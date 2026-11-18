<?php

/** What a signed-in student does: interests, recommendations, saving, calendar. */
final class StudentJourneyTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('student@example.org');
        // Start every test from a clean profile.
        $this->post('api/preferences/reset');
    }

    private function toggle(string $kind, int $id): array
    {
        return $this->post('api/toggle', ['kind' => $kind, 'id' => $id]);
    }

    public function test_choosing_interests_produces_explained_recommendations(): void
    {
        $before = $this->get('api/dashboard')['json'];
        $this->assertFalse($before['has_interests']);
        $this->assertSame([], $before['recommendations']);

        $this->request('PUT', 'api/preferences/interests', ['interests' => ['research', 'not-a-real-interest']]);
        $this->assertSame(['research'], $this->get('api/preferences')['json']['interests']);

        $after = $this->get('api/dashboard');
        $this->assertNotEmpty($after['json']['recommendations']);
        $this->assertSeeText('Matches your interests: Research', $after);
    }

    public function test_saving_an_opportunity_shows_it_on_saved_and_dashboard(): void
    {
        $id = $this->idOf('opportunities', 'Volta STEM Undergraduate Scholarship');
        $saved = $this->toggle('opportunity', $id);
        $this->assertTrue($saved['json']['is_on']);
        $this->assertSame('Saved: Volta STEM Undergraduate Scholarship', $saved['json']['message']);
        $this->assertTrue($this->get("api/opportunities/$id")['json']['opportunity']['is_saved']);

        $savedPage = $this->get('api/saved');
        $this->assertSeeText('Volta STEM Undergraduate Scholarship', $savedPage);
        $this->assertSeeText('Due in 8–14 days', $savedPage);
        $this->assertSeeText('Volta STEM Undergraduate Scholarship', $this->get('api/dashboard'));

        // Pressing the button again removes it.
        $this->assertFalse($this->toggle('opportunity', $id)['json']['is_on']);
        $this->assertSame([], $this->get('api/saved')['json']['deadline_groups']);
    }

    public function test_the_calendar_detects_clashing_events(): void
    {
        foreach (['Pitch Clinic: Tell Your Story in 3 Minutes', 'Paper Reading Group: Machine Learning for Health'] as $title) {
            $this->toggle('event', $this->idOf('events', $title));
        }
        $calendar = $this->get('api/calendar')['json'];
        $this->assertCount(1, $calendar['clashes']);

        $clashingEntries = [];
        foreach ($calendar['weeks'] as $week) {
            foreach ($week as $day) {
                foreach ($day['entries'] as $entry) {
                    if ($entry['clash']) {
                        $clashingEntries[] = $entry['title'];
                    }
                }
            }
        }
        // The clash may fall in next month; either way both events are marked once their month is shown.
        if ($clashingEntries === []) {
            $calendar = $this->get('api/calendar?month=' . $calendar['next'])['json'];
            $clashingEntries = array_merge(...array_map(fn ($day) => array_column(array_filter($day['entries'], fn ($e) => $e['clash']), 'title'), array_merge(...$calendar['weeks'])));
        }
        $this->assertCount(2, $clashingEntries);

        // A broken ?month= value falls back to the current month instead of failing.
        $this->assertSame(200, $this->get('api/calendar?month=not-a-month')['status']);
    }

    public function test_calendar_export_is_a_valid_ics_file(): void
    {
        $this->toggle('event', $this->idOf('events', 'Intro to Web Development Workshop'));

        $file = $this->get('api/calendar.ics');
        $this->assertStringContainsString('Content-Type: text/calendar', $file['headers']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $file['body']);
        $this->assertStringContainsString('SUMMARY:Intro to Web Development Workshop', $file['body']);
    }

    public function test_following_an_organization(): void
    {
        $id = $this->idOf('organizations', 'Robotics & Automation Team');
        $this->toggle('organization', $id);

        $this->assertContains('Robotics & Automation Team', $this->titlesIn($this->get('api/preferences'), 'followed'));
        $this->assertTrue($this->get("api/organizations/$id")['json']['organization']['is_following']);
    }

    public function test_students_cannot_open_rep_or_admin_areas(): void
    {
        $this->assertSame(403, $this->get('api/manage')['status']);
        $this->assertSame(403, $this->get('api/admin')['status']);
    }
}
