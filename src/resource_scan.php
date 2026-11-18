<?php
/**
 * Automated checks on uploaded resources, run in the background by
 * bin/scan-worker.php.
 *
 * The scanner looks for two kinds of problem:
 *   PRIVACY   - other people's personal data: ID and card numbers, lists of
 *               emails or phone numbers, dates of birth, passwords...
 *   COPYRIGHT - material the student probably may not share: publisher
 *               copyright notices, ISBNs, answer keys and exam papers,
 *               "do not distribute" notices, or the same file already
 *               uploaded by a different student.
 *
 * Like moderation_flags() for listings, it NEVER decides anything by itself.
 * It gives the reviewing rep a list of things to look at; the rep decides.
 *
 * The file is split into three steps so each can be tested on its own:
 *   1. extract_resource_text()  file   -> plain text (+ notes about what could not be read)
 *   2. scan_text_for_risks()    text   -> flags   (pure function, no database)
 *   3. scan_next_resource()     queue  -> claims one queued resource, scans it, saves the flags
 */

const SCAN_MAX_TEXT = 2_000_000;      // characters of text we look at, at most
const SCAN_MAX_UNPACKED = 5_000_000;  // bytes we unpack from one ZIP entry or PDF stream (zip-bomb guard)

const SCAN_KIND_LABELS = ['privacy' => 'Privacy', 'copyright' => 'Copyright', 'scan' => 'Check by eye'];

const KNOWN_PUBLISHERS = [
    'Pearson', 'McGraw-Hill', 'McGraw Hill', 'Wiley', 'Elsevier', 'Springer', 'Cengage', "O'Reilly", 'Oxford University Press',
    'Cambridge University Press', 'Routledge', 'SAGE Publications', 'Macmillan', 'Taylor & Francis', 'Packt', 'Manning Publications',
    'No Starch Press', 'Prentice Hall', 'Addison-Wesley', 'Harvard Business School Publishing',
];

/* ------------------------------------------------------------------
 * 1. Getting the text out of a file
 * ------------------------------------------------------------------ */

/**
 * Returns ['text' => string, 'author' => ?string, 'flags' => [...]].
 * 'flags' are notes for the rep about parts the scanner could not read.
 */
function extract_resource_text(string $path, string $extension): array
{
    $result = ['text' => '', 'author' => null, 'flags' => []];
    $note = fn (string $message) => ['severity' => 'warning', 'kind' => 'scan', 'message' => $message];

    switch ($extension) {
        case 'txt':
        case 'md':
            $result['text'] = (string) file_get_contents($path, false, null, 0, SCAN_MAX_TEXT);
            break;

        case 'pdf':
            [$result['text'], $result['author']] = extract_pdf_text((string) file_get_contents($path));
            if (mb_strlen(trim($result['text'])) < 200) {
                $result['flags'][] = $note('Very little readable text — it may be scanned pages or photos. Open it and check.');
            }
            break;

        case 'docx':
        case 'pptx':
        case 'xlsx':
            if (!class_exists(ZipArchive::class)) {
                $result['flags'][] = $note('The server cannot open Office files (PHP zip extension missing), so the content was not checked.');
                break;
            }
            [$result['text'], $result['author']] = extract_office_text($path, $extension);
            break;

        case 'zip':
            if (!class_exists(ZipArchive::class)) {
                $result['flags'][] = $note('The server cannot open ZIP files, so the content was not checked.');
                break;
            }
            [$result['text'], $count, $skipped] = extract_zip_text($path);
            $result['flags'][] = $note("ZIP archive with $count " . ($count === 1 ? 'file' : 'files') . ($skipped > 0 ? "; $skipped could not be read automatically" : '') . '.');
            break;

        case 'png':
        case 'jpg':
        case 'jpeg':
            $result['flags'][] = $note('Images cannot be read automatically. Look for faces, ID cards, screenshots of chats or photographed book pages.');
            break;
    }

    $result['text'] = mb_substr(mb_convert_encoding($result['text'], 'UTF-8', 'UTF-8'), 0, SCAN_MAX_TEXT);
    return $result;
}

