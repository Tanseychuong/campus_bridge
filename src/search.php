<?php
/**
 * The search engine for the Explore page.
 *
 * One search box finds opportunities, events AND organizations.
 *
 * How a result is scored, for each word the student typed:
 *   1. Look for the word in each field of the item.
 *   2. A match in the title counts most (x3), then provider/organization and
 *      tags (x2), then the description (x1).
 *   3. An exact word counts fully (1.0), the start of a word counts 0.7
 *      ("intern" finds "internship"), a one-letter typo counts 0.4
 *      ("scholarshp" finds "scholarship").
 *   4. Every typed word must be found somewhere, otherwise the item is skipped.
 *   5. Bonus points if the whole search appears in the title.
 */

const SEARCH_FIELD_WEIGHTS = ['title' => 3, 'subtitle' => 2, 'tags' => 2, 'body' => 1];
const SEARCH_MATCH_QUALITY = ['exact' => 1.0, 'prefix' => 0.7, 'typo' => 0.4];
const SEARCH_PHRASE_BONUS = 3;

/** Common words that are ignored when searching. */
const STOP_WORDS = [
    'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'in', 'is', 'it',
    'of', 'on', 'or', 'the', 'to', 'with', 'your', 'you', 'our', 'we', 'this', 'that',
];

/** Accented letters and their plain versions (includes the Ghanaian letters ɛ and ɔ). */
const ACCENTED_LETTERS = [
    'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'ç' => 'c',
    'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ɛ' => 'e',
    'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
    'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ɔ' => 'o',
    'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
];

/** Lower-case and remove accents and punctuation: "Café — Résumé!" -> "cafe resume". */
function normalize_text(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, ACCENTED_LETTERS);              // é -> e
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);    // anything else that is not a letter or digit becomes a space
    return trim($text);
}

/** Splits text into search words, skipping stop words and single letters. */
function tokenize(string $text): array
{
    $words = explode(' ', normalize_text($text));
    return array_values(array_filter($words, fn ($word) => strlen($word) > 1 && !in_array($word, STOP_WORDS, true)));
}

/**
 * True if two words differ by at most ONE letter (one added, removed or changed).
 * Example: "scholarshp" vs "scholarship" -> true.
 */
function within_one_edit(string $a, string $b): bool
{
    if ($a === $b) {
        return true;
    }
    if (abs(strlen($a) - strlen($b)) > 1) {
        return false;
    }
    // PHP's built-in levenshtein() counts the edits needed to turn $a into $b.
    return levenshtein($a, $b) <= 1;
}

/** How well one typed word matches a list of words from a field (0 = no match). */
function match_quality(string $typedWord, array $fieldWords): float
{
    $best = 0.0;
    foreach ($fieldWords as $word) {
        if ($word === $typedWord) {
            return SEARCH_MATCH_QUALITY['exact'];
        }
        if (strlen($typedWord) >= 2 && str_starts_with($word, $typedWord)) {
            $best = max($best, SEARCH_MATCH_QUALITY['prefix']);
        } elseif (strlen($typedWord) >= 4 && within_one_edit($typedWord, $word)) {
            $best = max($best, SEARCH_MATCH_QUALITY['typo']);
        }
    }
    return $best;
}

/**
 * Turns the catalogue into a list of searchable "documents", one per item.
 * Closed opportunities and past events are left out.
 */
