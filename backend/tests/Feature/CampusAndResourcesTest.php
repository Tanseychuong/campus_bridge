<?php

/** Campus-only events, and the student resource + IP declaration workflow. */
final class CampusAndResourcesTest extends WebTestCase
{
    /** All event titles on the events list. */
    private function eventTitles(): array
    {
        $titles = [];
        foreach ($this->get('api/events')['json']['days'] as $day) {
            $titles = array_merge($titles, array_column($day['events'], 'title'));
        }
        return $titles;
    }

    /* ---------- campus visibility ---------- */

    public function test_campus_only_events_are_hidden_from_guests(): void
    {
        $hillsideOnly = $this->idOf('events', 'Inter-Year Football Tournament');
        $this->assertSame(404, $this->get("api/events/$hillsideOnly")['status']);
        $this->assertNotContains('Inter-Year Football Tournament', $this->eventTitles());
        $this->assertContains('Intro to Web Development Workshop', $this->eventTitles()); // public events still show
    }

    public function test_students_only_see_campus_only_events_of_their_own_campus(): void
    {
        $hillsideOnly = $this->idOf('events', 'Inter-Year Football Tournament');
        $riversideOnly = $this->idOf('events', 'Podcast Production Training');

        $this->signIn('student@example.org'); // Hillside
        $this->assertSame(200, $this->get("api/events/$hillsideOnly")['status']);
        $this->assertSame(404, $this->get("api/events/$riversideOnly")['status']);
        $this->signOut();

        $this->signIn('riverside@example.org'); // Riverside
        $this->assertSame(404, $this->get("api/events/$hillsideOnly")['status']);
        $this->assertSame(200, $this->get("api/events/$riversideOnly")['status']);
    }