/** Text of a .docx / .pptx / .xlsx (they are ZIP files full of XML), and the author from its properties. */
function extract_office_text(string $path, string $extension): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['', null];
    }
    $parts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $wanted = match ($extension) {
            'docx' => preg_match('#^word/(document|header\d*|footer\d*|footnotes|comments)\.xml$#', $name),
            'pptx' => preg_match('#^ppt/(slides/slide|notesSlides/notesSlide)\d+\.xml$#', $name),
            'xlsx' => preg_match('#^xl/(sharedStrings|worksheets/sheet\d+)\.xml$#', $name),
        };
        if ($wanted) {
            $parts[] = xml_to_text((string) $zip->getFromIndex($i, SCAN_MAX_UNPACKED));
        }
    }
    $author = null;
    $core = $zip->getFromName('docProps/core.xml', 100_000);
    if (is_string($core) && preg_match('#<dc:creator>([^<]{1,120})</dc:creator>#', $core, $match)) {
        $author = html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    $zip->close();
    return [implode("\n", $parts), $author];
}

/** Text files inside a ZIP (txt, md, csv, docx...). Returns [text, number of files, files we could not read]. */
function extract_zip_text(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['', 0, 0];
    }
    $text = '';
    $skipped = 0;
    $count = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (str_ends_with($name, '/')) {
            continue; // a folder
        }
        $count++;
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, ['txt', 'md', 'csv', 'html', 'json', 'xml', 'py', 'java', 'js', 'php', 'c', 'cpp', 'sql'], true)) {
            $text .= "\n" . $zip->getFromIndex($i, SCAN_MAX_UNPACKED);
        } else {
            $skipped++;
        }
        if (strlen($text) > SCAN_MAX_TEXT) {
            break;
        }
    }
    $zip->close();
    return [$text, $count, $skipped];
}

/** Office XML -> text, keeping paragraph and cell breaks as new lines. */
function xml_to_text(string $xml): string
{
    $xml = preg_replace('#</(w:p|a:p|si|row)>|<(w:br|w:tab|a:br)\b[^>]*/>#', "\n", $xml);
    return html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * A small PDF text extractor. It unpacks compressed (FlateDecode) content
 * streams and collects the text drawn by the Tj / TJ operators. It cannot read
 * scanned pages or unusual font encodings; the caller warns when little text is found.
 * Returns [text, author from the document info, or null].
 */
function extract_pdf_text(string $pdf): array
{
    $chunks = [$pdf];
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams)) {
        foreach ($streams[1] as $stream) {
            $unpacked = @gzuncompress($stream, SCAN_MAX_UNPACKED);
            if ($unpacked !== false) {
                $chunks[] = $unpacked;
            }
        }
    }

    $lines = [];
    $string = '\((?:\\\\.|[^\\\\)])*\)';
    foreach ($chunks as $chunk) {
        preg_match_all('/\[((?:' . $string . '|[^\]])*)\]\s*TJ|(' . $string . ')\s*(?:Tj|\'|")/s', $chunk, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                preg_match_all('/' . $string . '/s', $match[1], $pieces);
                $lines[] = implode('', array_map('pdf_unescape', $pieces[0]));
            } else {
                $lines[] = pdf_unescape($match[2]);
            }
        }
    }

    $author = preg_match('/\/Author\s*(' . $string . ')/s', $pdf, $match) ? trim(pdf_unescape($match[1])) : null;
    return [implode("\n", $lines), $author !== '' ? $author : null];
}

/** "(Hello \(world\))" -> "Hello (world)". */
function pdf_unescape(string $literal): string
{
    $inner = substr($literal, 1, -1);
    return preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', function ($m) {
        $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C", '(' => '(', ')' => ')', '\\' => '\\'];
        return $map[$m[1]] ?? chr(octdec($m[1]) & 0xFF);
    }, $inner);
}

/* ------------------------------------------------------------------
 * 2. Looking for risks in the text (pure: no files, no database)
 * ------------------------------------------------------------------ */

/**
 * $resource needs: ip_ownership, ip_credits, declared_name.
 * $author is the author stored in the file's properties, if any.
 * Returns flags: [['severity' => 'critical'|'warning', 'kind' => 'privacy'|'copyright', 'message' => '...'], ...]
 */
