<?php

use PHPUnit\Framework\TestCase;

/** The privacy and copyright scanner used by bin/scan-worker.php. */
final class ResourceScanTest extends TestCase
{
    private function resource(array $overrides = []): array
    {
        return array_merge(['ip_ownership' => 'sole', 'ip_credits' => '', 'license' => 'cc_by', 'declared_name' => 'Ama Mensah'], $overrides);
    }

    /** Just the messages, for readable assertions. */
    private function messages(array $flags): string
    {
        return implode("\n", array_map(fn ($flag) => $flag['severity'] . ' ' . $flag['kind'] . ': ' . $flag['message'], $flags));
    }

    public function test_ordinary_study_notes_raise_nothing(): void
    {
        $notes = "Week 3: Sorting algorithms\nMerge sort splits the list in half, sorts each half and merges them. "
            . "Copyright law is covered in week 9. See chapter 2 of the course reader.\n© 2026 Ama Mensah";
        $this->assertSame([], scan_text_for_risks($notes, $this->resource()));
    }

    public function test_personal_data_is_flagged_without_repeating_it(): void
    {
        $text = "Contacts: kofi.addo@example.org, esi@example.org, yaw@example.org\n"
            . "Call 024 123 4567 or +233 20 765 4321 or 055 999 0000\nGhana Card: GHA-123456789-0\nDate of birth: 01/02/2004";
        $flags = $this->messages(scan_text_for_risks($text, $this->resource()));

        $this->assertStringContainsString('critical privacy: Contains 3 email addresses (e.g. k***@example.org)', $flags);
        $this->assertStringContainsString('critical privacy: Contains 3 phone numbers', $flags);
        $this->assertStringContainsString('Ghana Card', $flags);
        $this->assertStringContainsString('Mentions date of birth', $flags);
        $this->assertStringNotContainsString('kofi.addo', $flags); // masked
    }

    public function test_bank_card_numbers_use_the_luhn_check(): void
    {
        $this->assertTrue(contains_card_number('Card: 4111 1111 1111 1111'));
        $this->assertFalse(contains_card_number('Order 4111 1111 1111 1112')); // fails Luhn
        $this->assertFalse(contains_card_number('0000000000000000'));
    }

    public function test_copied_book_pages_are_critical(): void
    {
        $text = "Introduction to Algorithms\n© 2022 Pearson Education, Inc. All rights reserved.\nISBN 978-0-13-468599-1";
        $flags = scan_text_for_risks($text, $this->resource());
        $this->assertSame('critical', $flags[0]['severity']);
        $this->assertStringContainsString('Looks like pages from a published book (Pearson, ISBN)', $this->messages($flags));
    }

    public function test_someone_elses_copyright_contradicts_a_sole_ownership_declaration(): void
    {
        $text = 'Copyright 2021 Dr. K. Boateng';
        $sole = $this->messages(scan_text_for_risks($text, $this->resource()));
        $this->assertStringContainsString('critical copyright: Copyright notice by someone else', $sole);
        $this->assertStringContainsString('declared they created all of it', $sole);

        // Declared and credited: still worth a look, but only a warning.
        $credited = scan_text_for_risks($text, $this->resource(['ip_ownership' => 'third_party', 'ip_credits' => 'Slides by Dr K. Boateng, used with permission']));
        $this->assertSame([], $credited);
    }

    public function test_exam_material_and_restriction_notices(): void
    {
        $answerKey = $this->messages(scan_text_for_risks('CS201 Midsemester — ANSWER KEY', $this->resource()));
        $this->assertStringContainsString('critical copyright: Looks like instructor or assessment material (“answer key”)', $answerKey);

        $restricted = $this->messages(scan_text_for_risks('Lecture 4 slides. Do not distribute.', $this->resource()));
        $this->assertStringContainsString('critical copyright: Contains a restriction notice: “do not distribute”', $restricted);

        $pastQuestions = $this->messages(scan_text_for_risks('Practice: past questions 2019', $this->resource()));
        $this->assertStringContainsString('warning copyright: Mentions exam material', $pastQuestions);
    }

    public function test_a_different_author_in_the_file_properties_is_noted(): void
    {
        $this->assertStringContainsString('different author: “John Smith”', $this->messages(scan_text_for_risks('notes', $this->resource(), 'John Smith')));
        $this->assertSame([], scan_text_for_risks('notes', $this->resource(), 'Ama Mensah'));
    }

    public function test_text_is_read_from_compressed_pdf_streams(): void
    {
        $content = "BT /F1 12 Tf (Answer key \\(final\\)) Tj [(Do not dis) -20 (tribute)] TJ ET";
        $pdf = "%PDF-1.4\n1 0 obj << /Author (John Smith) >> endobj\n2 0 obj << /Filter /FlateDecode >>\nstream\n"
            . gzcompress($content) . "\nendstream\nendobj\n%%EOF";

        [$text, $author] = extract_pdf_text($pdf);
        $this->assertStringContainsString('Answer key (final)', $text);
        $this->assertStringContainsString('Do not distribute', $text);
        $this->assertSame('John Smith', $author);
    }

    public function test_text_is_read_from_word_files(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP zip extension not installed.');
        }
        $path = tempnam(sys_get_temp_dir(), 'cb_docx_');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>Solutions manual</w:t></w:r></w:p><w:p><w:r><w:t>Chapter 1</w:t></w:r></w:p></w:body></w:document>');
        $zip->addFromString('docProps/core.xml', '<cp:coreProperties><dc:creator>Prof. Owusu</dc:creator></cp:coreProperties>');
        $zip->close();

        $result = extract_resource_text($path, 'docx');
        unlink($path);
        $this->assertStringContainsString("Solutions manual\nChapter 1", $result['text']);
        $this->assertSame('Prof. Owusu', $result['author']);
    }

    public function test_images_are_left_for_the_rep_to_check(): void
    {
        $result = extract_resource_text(__FILE__, 'png');
        $this->assertSame('scan', $result['flags'][0]['kind']);
        $this->assertStringContainsString('Images cannot be read automatically', $result['flags'][0]['message']);
    }
}