    public function test_a_hidden_event_cannot_be_saved_by_another_campus(): void
    {
        $this->signIn('riverside@example.org');
        $hillsideOnly = $this->idOf('events', 'Inter-Year Football Tournament');
        $this->assertSame(404, $this->post('api/toggle', ['kind' => 'event', 'id' => $hillsideOnly])['status']);
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

    private function upload(array $form): array
    {
        return $this->post('api/resources', $form);
    }

    /** $confirm: the rep ticked "I checked the file myself" (needed when there is a breach warning). */
    private function decide(int $resourceId, string $decision, string $note = '', bool $confirm = false): array
    {
        return $this->post("api/manage/resources/$resourceId", ['decision' => $decision, 'note' => $note, 'confirm_breach' => $confirm ? 'yes' : '']);
    }

    /** The rep's view of one resource on the Manage page. */
    private function managedResource(string $title): ?array
    {
        foreach ($this->get('api/manage')['json']['resources'] as $resource) {
            if ($resource['title'] === $title) {
                return $resource;
            }
        }
        return null;
    }

    public function test_the_declaration_is_required(): void
    {
        $this->signIn('student@example.org');
        $response = $this->upload(['confirm_takedown' => '', 'declared_name' => ''] + $this->uploadForm('Incomplete declaration'));
        $this->assertSame(422, $response['status']);
        $this->assertSame('Please confirm all three statements in the declaration.', $response['json']['errors']['declaration']);
        $this->assertSame('Type your full name to sign the declaration.', $response['json']['errors']['declared_name']);
    }

    public function test_a_file_whose_content_does_not_match_its_extension_is_refused(): void
    {
        $this->signIn('student@example.org');
        $response = $this->upload($this->uploadForm('Fake PDF', '<script>alert(1)</script>'));
        $this->assertStringContainsString('does not match its .pdf extension', $response['json']['errors']['file']);
    }

    public function test_a_rep_publishes_takes_down_and_republishes_a_resource(): void
    {
        $title = 'Calculus revision notes ' . uniqid();

        // 1. A student uploads it: private and waiting.
        $this->signIn('student@example.org');
        $uploaded = $this->upload($this->uploadForm($title));
        $this->assertSame(201, $uploaded['status'], $uploaded['body']);
        $this->assertSeeText('was sent to Code & Circuits Society for review', $uploaded);
        $resourceId = $uploaded['json']['id'];
        $this->assertSame('Waiting for review', $this->get('api/resources')['json']['my_uploads'][0]['status_label']);
        $this->signOut();

        $this->assertSame(404, $this->get("api/resources/$resourceId/download")['status']);
        $this->assertNotContains($title, $this->titlesIn($this->get('api/resources'), 'resources'));

        // 2. The automatic check runs; the rep sees the declaration and publishes it.
        $this->runScanWorker();
        $this->signIn('rep@example.org');
        $this->assertNull($this->managedResource($title)['scan']['breach_warning']);
        $this->assertSame('I created all of it myself', $this->managedResource($title)['declaration']['ownership']);
        $this->decide($resourceId, 'publish');
        $this->signOut();

        $download = $this->get("api/resources/$resourceId/download");
        $this->assertSame(200, $download['status']);
        $this->assertStringContainsString('Content-Disposition: attachment', $download['headers']);
        $this->assertStringStartsWith('%PDF-1.4', $download['body']);
        $this->assertContains($title, $this->titlesIn($this->get('api/resources'), 'resources'));

        // 3. The rep takes it down, then brings it back.
        $this->signIn('rep@example.org');
        $this->decide($resourceId, 'hide');
        $this->signOut();
        $this->assertSame(404, $this->get("api/resources/$resourceId/download")['status']);

        $this->signIn('rep@example.org');
        $this->assertSeeText('is now public', $this->decide($resourceId, 'publish'));
    }

    public function test_rejecting_a_resource_needs_a_reason_and_students_cannot_review(): void
    {
        $title = 'Resource to reject ' . uniqid();
        $this->signIn('student@example.org');
        $resourceId = $this->upload($this->uploadForm($title))['json']['id'];

        $this->assertSame(403, $this->decide($resourceId, 'publish')['status']);
        $this->signOut();

        $this->signIn('rep@example.org');
        $this->assertSeeText('Please write a short reason', $this->decide($resourceId, 'reject'));
        $this->decide($resourceId, 'reject', 'Please remove the scanned textbook pages.');
        $this->signOut();

        $this->signIn('student@example.org');
        $mine = $this->get('api/resources')['json']['my_uploads'][0];
        $this->assertSame('Not accepted', $mine['status_label']);
        $this->assertSame('Please remove the scanned textbook pages.', $mine['review_note']);
    }

    /* ---------- reps for every organization ---------- */

    public function test_every_organization_has_a_rep_who_only_reviews_their_own_resources(): void
    {
        $this->signIn('riverside@example.org');
        $options = $this->get('api/resources/options');
        $this->assertSeeText('Riverside Campus', $options);
        $this->assertSeeText('Open Design Studio', $options);

        $title = 'Figma shortcuts cheat sheet ' . uniqid();
        // Its own content: the same bytes as another student's upload would (rightly) be flagged as a copy.
        $upload = ['organization_id' => (string) $this->idOf('organizations', 'Open Design Studio'), 'declared_name' => 'Kwame Darko']
            + $this->uploadForm($title, "%PDF-1.4\n% Kwame's own cheat sheet " . uniqid() . "\n");
        $resourceId = $this->upload($upload)['json']['id'];
        $this->signOut();
        $this->runScanWorker();

        $this->signIn('rep@example.org'); // Code & Circuits: not theirs
        $this->assertNull($this->managedResource($title));
        $this->assertSame(404, $this->decide($resourceId, 'publish')['status']);
        $this->signOut();

        $this->signIn('rep9@example.org'); // Open Design Studio's rep
        $this->assertNotNull($this->managedResource($title));
        $this->assertSeeText('is now public', $this->decide($resourceId, 'publish'));
    }

    /* ---------- the background scan worker ---------- */

    public function test_the_worker_flags_privacy_and_copyright_problems_for_the_rep(): void
    {
        $risky = 'Risky upload ' . uniqid();
        $clean = 'Clean upload ' . uniqid();
        $text = "Class list: esi@example.org, kofi@example.org, ama@example.org\n© 2022 Pearson Education. ISBN 978-0-13-468599-1\nANSWER KEY";

        $this->signIn('student@example.org');
        $this->upload($this->uploadForm($risky, $text, 'notes.txt', 'text/plain'));
        $this->upload($this->uploadForm($clean, "My own summary of recursion and base cases.\n", 'clean.txt', 'text/plain'));
        $this->signOut();

        // Before the worker runs, the rep sees that the check is still in progress.
        $this->signIn('rep@example.org');
        $this->assertSame('pending', $this->managedResource($risky)['scan']['state']);

        $output = $this->runScanWorker();
        $this->assertStringContainsString('critical', $output);

        $manage = $this->get('api/manage');
        $this->assertSeeText('Contains 3 email addresses', $manage);
        $this->assertSeeText('Looks like pages from a published book (Pearson, ISBN)', $manage);
        $this->assertSeeText('Looks like instructor or assessment material', $manage);
        $this->assertGreaterThan(0, $this->managedResource($risky)['scan']['critical_count']);
        $this->assertSame('clean', $this->managedResource($clean)['scan']['state']);
        $this->assertStringNotContainsString('esi@example.org', $manage['body']); // the rep's view does not repeat the personal data

        // Serious findings put a "potential rule breach" warning on the approval,
        // and publishing needs the rep to confirm they checked the file themselves.
        $this->assertStringContainsString('serious problems', $this->managedResource($risky)['scan']['breach_warning']);
        $this->assertNull($this->managedResource($clean)['scan']['breach_warning']);
        $riskyId = $this->idOf('resources', $risky);
        $refused = $this->decide($riskyId, 'publish');
        $this->assertSame(409, $refused['status']);
        $this->assertSeeText('Potential rule breach', $refused);
        $this->assertSeeText('is now public', $this->decide($riskyId, 'publish', '', true));
    }

    public function test_publishing_before_the_check_has_run_needs_confirmation(): void
    {
        $title = 'Unchecked upload ' . uniqid();
        $this->signIn('student@example.org');
        $resourceId = $this->upload($this->uploadForm($title))['json']['id'];
        $this->signOut();

        $this->signIn('rep@example.org');
        $this->assertStringContainsString('has not finished yet', $this->managedResource($title)['scan']['breach_warning']);
        $this->assertSame(409, $this->decide($resourceId, 'publish')['status']);
        $this->assertSame(200, $this->decide($resourceId, 'publish', '', true)['status']);
    }

    /* ---------- admins add and remove study materials ---------- */

    public function test_an_admin_adds_material_to_any_organization_and_removes_it(): void
    {
        // Admins may file material under any organization, even one without a rep.
        $this->signIn('admin@example.org');
        $options = $this->get('api/resources/options');
        $this->assertTrue($options['json']['publishes_directly']);

        $title = 'Official exam timetable ' . uniqid();
        $added = $this->upload(['declared_name' => 'Esi Owusu'] + $this->uploadForm($title));
        $this->assertSame(201, $added['status'], $added['body']);
        $this->assertSeeText('is now public', $added);
        $resourceId = $added['json']['id'];

        // Public straight away, and listed (with its scan state) on the Admin page.
        $this->assertContains($title, $this->titlesIn($this->get('api/resources'), 'resources'));
        $this->assertContains($title, $this->titlesIn($this->get('api/admin'), 'resources'));

        // Only admins can remove material.
        $this->signOut();
        $this->signIn('rep@example.org');
        $this->assertSame(403, $this->request('DELETE', "api/admin/resources/$resourceId", [], $this->csrfToken())['status']);
        $this->signOut();

        $this->signIn('admin@example.org');
        $removed = $this->request('DELETE', "api/admin/resources/$resourceId", [], $this->csrfToken());
        $this->assertSeeText('was removed', $removed);
        $this->assertSame(404, $this->get("api/resources/$resourceId/download")['status']);
        $this->assertNotContains($title, $this->titlesIn($this->get('api/resources'), 'resources'));
    }

    public function test_the_worker_spots_the_same_file_from_a_different_student(): void
    {
        $content = "Shared lab report on pendulum motion " . uniqid() . "\n";
        $this->signIn('student@example.org');
        $this->upload($this->uploadForm('Original report ' . uniqid(), $content, 'report.txt', 'text/plain'));
        $this->signOut();

        $this->signIn('riverside@example.org');
        $this->upload(['declared_name' => 'Kwame Darko'] + $this->uploadForm('Copied report ' . uniqid(), $content, 'report.txt', 'text/plain'));
        $this->signOut();

        $this->runScanWorker();
        $this->signIn('rep@example.org');
        $this->assertSeeText('Identical to a file another student already uploaded', $this->get('api/manage'));
    }
}