function scan_text_for_risks(string $text, array $resource, ?string $author = null): array
{
    $flags = [];
    $add = function (string $severity, string $kind, string $message) use (&$flags) {
        $flags[] = ['severity' => $severity, 'kind' => $kind, 'message' => $message];
    };

    /* ---------- privacy ---------- */

    preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $emails);
    $emails = array_values(array_unique(array_map('strtolower', $emails[0])));
    if (count($emails) >= 3) {
        $add('critical', 'privacy', 'Contains ' . count($emails) . ' email addresses (e.g. ' . mask_email($emails[0]) . ') — may be a contact list.');
    } elseif ($emails !== []) {
        $add('warning', 'privacy', 'Contains an email address (' . mask_email($emails[0]) . ').');
    }

    preg_match_all('/(?<![\d+])(?:\+233[\s-]?|0)[235]\d[\s-]?\d{3}[\s-]?\d{4}(?!\d)|\+\d{1,3}[\s-]\(?\d{1,4}\)?(?:[\s-]?\d{2,4}){2,4}(?!\d)/', $text, $phones);
    $phones = array_values(array_unique(array_map(fn ($p) => preg_replace('/\D/', '', $p), $phones[0])));
    if (count($phones) >= 3) {
        $add('critical', 'privacy', 'Contains ' . count($phones) . ' phone numbers — may be a list of people’s contacts.');
    } elseif ($phones !== []) {
        $add('warning', 'privacy', 'Contains a phone number (ending ' . substr($phones[0], -3) . ').');
    }

    if (preg_match('/\bGHA-?\d{9}-?\d\b/i', $text)) {
        $add('critical', 'privacy', 'Contains a Ghana Card (national ID) number.');
    }
    if (preg_match('/\b(passport|national\s+id|id\s+card)\s*(no\.?|number|#)?\s*[:#]?\s*[A-Z]{0,3}\d[A-Z0-9-]{5,}/i', $text)) {
        $add('critical', 'privacy', 'Contains what looks like a passport or ID card number.');
    }
    if (preg_match('/\b(student|index|matric(?:ulation)?)\s*(id|no\.?|number)\s*[:#]?\s*[A-Z0-9\/-]*\d[A-Z0-9\/-]{3,}/i', $text)) {
        $add('warning', 'privacy', 'Contains a student or index number.');
    }
    if (contains_card_number($text)) {
        $add('critical', 'privacy', 'Contains what looks like a bank card number.');
    }

    $sensitive = [
        'date of birth' => '/\b(date\s+of\s+birth|d\.?o\.?b\.?\s*[:\-])/i',
        'a password'    => '/\b(password|passcode|pin)\s*[:=]\s*\S+/i',
        'health information' => '/\b(medical\s+(record|history|condition)|diagnos(is|ed)\s+with|prescription)\b/i',
        'bank details'  => '/\b(bank\s+account|account\s+number|iban|mobile\s+money\s+number)\b/i',
        'someone’s grades' => '/\b(transcript|cgpa|gpa)\s*[:\-]?\s*\d/i',
        'a home address' => '/\b(home\s+address|residential\s+address)\b/i',
    ];
    foreach ($sensitive as $what => $pattern) {
        if (preg_match($pattern, $text)) {
            $add('warning', 'privacy', "Mentions $what.");
        }
    }

    /* ---------- copyright ---------- */

    $owners = owner_words($resource);
    $soleOwner = ($resource['ip_ownership'] ?? '') === 'sole';

    $foreignNotices = [];
    // A copyright NOTICE ("© 2021 Pearson", "Copyright 2020 Jane Doe"), not just the word "copyright" in a sentence.
    $notice = '/(?:©|\(c\)(?=\s*\d{4})|\bcopyright\s*(?:©|\(c\))?(?=\s*(?:\d{4}|©)))\s*©?\s*(?:\d{4}(?:\s*[-–]\s*\d{2,4})?)?[\s,]*(?:by\s+)?([^\n;©]{2,60})/iu';
    if (preg_match_all($notice, $text, $notices)) {
        foreach ($notices[1] as $holder) {
            $holder = trim(preg_replace('/[.,]?\s*(all rights reserved|isbn).*$/i', '', $holder), " .,\t");
            if ($holder !== '' && !mentions_any($holder, $owners)) {
                $foreignNotices[] = $holder;
            }
        }
    }
    $foreignNotices = array_values(array_unique($foreignNotices));

    $publishers = array_values(array_filter(KNOWN_PUBLISHERS, fn ($p) => stripos($text, $p) !== false));
    $hasIsbn = preg_match('/\bISBN(?:-1[03])?:?\s*(?=[0-9X -]{10,17}\b)[0-9][0-9 -]{8,15}[0-9X]\b/i', $text) === 1;

    if ($publishers !== [] && ($hasIsbn || $foreignNotices !== [])) {
        $add('critical', 'copyright', 'Looks like pages from a published book (' . $publishers[0] . ($hasIsbn ? ', ISBN' : '') . ').');
    } else {
        if ($foreignNotices !== []) {
            $add($soleOwner ? 'critical' : 'warning', 'copyright',
                'Copyright notice by someone else: “' . mb_substr($foreignNotices[0], 0, 50) . '”'
                . ($soleOwner ? ' — but the student declared they created all of it.' : '.'));
        }
        if ($hasIsbn) {
            $add('warning', 'copyright', 'Contains an ISBN — check that it only cites the book and does not copy it.');
        }
        if ($publishers !== []) {
            $add('warning', 'copyright', 'Mentions the publisher ' . $publishers[0] . ' — check that nothing is copied from their books.');
        }
    }
    if (preg_match('/\ball rights reserved\b/i', $text) && $foreignNotices === [] && ($resource['license'] ?? '') !== 'all_rights') {
        $add('warning', 'copyright', 'Says “All rights reserved”, but the student chose an open licence.');
    }

    if (preg_match('/\b(answer\s+key|marking\s+scheme|mark\s+scheme|solutions?\s+manual|instructor[\'’]?s?\s+(manual|edition|solutions|guide)|test\s+bank|model\s+answers)\b/i', $text, $match)) {
        $add('critical', 'copyright', 'Looks like instructor or assessment material (“' . strtolower($match[1]) . '”).');
    } elseif (preg_match('/\b(exam\s+paper|past\s+(exam\s+)?questions|final\s+exam(ination)?|mid-?\s?term\s+exam|quiz\s+\d+\s+answers)\b/i', $text, $match)) {
        $add('warning', 'copyright', 'Mentions exam material (“' . strtolower($match[1]) . '”) — make sure it is not a real exam paper.');
    }

    if (preg_match('/\b(do\s+not\s+(distribute|copy|share|reproduce|circulate)|not\s+for\s+(distribution|circulation|redistribution)|for\s+internal\s+use\s+only|strictly\s+confidential|licensed\s+(solely\s+)?to\s+\w+)/i', $text, $match)) {
        $add('critical', 'copyright', 'Contains a restriction notice: “' . strtolower($match[1]) . '”.');
    }

    if ($author !== null && trim($author) !== '' && !mentions_any($author, $owners)) {
        $add('warning', 'copyright', 'The file’s properties name a different author: “' . mb_substr($author, 0, 60) . '”.');
    }

    return $flags;
}