function build_search_index(array $opportunities, array $events, array $organizations, DateTimeImmutable $now): array
{
    $documents = [];

    foreach ($opportunities as $o) {
        if ($o['status'] !== 'published' || is_closed($o['deadline'], $now)) {
            continue;
        }
        $categoryLabel = OPPORTUNITY_CATEGORIES[$o['category']];
        $documents[] = [
            'type' => 'opportunity', 'item' => $o, 'category' => $o['category'], 'interests' => $o['interests'],
            'date' => $o['deadline'], 'published_at' => $o['published_at'], 'location_mode' => $o['location_mode'],
            'title' => $o['title'],
            'words' => [
                'title'    => tokenize($o['title']),
                'subtitle' => tokenize($o['provider']),
                'tags'     => tokenize($categoryLabel . ' ' . interest_labels($o['interests'])),
                'body'     => tokenize($o['summary'] . ' ' . $o['description'] . ' ' . ($o['location_place'] ?? '')),
            ],
        ];
    }

    foreach ($events as $e) {
        if ($e['status'] !== 'published' || timestamp_of($e['ends_at']) <= $now->getTimestamp()) {
            continue;
        }
        $documents[] = [
            'type' => 'event', 'item' => $e, 'category' => $e['category'], 'interests' => $e['interests'],
            'date' => $e['starts_at'], 'published_at' => null, 'location_mode' => null,
            'title' => $e['title'],
            'words' => [
                'title'    => tokenize($e['title']),
                'subtitle' => tokenize($e['organization_name']),
                'tags'     => tokenize(EVENT_CATEGORIES[$e['category']] . ' ' . interest_labels($e['interests'])),
                'body'     => tokenize($e['summary'] . ' ' . $e['description'] . ' ' . $e['venue']),
            ],
        ];
    }

    foreach ($organizations as $org) {
        $documents[] = [
            'type' => 'organization', 'item' => $org, 'category' => $org['category'], 'interests' => $org['interests'],
            'date' => null, 'published_at' => null, 'location_mode' => null,
            'title' => $org['name'],
            'words' => [
                'title'    => tokenize($org['name']),
                'subtitle' => tokenize($org['short_name']),
                'tags'     => tokenize(ORGANIZATION_CATEGORIES[$org['category']] . ' ' . interest_labels($org['interests'])),
                'body'     => tokenize($org['summary'] . ' ' . $org['description'] . ' ' . $org['activities']),
            ],
        ];
    }

    return $documents;
}

/** The relevance score of one document for a search (0 means "not a match"). */
function score_document(array $document, string $query): float
{
    $typedWords = tokenize($query);
    if ($typedWords === []) {
        return 0.0;
    }

    $total = 0.0;
    foreach ($typedWords as $typedWord) {
        $bestForThisWord = 0.0;
        foreach (SEARCH_FIELD_WEIGHTS as $field => $weight) {
            $bestForThisWord = max($bestForThisWord, $weight * match_quality($typedWord, $document['words'][$field]));
        }
        if ($bestForThisWord === 0.0) {
            return 0.0; // every typed word must match somewhere
        }
        $total += $bestForThisWord;
    }

    $wholeQueryInTitle = count($typedWords) > 1 && str_contains(normalize_text($document['title']), normalize_text($query));
    if ($wholeQueryInTitle) {
        $total += SEARCH_PHRASE_BONUS;
    }
    return round($total, 2);
}

/* ------------------------------------------------------------------
 * Filters (read from the URL, e.g. explore.php?q=data&type=event)
 * ------------------------------------------------------------------ */

const EXPLORE_DEFAULTS = [
    'q' => '', 'type' => 'all', 'interests' => [], 'category' => '', 'within' => 'any', 'mode' => '', 'sort' => 'relevance',
];

/**
 * Reads the filters from the URL.
 * The URL can be edited by anyone, so every value is checked against the
 * allowed list. Anything unexpected falls back to the default.
 */
function read_explore_filters(array $query): array
{
    $filters = EXPLORE_DEFAULTS;

    $filters['q'] = is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, 100) : '';

    $type = $query['type'] ?? 'all';
    $filters['type'] = in_array($type, ['all', 'opportunity', 'event', 'organization'], true) ? $type : 'all';

    $interests = $query['interest'] ?? [];
    $interests = is_array($interests) ? $interests : [$interests];
    $filters['interests'] = array_values(array_unique(array_filter($interests, fn ($i) => is_string($i) && isset(INTEREST_LABELS[$i]))));

    $categoryLists = ['opportunity' => OPPORTUNITY_CATEGORIES, 'event' => EVENT_CATEGORIES, 'organization' => ORGANIZATION_CATEGORIES];
    $category = $query['category'] ?? '';
    if ($filters['type'] !== 'all' && is_string($category) && isset($categoryLists[$filters['type']][$category])) {
        $filters['category'] = $category;
    }

    $within = $query['within'] ?? 'any';
    $filters['within'] = in_array($within, ['any', '7', '30'], true) ? $within : 'any';

    $mode = $query['mode'] ?? '';
    if ($filters['type'] === 'opportunity' && is_string($mode) && isset(LOCATION_MODES[$mode])) {
        $filters['mode'] = $mode;
    }

    $sort = $query['sort'] ?? 'relevance';
    $filters['sort'] = in_array($sort, ['relevance', 'soonest', 'newest'], true) ? $sort : 'relevance';

    return $filters;
}

