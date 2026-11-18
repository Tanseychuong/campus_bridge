<?php

/** Campus-only events, and the student resource + IP declaration workflow. */
final class CampusAndResourcesTest extends WebTestCase
{
    /* ---------- campus visibility ---------- */

    public function test_campus_only_events_are_hidden_from_guests(): void
    {
        $hillsideOnly = $this->idOf('events', 'Inter-Year Football Tournament');
        $this->assertSame(404, $this->get("event.php?id=$hillsideOnly")['status']);
        $this->assertDontSeeText('Inter-Year Football Tournament', $this->get('events.php'));
        $this->assertSeeText('Intro to Web Development Workshop', $this->get('events.php')); // public events still show
    }

    public function test_students_only_see_campus_only_events_of_their_own_campus(): void
    {
        $hillsideOnly = $this->idOf('events', 'Inter-Year Football Tournament');
        $riversideOnly = $this->idOf('events', 'Podcast Production Training');

        $this->signIn('student@example.org'); // Hillside
        $this->assertSame(200, $this->get("event.php?id=$hillsideOnly")['status']);
        $this->assertSame(404, $this->get("event.php?id=$riversideOnly")['status']);
        $this->submit('index.php', 'logout.php', []);

        $this->signIn('riverside@example.org'); // Riverside
        $this->assertSame(404, $this->get("event.php?id=$hillsideOnly")['status']);
        $this->assertSame(200, $this->get("event.php?id=$riversideOnly")['status']);
    }

    public function test_a_hidden_event_cannot_be_saved_by_another_campus(): void
    {
        $this->signIn('riverside@example.org');
        $hillsideOnly = $this->idOf('events', 'Inter-Year Football Tournament');
        $response = $this->submit('events.php', 'toggle.php', ['kind' => 'event', 'id' => $hillsideOnly, 'return' => '/events.php'], false);
        $this->assertSame(404, $response['status']);
    }

    /* ---------- resources ---------- */

    private function uploadForm(string $title, string $content = "%PDF-1.4\n% test file\n", string $fileName = 'notes.pdf', string $mime = 'application/pdf'): array
    {
        $file = tempnam(sys_get_temp_dir(), 'cb_upload_');
        file_put_contents($file, $content);
        return [
            'title' => $title, 'description' => 'My own summary of the first six lectures.',
            'organization_id' => (string) $this->idOf('organizations', 'Code & Circuits Society'),
            'ip_ownership' => 'sole', 'ip_credits' => '', 'license' => 'cc_by', 'declared_name' => 'Ama Mensah',
            'confirm_rights' => '1', 'confirm_no_confidential' => '1', 'confirm_takedown' => '1',
            'file' => new CURLFile($file, $mime, $fileName),
        ];
    }

    private function decide(int $resourceId, string $decision, string $note = ''): array
    {
        return $this->submit('manage.php', 'manage-resource.php', ['id' => $resourceId, 'decision' => $decision, 'note' => $note]);
    }

    public function test_the_declaration_is_required(): void
    {
        $this->signIn('student@example.org');
        $response = $this->submit('resource-upload.php', 'resource-upload.php', ['confirm_takedown' => '', 'declared_name' => ''] + $this->uploadForm('Incomplete declaration'));
        $this->assertSeeText('Please confirm all three statements in the declaration.', $response);
        $this->assertSeeText('Type your full name to sign the declaration.', $response);
    }

    public function test_a_file_whose_content_does_not_match_its_extension_is_refused(): void
    {
        $this->signIn('student@example.org');
        $response = $this->submit('resource-upload.php', 'resource-upload.php', $this->uploadForm('Fake PDF', '<script>alert(1)</script>'));
        $this->assertSeeText('does not match its .pdf extension', $response);
    }

    public function test_a_rep_publishes_takes_down_and_republishes_a_resource(): void
    {
        $title = 'Calculus revision notes ' . uniqid();

        // 1. A student uploads it: private and waiting.
        $this->signIn('student@example.org');
        $mine = $this->submit('resource-upload.php', 'resource-upload.php', $this->uploadForm($title));
        $this->assertSeeText('was sent to Code & Circuits Society for review', $mine);
        $this->assertSeeText('Waiting for review', $mine);
        $resourceId = $this->idOf('resources', $title);
        $this->submit('resources.php', 'logout.php', []);

        $this->assertSame(404, $this->get("resource-download.php?id=$resourceId")['status']);
        $this->assertDontSeeText($title, $this->get('resources.php'));

        // 2. The rep sees the declaration and publishes it.
        $this->signIn('rep@example.org');
        $manage = $this->get('manage.php');
        $this->assertSeeText($title, $manage);
        $this->assertSeeText('I created all of it myself', $manage);
        $this->decide($resourceId, 'publish');
        $this->submit('manage.php', 'logout.php', []);

        $download = $this->get("resource-download.php?id=$resourceId");
        $this->assertSame(200, $download['status']);
        $this->assertStringContainsString('Content-Disposition: attachment', $download['headers']);
        $this->assertStringStartsWith('%PDF-1.4', $download['body']);
        $this->assertSeeText($title, $this->get('resources.php'));

        // 3. The rep takes it down, then brings it back.
        $this->signIn('rep@example.org');
        $this->decide($resourceId, 'hide');
        $this->submit('manage.php', 'logout.php', []);
        $this->assertSame(404, $this->get("resource-download.php?id=$resourceId")['status']);

        $this->signIn('rep@example.org');
        $this->assertSeeText('is now public', $this->decide($resourceId, 'publish'));
    }