/** Words that identify the people who may own the work: the signer's name and the declared credits. */
function owner_words(array $resource): array
{
    $text = ($resource['declared_name'] ?? '') . ' ' . ($resource['ip_credits'] ?? '');
    $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter($words, fn ($word) => mb_strlen($word) >= 3));
}

function mentions_any(string $text, array $words): bool
{
    $text = mb_strtolower($text);
    foreach ($words as $word) {
        if (str_contains($text, $word)) {
            return true;
        }
    }
    return false;
}

/** "ama.mensah@example.org" -> "a***@example.org", so the rep's page does not repeat the personal data. */
function mask_email(string $email): string
{
    [$name, $domain] = explode('@', $email, 2);
    return mb_substr($name, 0, 1) . '***@' . $domain;
}

/** True when the text has a 13–19 digit number that passes the Luhn check used by bank cards. */
function contains_card_number(string $text): bool
{
    $text = preg_replace('/\bISBN[^\n]{0,25}/i', '', $text); // ISBNs are 13 digits too and can pass the Luhn check
    preg_match_all('/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/', $text, $candidates);
    foreach ($candidates[0] as $candidate) {
        $digits = preg_replace('/\D/', '', $candidate);
        if (luhn_valid($digits) && !preg_match('/^(\d)\1+$/', $digits)) {
            return true;
        }
    }
    return false;
}

