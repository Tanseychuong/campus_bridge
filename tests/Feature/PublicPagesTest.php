<?php

/** Pages anyone can visit without signing in. */
final class PublicPagesTest extends WebTestCase
{
    public function test_every_public_page_loads(): void
    {
        $pages = [
            '' => 'Every campus opportunity',
            'explore.php' => 'Explore',
            'events.php' => 'Campus events',
            'organizations.php' => 'Student organizations',
            'about.php' => 'How recommendations work',
            'login.php' => 'Sign in',
            'register.php' => 'Create your account',
        ];
        foreach ($pages as $path => $expectedText) {
            $response = $this->get($path);
            $this->assertSame(200, $response['status'], "$path should load");
            $this->assertSeeText($expectedText, $response);
        }
    }

    public function test_search_finds_items_across_types_and_tolerates_typos(): void
    {
        $response = $this->get('explore.php?q=internship');
        $this->assertSeeText('Software Engineering Internship — Kente Digital', $response);

        $typo = $this->get('explore.php?q=scholarshp');
        $this->assertSeeText('Volta STEM Undergraduate Scholarship', $typo);

        $empty = $this->get('explore.php?q=zzzqqq');
        $this->assertSeeText('No matches found', $empty);
    }

    public function test_filters_change_the_results(): void
    {
        $response = $this->get('explore.php?type=opportunity&mode=remote');
        $this->assertSeeText('Volta STEM Undergraduate Scholarship', $response);
        $this->assertDontSeeText('Software Engineering Internship — Kente Digital', $response); // in person
    }

    public function test_bad_url_parameters_do_not_break_pages(): void
    {
        $this->assertSame(200, $this->get('explore.php?type=%3Cscript%3E&sort=drop&within=999')['status']);
        $this->assertSame(200, $this->get('events.php?category=nonsense')['status']);
        $this->assertSame(404, $this->get('event.php?id=-5')['status']);
    }

    public function test_user_input_is_escaped_in_the_page(): void
    {
        $response = $this->get('explore.php?q=' . urlencode('<script>alert(1)</script>'));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response['body']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response['body']);
    }

    public function test_detail_pages_show_their_item(): void
    {
        $opportunity = $this->get('opportunity.php?id=' . $this->idOf('opportunities', 'Volta STEM Undergraduate Scholarship'));
        $this->assertSeeText('Eligibility', $opportunity);
        $this->assertStringContainsString('rel="noopener noreferrer"', $opportunity['body']);

        $event = $this->get('event.php?id=' . $this->idOf('events', 'Intro to Web Development Workshop'));
        $this->assertSeeText('Engineering Lab 2', $event);

        $organization = $this->get('organization.php?id=' . $this->idOf('organizations', 'Code & Circuits Society'));
        $this->assertSeeText('How to join', $organization);
    }

    public function test_missing_and_unpublished_items_return_404(): void
    {
        $this->assertSame(404, $this->get('opportunity.php?id=99999')['status']);
        $this->assertSame(404, $this->get('opportunity.php?id=abc')['status']);
        $pendingId = $this->idOf('opportunities', 'Cloud Skills Certification Scholarship');
        $this->assertSame(404, $this->get("opportunity.php?id=$pendingId")['status']);
    }

    public function test_security_headers_are_sent(): void
    {
        $headers = $this->get('')['headers'];
        $this->assertStringContainsString('X-Frame-Options: DENY', $headers);
        $this->assertStringContainsString('X-Content-Type-Options: nosniff', $headers);
        $this->assertStringContainsString('Content-Security-Policy:', $headers);
        $this->assertMatchesRegularExpression('/Set-Cookie: campusbridge_session=[^;]+;.*HttpOnly/i', $headers);
    }

    public function test_guests_are_sent_to_sign_in_for_personal_pages(): void
    {
        foreach (['dashboard.php', 'saved.php', 'calendar.php', 'preferences.php', 'manage.php', 'admin.php'] as $page) {
            $response = $this->get($page);
            $this->assertSame(302, $response['status'], $page);
            $this->assertStringContainsString('login.php', $response['location']);
        }
    }
}