    public function test_rejecting_a_resource_needs_a_reason_and_students_cannot_review(): void
    {
        $title = 'Resource to reject ' . uniqid();
        $this->signIn('student@example.org');
        $this->submit('resource-upload.php', 'resource-upload.php', $this->uploadForm($title));
        $resourceId = $this->idOf('resources', $title);

        $this->assertSame(403, $this->submit('resources.php', 'manage-resource.php', ['id' => $resourceId, 'decision' => 'publish'], false)['status']);
        $this->submit('resources.php', 'logout.php', []);

        $this->signIn('rep@example.org');
        $this->assertSeeText('Please write a short reason', $this->decide($resourceId, 'reject'));
        $this->decide($resourceId, 'reject', 'Please remove the scanned textbook pages.');
        $this->submit('manage.php', 'logout.php', []);

        $this->signIn('student@example.org');
        $mine = $this->get('resources.php');
        $this->assertSeeText('Not accepted', $mine);
        $this->assertSeeText('Please remove the scanned textbook pages.', $mine);
    }

    /* ---------- reps for every organization ---------- */

    public function test_every_organization_has_a_rep_who_only_reviews_their_own_resources(): void
    {
        $this->signIn('riverside@example.org');
        $form = $this->get('resource-upload.php');
        $this->assertSeeText('Riverside Campus', $form);
        $this->assertSeeText('Open Design Studio', $form);

        $title = 'Figma shortcuts cheat sheet ' . uniqid();
        $upload = ['organization_id' => (string) $this->idOf('organizations', 'Open Design Studio'), 'declared_name' => 'Kwame Darko'] + $this->uploadForm($title);
        $this->submit('resource-upload.php', 'resource-upload.php', $upload);
        $resourceId = $this->idOf('resources', $title);
        $this->submit('resources.php', 'logout.php', []);

        $this->signIn('rep@example.org'); // Code & Circuits: not theirs
        $this->assertDontSeeText($title, $this->get('manage.php'));
        $this->assertSame(404, $this->submit('manage.php', 'manage-resource.php', ['id' => $resourceId, 'decision' => 'publish'], false)['status']);
        $this->submit('manage.php', 'logout.php', []);

        $this->signIn('rep9@example.org'); // Open Design Studio's rep
        $this->assertSeeText($title, $this->get('manage.php'));
        $this->assertSeeText('is now public', $this->decide($resourceId, 'publish'));
    }

    /* ---------- the background scan worker ---------- */

    public function test_the_worker_flags_privacy_and_copyright_problems_for_the_rep(): void
    {
        $risky = 'Risky upload ' . uniqid();
        $clean = 'Clean upload ' . uniqid();
        $text = "Class list: esi@example.org, kofi@example.org, ama@example.org\n© 2022 Pearson Education. ISBN 978-0-13-468599-1\nANSWER KEY";

        $this->signIn('student@example.org');
        $this->submit('resource-upload.php', 'resource-upload.php', $this->uploadForm($risky, $text, 'notes.txt', 'text/plain'));
        $this->submit('resource-upload.php', 'resource-upload.php', $this->uploadForm($clean, "My own summary of recursion and base cases.\n", 'clean.txt', 'text/plain'));
        $this->submit('resources.php', 'logout.php', []);

        // Before the worker runs, the rep sees that the check is still in progress.
        $this->signIn('rep@example.org');
        $this->assertSeeText('check in progress', $this->get('manage.php'));

        $output = $this->runScanWorker();
        $this->assertStringContainsString('critical', $output);

        $manage = $this->get('manage.php');
        $this->assertSeeText('Contains 3 email addresses', $manage);
        $this->assertSeeText('Looks like pages from a published book (Pearson, ISBN)', $manage);
        $this->assertSeeText('Looks like instructor or assessment material', $manage);
        $this->assertSeeText('Automatic check found no privacy or copyright issues', $manage);
        $this->assertStringContainsString('serious issue(s). Publish anyway?', $manage['body']);
        $this->assertStringNotContainsString('esi@example.org', $manage['body']); // the rep's page does not repeat the personal data
    }

    public function test_the_worker_spots_the_same_file_from_a_different_student(): void
    {
        $content = "Shared lab report on pendulum motion " . uniqid() . "\n";
        $this->signIn('student@example.org');
        $this->submit('resource-upload.php', 'resource-upload.php', $this->uploadForm('Original report ' . uniqid(), $content, 'report.txt', 'text/plain'));
        $this->submit('resources.php', 'logout.php', []);

        $this->signIn('riverside@example.org');
        $this->submit('resource-upload.php', 'resource-upload.php', ['declared_name' => 'Kwame Darko'] + $this->uploadForm('Copied report ' . uniqid(), $content, 'report.txt', 'text/plain'));
        $this->submit('resources.php', 'logout.php', []);

        $this->runScanWorker();
        $this->signIn('rep@example.org');
        $this->assertSeeText('Identical to a file another student already uploaded', $this->get('manage.php'));
    }
}