function luhn_valid(string $digits): bool
{
    $sum = 0;
    $double = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $digit = (int) $digits[$i];
        if ($double) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $double = !$double;
    }
    return $sum % 10 === 0;
}

/** Critical flags first, then warnings. */
function sort_scan_flags(array $flags): array
{
    usort($flags, fn ($a, $b) => ($a['severity'] === 'critical' ? 0 : 1) <=> ($b['severity'] === 'critical' ? 0 : 1));
    return $flags;
}

/* ------------------------------------------------------------------
 * 3. The queue: used by bin/scan-worker.php
 * ------------------------------------------------------------------ */

/**
 * Takes the oldest queued resource, scans it and saves the result.
 * Returns ['id' => ..., 'flags' => [...]] or null when the queue is empty.
 *
 * "Claiming" with UPDATE ... WHERE scan_status = 'queued' means two workers
 * running at the same time never scan the same resource twice.
 */
function scan_next_resource(): ?array
{
    $next = fetch_one("SELECT id FROM resources WHERE scan_status = 'queued' ORDER BY id LIMIT 1");
    if ($next === null) {
        return null;
    }
    $id = (int) $next['id'];
    if (execute("UPDATE resources SET scan_status = 'scanning' WHERE id = ? AND scan_status = 'queued'", [$id]) === 0) {
        return ['id' => $id, 'flags' => null]; // another worker took it
    }

    $resource = fetch_one('SELECT * FROM resources WHERE id = ?', [$id]);
    try {
        $flags = scan_resource_file($resource);
        execute(
            "UPDATE resources SET scan_status = 'done', scan_flags = ?, file_sha256 = ?, scanned_at = UTC_TIMESTAMP() WHERE id = ?",
            [json_encode($flags['flags'], JSON_UNESCAPED_UNICODE), $flags['sha256'], $id]
        );
        return ['id' => $id, 'flags' => $flags['flags']];
    } catch (Throwable $error) {
        error_log("Scanning resource $id failed: " . $error->getMessage());
        $failed = [['severity' => 'warning', 'kind' => 'scan', 'message' => 'The automatic check failed. Open the file and check it by eye.']];
        execute(
            "UPDATE resources SET scan_status = 'failed', scan_flags = ?, scanned_at = UTC_TIMESTAMP() WHERE id = ?",
            [json_encode($failed), $id]
        );
        return ['id' => $id, 'flags' => $failed];
    }
}

/** Scans one resource row's file. Returns ['flags' => [...], 'sha256' => '...']. */
function scan_resource_file(array $resource): array
{
    $path = upload_directory() . '/' . basename($resource['stored_name']);
    if (!is_file($path)) {
        throw new RuntimeException('File is missing on disk.');
    }
    $sha256 = hash_file('sha256', $path);
    $extension = strtolower(pathinfo($resource['stored_name'], PATHINFO_EXTENSION));

    $extracted = extract_resource_text($path, $extension);
    $flags = array_merge(scan_text_for_risks($extracted['text'], $resource, $extracted['author']), $extracted['flags']);

    // The exact same file, uploaded earlier by a DIFFERENT student.
    $copy = fetch_one(
        'SELECT title FROM resources WHERE file_sha256 = ? AND uploaded_by <> ? AND id <> ? ORDER BY id LIMIT 1',
        [$sha256, $resource['uploaded_by'], $resource['id']]
    );
    if ($copy !== null) {
        $flags[] = ['severity' => 'critical', 'kind' => 'copyright', 'message' => 'Identical to a file another student already uploaded (“' . $copy['title'] . '”).'];
    }

    return ['flags' => sort_scan_flags($flags), 'sha256' => $sha256];
}

/** The saved flags of a resource row, or null while it has not been scanned yet. */
function resource_scan_flags(array $resource): ?array
{
    if (!in_array($resource['scan_status'], ['done', 'failed'], true)) {
        return null;
    }
    $flags = json_decode((string) $resource['scan_flags'], true);
    return is_array($flags) ? $flags : [];
}
