<?php

/** Organization reps submit events; administrators review them. */
final class RoleWorkflowTest extends WebTestCase
{
    private function validEventForm(string $title): array
    {
        return [
            'title' => $title, 'category' => 'tech', 'summary' => 'Learn Arduino basics in one evening.',
            'description' => 'A beginner-friendly session on microcontrollers, sensors and LEDs.', 'venue' => 'Fabrication Lab',
            'date' => (new DateTimeImmutable('+5 days'))->format('Y-m-d'), 'start_time' => '18:00', 'end_time' => '20:00',
            'capacity' => '25', 'registration' => 'rsvp', 'visibility' => 'public', 'interests' => ['engineering'],
        ];
    }

    private function createEvent(string $title): int
    {
        $response = $this->post('api/manage/events', $this->validEventForm($title));
        $this->assertSame(201, $response['status'], $response['body']);
        return $response['json']['id'];
    }

    private function eventStatus(string $title): ?array
    {
        foreach ($this->get('api/manage')['json']['events'] as $event) {
            if ($event['title'] === $title) {
                return $event;
            }
        }
        return null;
    }

    public function test_the_event_form_rejects_invalid_input(): void
    {
        $this->signIn('rep@example.org');
        $response = $this->post('api/manage/events', ['title' => 'Hi', 'end_time' => '10:00', 'start_time' => '12:00', 'interests' => []] + $this->validEventForm('x'));
        $this->assertSame(422, $response['status']);
        $this->assertSame('Title must be at least 5 characters.', $response['json']['errors']['title']);
        $this->assertSame('The end time must be after the start time.', $response['json']['errors']['end_time']);
        $this->assertSame('Choose at least one interest tag.', $response['json']['errors']['interests']);
    }

    public function test_a_new_event_is_pending_until_an_admin_approves_it(): void
    {
        $title = 'Intro to Arduino Night ' . uniqid();

        // 1. The rep submits an event.
        $this->signIn('rep@example.org');
        $response = $this->post('api/manage/events', $this->validEventForm($title));
        $this->assertSeeText('submitted for admin review', $response);
        $this->assertSame('Pending review', $this->eventStatus($title)['status_label']);

        // 2. It is not public yet.
        $eventId = $response['json']['id'];
        $this->assertSame(404, $this->get("api/events/$eventId")['status']);

        // 3. An admin sees it in the queue and approves it.
        $this->signOut();
        $this->signIn('admin@example.org');
        $admin = $this->get('api/admin');
        $this->assertSeeText($title, $admin);
        $this->assertSeeText('Mentions payment or fees', $admin); // the suspicious sample listing is flagged

        $this->post('api/admin/review', ['type' => 'event', 'id' => $eventId, 'decision' => 'approve', 'note' => '']);

        // 4. Now everyone can see it.
        $this->signOut();
        $this->assertSame(200, $this->get("api/events/$eventId")['status']);
    }

    public function test_rejecting_requires_a_reason_and_the_rep_sees_it(): void
    {
        $title = 'Event To Reject ' . uniqid();
        $this->signIn('rep@example.org');
        $eventId = $this->createEvent($title);
        $this->signOut();

        $this->signIn('admin@example.org');
        $noReason = $this->post('api/admin/review', ['type' => 'event', 'id' => $eventId, 'decision' => 'reject', 'note' => '']);
        $this->assertSame(422, $noReason['status']);
        $this->assertSeeText('Please write a short reason', $noReason);

        $this->post('api/admin/review', ['type' => 'event', 'id' => $eventId, 'decision' => 'reject', 'note' => 'Please add the room number.']);
        $this->signOut();

        $this->signIn('rep@example.org');
        $event = $this->eventStatus($title);
        $this->assertSame('Rejected', $event['status_label']);
        $this->assertSame('Please add the room number.', $event['review_note']);
    }

    public function test_a_rep_cannot_edit_or_delete_another_organizations_event(): void
    {
        $this->signIn('rep@example.org'); // rep for Code & Circuits Society
        $otherEventId = $this->idOf('events', 'Pitch Clinic: Tell Your Story in 3 Minutes'); // Venture Lab's event

        $this->assertSame(404, $this->get("api/manage/events/$otherEventId")['status']);
        $this->assertSame(404, $this->request('PUT', "api/manage/events/$otherEventId", $this->validEventForm('Taken over'))['status']);
        $this->assertSame(404, $this->request('DELETE', "api/manage/events/$otherEventId")['status']);
        $this->assertSame(200, $this->get("api/events/$otherEventId")['status']); // still there
    }

    public function test_a_rep_can_edit_and_delete_their_own_event(): void
    {
        $title = 'Event To Edit ' . uniqid();
        $this->signIn('rep@example.org');
        $eventId = $this->createEvent($title);
        $this->assertSame($title, $this->get("api/manage/events/$eventId")['json']['values']['title']);

        $edited = $this->request('PUT', "api/manage/events/$eventId", $this->validEventForm($title . ' (updated)'));
        $this->assertSeeText('updated and sent for review again', $edited);

        $deleted = $this->request('DELETE', "api/manage/events/$eventId");
        $this->assertSeeText('was deleted', $deleted);
        $this->assertNull($this->eventStatus($title . ' (updated)'));
    }

    public function test_an_org_rep_cannot_review_listings(): void
    {
        $this->signIn('rep@example.org');
        $response = $this->post('api/admin/review', ['type' => 'event', 'id' => 1, 'decision' => 'approve']);
        $this->assertSame(403, $response['status']);
    }
}