/** Builds the query-string for a set of filters, leaving out default values. */
function explore_query_string(array $filters): string
{
    $parts = [];
    foreach ($filters as $name => $value) {
        if ($value === EXPLORE_DEFAULTS[$name]) {
            continue;
        }
        $parts[$name === 'interests' ? 'interest' : $name] = $value;
    }
    // http_build_query writes lists as interest[0]=a; PHP reads interest[]=a the same way.
    return http_build_query($parts);
}

function count_active_filters(array $filters): int
{
    return count($filters['interests'])
        + ($filters['category'] !== '' ? 1 : 0)
        + ($filters['within'] !== 'any' ? 1 : 0)
        + ($filters['mode'] !== '' ? 1 : 0);
}

function passes_filters(array $document, array $filters, DateTimeImmutable $now): bool
{
    if ($filters['interests'] !== [] && array_intersect($filters['interests'], $document['interests']) === []) {
        return false;
    }
    if ($filters['category'] !== '' && $document['category'] !== $filters['category']) {
        return false;
    }
    if ($filters['mode'] !== '' && $document['location_mode'] !== $filters['mode']) {
        return false;
    }
    if ($filters['within'] !== 'any') {
        if ($document['date'] === null) {
            return false;
        }
        $limit = $now->getTimestamp() + (int) $filters['within'] * 86400;
        if (timestamp_of($document['date']) > $limit) {
            return false;
        }
    }
    return true;
}

/**
 * Runs a search.
 * Returns ['results' => [['document' => ..., 'score' => ...], ...],
 *          'counts'  => ['all' => 12, 'opportunity' => 5, 'event' => 4, 'organization' => 3]]
 * The counts ignore the "type" filter so the tabs can show how many of each exist.
 */
function run_search(array $documents, array $filters, DateTimeImmutable $now): array
{
    $hasQuery = tokenize($filters['q']) !== [];
    $matches = [];

    foreach ($documents as $document) {
        if (!passes_filters($document, $filters, $now)) {
            continue;
        }
        $score = $hasQuery ? score_document($document, $filters['q']) : 0.0;
        if ($hasQuery && $score === 0.0) {
            continue;
        }
        $matches[] = ['document' => $document, 'score' => $score];
    }

    $counts = ['all' => count($matches), 'opportunity' => 0, 'event' => 0, 'organization' => 0];
    foreach ($matches as $match) {
        $counts[$match['document']['type']]++;
    }

    if ($filters['type'] !== 'all') {
        $matches = array_values(array_filter($matches, fn ($m) => $m['document']['type'] === $filters['type']));
    }

    usort($matches, fn ($a, $b) => compare_search_results($a, $b, $filters['sort']));

    return ['results' => $matches, 'counts' => $counts];
}

/** Sorting rule for results. Items without a date go last. */
function compare_search_results(array $a, array $b, string $sort): int
{
    $byDate = [$a['document']['date'] ?? '9999', $a['document']['title']] <=> [$b['document']['date'] ?? '9999', $b['document']['title']];

    if ($sort === 'soonest') {
        return $byDate;
    }
    if ($sort === 'newest') {
        return (($b['document']['published_at'] ?? '') <=> ($a['document']['published_at'] ?? '')) ?: $byDate;
    }
    return ($b['score'] <=> $a['score']) ?: $byDate; // relevance
}
