<?php

/** API addresses anyone can call without signing in. */
final class PublicPagesTest extends WebTestCase
{
    public function test_every_public_endpoint_answers_with_json(): void
    {
        foreach (['api/session', 'api/meta', 'api/home', 'api/explore', 'api/events', 'api/organizations', 'api/resources'] as $path) {
            $response = $this->get($path);
            $this->assertSame(200, $response['status'], "$path should answer");
            $this->assertStringContainsString('Content-Type: application/json', $response['headers']);
            $this->assertIsArray($response['json'], "$path should return JSON");
        }
        $this->assertCount(3, $this->get('api/home')['json']['stats']);
    }

    public function test_search_finds_items_across_types_and_tolerates_typos(): void
    {
        $response = $this->get('api/explore?q=internship');
        $this->assertContains('Software Engineering Internship — Kente Digital', $this->titlesIn($response, 'results'));

        $typo = $this->get('api/explore?q=scholarshp');
        $this->assertContains('Volta STEM Undergraduate Scholarship', $this->titlesIn($typo, 'results'));

        $this->assertSame([], $this->get('api/explore?q=zzzqqq')['json']['results']);
    }

    public function test_filters_change_the_results(): void
    {
        $titles = $this->titlesIn($this->get('api/explore?type=opportunity&mode=remote'), 'results');
        $this->assertContains('Volta STEM Undergraduate Scholarship', $titles);
        $this->assertNotContains('Software Engineering Internship — Kente Digital', $titles); // in person
    }

    public function test_bad_parameters_do_not_break_anything(): void
    {
        $explore = $this->get('api/explore?type=%3Cscript%3E&sort=drop&within=999');
        $this->assertSame(200, $explore['status']);
        $this->assertSame(['all', 'relevance', 'any'], [$explore['json']['filters']['type'], $explore['json']['filters']['sort'], $explore['json']['filters']['within']]);
        $this->assertSame('', $this->get('api/events?category=nonsense')['json']['category']);
        $this->assertSame(404, $this->get('api/events/-5')['status']);
        $this->assertSame(404, $this->get('api/no-such-address')['status']);
    }

    public function test_html_in_answers_is_encoded(): void
    {
        $response = $this->get('api/explore?q=' . urlencode('<script>alert(1)</script>'));
        $this->assertStringNotContainsString('<script>', $response['body']);
        $this->assertSame('<script>alert(1)</script>', $response['json']['filters']['q']); // still the same text once decoded
    }

    public function test_detail_endpoints_return_their_item(): void
    {
        $opportunity = $this->get('api/opportunities/' . $this->idOf('opportunities', 'Volta STEM Undergraduate Scholarship'))['json'];
        $this->assertNotEmpty($opportunity['opportunity']['eligibility']);
        $this->assertStringStartsWith('https://', $opportunity['opportunity']['source']['url']);

        $event = $this->get('api/events/' . $this->idOf('events', 'Intro to Web Development Workshop'))['json'];
        $this->assertSame('Engineering Lab 2', $event['event']['venue']);

        $organization = $this->get('api/organizations/' . $this->idOf('organizations', 'Code & Circuits Society'))['json'];
        $this->assertNotSame('', $organization['organization']['membership']);
    }

    public function test_missing_and_unpublished_items_return_404(): void
    {
        $this->assertSame(404, $this->get('api/opportunities/99999')['status']);
        $this->assertSame(404, $this->get('api/opportunities/abc')['status']);
        $pendingId = $this->idOf('opportunities', 'Cloud Skills Certification Scholarship');
        $this->assertSame(404, $this->get("api/opportunities/$pendingId")['status']);
    }

    public function test_security_headers_are_sent(): void
    {
        $headers = $this->get('api/session')['headers'];
        $this->assertStringContainsString('X-Frame-Options: DENY', $headers);
        $this->assertStringContainsString('X-Content-Type-Options: nosniff', $headers);
        $this->assertStringContainsString("Content-Security-Policy: default-src 'none'", $headers);
        $this->assertMatchesRegularExpression('/Set-Cookie: campusbridge_session=[^;]+;.*HttpOnly/i', $headers);
    }

    public function test_guests_get_401_for_personal_endpoints(): void
    {
        foreach (['api/dashboard', 'api/saved', 'api/calendar', 'api/calendar.ics', 'api/preferences', 'api/manage', 'api/admin'] as $path) {
            $response = $this->get($path);
            $this->assertSame(401, $response['status'], $path);
            $this->assertSame('Please sign in to continue.', $response['json']['error']);
        }
    }
}
