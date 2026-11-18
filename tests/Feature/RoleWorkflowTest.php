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
            'capacity' => '25', 'registration' => 'rsvp', 'interests' => ['engineering'],
        ];
    }

    public function test_the_event_form_rejects_invalid_input(): void
    {
        $this->signIn('rep@example.org');
        $response = $this->submit('manage-event.php', 'manage-event.php', ['title' => 'Hi', 'end_time' => '10:00', 'start_time' => '12:00', 'interests' => []] + $this->validEventForm('x'));
        $this->assertSeeText('Title must be at least 5 characters.', $response);
        $this->assertSeeText('The end time must be after the start time.', $response);
        $this->assertSeeText('Choose at least one interest tag.', $response);
    }

    public function test_a_new_event_is_pending_until_an_admin_approves_it(): void
    {
        $title = 'Intro to Arduino Night ' . uniqid();

        // 1. The rep submits an event.
        $this->signIn('rep@example.org');
        $manage = $this->submit('manage-event.php', 'manage-event.php', $this->validEventForm($title));
        $this->assertSeeText('submitted for admin review', $manage);
        $this->assertSeeText('Pending review', $manage);

        // 2. It is not public yet.
        $eventId = $this->idOf('events', $title);
        $this->assertSame(404, $this->get("event.php?id=$eventId")['status']);

        // 3. An admin sees it in the queue and approves it.
        $this->submit('manage.php', 'logout.php', []);
        $this->signIn('admin@example.org');
        $admin = $this->get('admin.php');
        $this->assertSeeText($title, $admin);
        $this->assertSeeText('Mentions payment or fees', $admin); // the suspicious sample listing is flagged

        $this->submit('admin.php', 'admin-review.php', ['type' => 'event', 'id' => $eventId, 'decision' => 'approve', 'note' => '']);

        // 4. Now everyone can see it.
        $this->assertSame(200, $this->get("event.php?id=$eventId")['status']);
    }

    public function test_rejecting_requires_a_reason_and_the_rep_sees_it(): void
    {
        $title = 'Event To Reject ' . uniqid();
        $this->signIn('rep@example.org');
        $this->submit('manage-event.php', 'manage-event.php', $this->validEventForm($title));
        $eventId = $this->idOf('events', $title);
        $this->submit('manage.php', 'logout.php', []);

        $this->signIn('admin@example.org');
        $noReason = $this->submit('admin.php', 'admin-review.php', ['type' => 'event', 'id' => $eventId, 'decision' => 'reject', 'note' => '']);
        $this->assertSeeText('Please write a short reason', $noReason);

        $this->submit('admin.php', 'admin-review.php', ['type' => 'event', 'id' => $eventId, 'decision' => 'reject', 'note' => 'Please add the room number.']);
        $this->submit('admin.php', 'logout.php', []);

        $this->signIn('rep@example.org');
        $manage = $this->get('manage.php');
        $this->assertSeeText('Rejected', $manage);
        $this->assertSeeText('Please add the room number.', $manage);
    }

    public function test_a_rep_cannot_edit_or_delete_another_organizations_event(): void
    {
        $this->signIn('rep@example.org'); // rep for Code & Circuits Society
        $otherEventId = $this->idOf('events', 'Pitch Clinic: Tell Your Story in 3 Minutes'); // Venture Lab's event

        $this->assertSame(404, $this->get("manage-event.php?id=$otherEventId")['status']);
        $delete = $this->submit('manage.php', 'manage-event-delete.php', ['id' => $otherEventId], false);
        $this->assertSame(404, $delete['status']);
        $this->assertSame(200, $this->get("event.php?id=$otherEventId")['status']); // still there
    }

    public function test_a_rep_can_edit_and_delete_their_own_event(): void
    {
        $title = 'Event To Edit ' . uniqid();
        $this->signIn('rep@example.org');
        $this->submit('manage-event.php', 'manage-event.php', $this->validEventForm($title));
        $eventId = $this->idOf('events', $title);

        $edited = $this->submit("manage-event.php?id=$eventId", "manage-event.php?id=$eventId", ['id' => $eventId] + $this->validEventForm($title . ' (updated)'));
        $this->assertSeeText('updated and sent for review again', $edited);

        $afterDelete = $this->submit('manage.php', 'manage-event-delete.php', ['id' => $eventId]);
        $this->assertSeeText('was deleted', $afterDelete);
    }

    public function test_an_org_rep_cannot_review_listings(): void
    {
        $this->signIn('rep@example.org');
        $response = $this->submit('manage.php', 'admin-review.php', ['type' => 'event', 'id' => 1, 'decision' => 'approve'], false);
        $this->assertSame(403, $response['status']);
    }
}
