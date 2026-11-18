<?php

/** What a signed-in student does: interests, recommendations, saving, calendar. */
final class StudentJourneyTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('student@example.org');
        // Start every test from a clean profile.
        $this->submit('preferences.php', 'preferences.php', ['action' => 'reset']);
    }

    public function test_choosing_interests_produces_explained_recommendations(): void
    {
        $before = $this->get('dashboard.php');
        $this->assertSeeText('No interests selected yet', $before);

        $after = $this->submit('dashboard.php', 'preferences.php', ['action' => 'interests', 'interests' => ['research'], 'return' => '/dashboard.php']);
        $this->assertStringContainsString('data-testid="recommendation"', $after['body']);
        $this->assertSeeText('Matches your interests: Research', $after);
    }

    public function test_saving_an_opportunity_shows_it_on_saved_and_dashboard(): void
    {
        $id = $this->idOf('opportunities', 'Volta STEM Undergraduate Scholarship');
        $this->submit("opportunity.php?id=$id", 'toggle.php', ['kind' => 'opportunity', 'id' => $id, 'return' => "/opportunity.php?id=$id"]);

        $saved = $this->get('saved.php');
        $this->assertSeeText('Volta STEM Undergraduate Scholarship', $saved);
        $this->assertSeeText('Due in 8–14 days', $saved);

        $this->assertSeeText('Volta STEM Undergraduate Scholarship', $this->get('dashboard.php'));

        // Pressing the button again removes it.
        $this->submit('saved.php', 'toggle.php', ['kind' => 'opportunity', 'id' => $id, 'return' => '/saved.php']);
        $this->assertSeeText('No saved opportunities', $this->get('saved.php'));
    }

    public function test_the_calendar_detects_clashing_events(): void
    {
        foreach (['Pitch Clinic: Tell Your Story in 3 Minutes', 'Paper Reading Group: Machine Learning for Health'] as $title) {
            $id = $this->idOf('events', $title);
            $this->submit("event.php?id=$id", 'toggle.php', ['kind' => 'event', 'id' => $id, 'return' => '/calendar.php']);
        }
        $calendar = $this->get('calendar.php');
        $this->assertSeeText('1 schedule clash detected', $calendar);
        $this->assertSeeText('Clashes with another saved event', $calendar);

        // A broken ?month= value falls back to the current month instead of failing.
        $this->assertSame(200, $this->get('calendar.php?month=not-a-month')['status']);
    }

    public function test_calendar_export_is_a_valid_ics_file(): void
    {
        $id = $this->idOf('events', 'Intro to Web Development Workshop');
        $this->submit("event.php?id=$id", 'toggle.php', ['kind' => 'event', 'id' => $id, 'return' => '/saved.php']);

        $file = $this->get('calendar-export.php');
        $this->assertStringContainsString('Content-Type: text/calendar', $file['headers']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $file['body']);
        $this->assertStringContainsString('SUMMARY:Intro to Web Development Workshop', $file['body']);
    }

    public function test_following_an_organization(): void
    {
        $id = $this->idOf('organizations', 'Robotics & Automation Team');
        $this->submit("organization.php?id=$id", 'toggle.php', ['kind' => 'organization', 'id' => $id, 'return' => "/organization.php?id=$id"]);

        $preferences = $this->get('preferences.php');
        $this->assertSeeText('Robotics & Automation Team', $preferences);
        $this->assertStringContainsString('aria-pressed="true"', $preferences['body']);
    }

    public function test_students_cannot_open_rep_or_admin_pages(): void
    {
        $this->assertSame(403, $this->get('manage.php')['status']);
        $this->assertSame(403, $this->get('admin.php')['status']);
    }
}
