<?php

/** The profile page: account details, activity, and changing your name and password. */
final class ProfileTest extends WebTestCase
{
    public function test_guests_have_no_profile(): void
    {
        $this->assertSame(401, $this->get('api/profile')['status']);
    }

    public function test_the_profile_shows_the_account_and_activity(): void
    {
        $this->signIn('student@example.org');
        $this->post('api/toggle', ['kind' => 'opportunity', 'id' => 1]); // save something

        $profile = $this->get('api/profile');
        $this->assertSame(200, $profile['status']);
        $account = $profile['json']['account'];
        $this->assertSame('student@example.org', $account['email']);
        $this->assertSame('Student', $account['role_label']);
        $this->assertSame('Hillside Campus', $account['campus_name']);
        $this->assertSame('Active', $account['status_label']);
        $this->assertSame(1, $profile['json']['stats'][0]['value']); // saved opportunities
        $this->assertNull($profile['json']['role']); // students have no role shortcuts
        $this->assertStringNotContainsString('password_hash', $profile['body']);
    }

    public function test_reps_see_what_they_look_after(): void
    {
        $this->signIn('rep@example.org');
        $this->assertSame('You manage Code & Circuits Society', $this->get('api/profile')['json']['role']['title']);
        $this->signOut();

        $this->signIn('campus@example.org');
        $role = $this->get('api/profile')['json']['role'];
        $this->assertSame('You approve sign-ups for Hillside Campus', $role['title']);
        $this->assertGreaterThanOrEqual(0, $role['waiting']);
    }

    /** A real 1×1 PNG, saved to a temporary file for upload. */
    private function picture(?string $content = null, string $name = 'me.png', string $mime = 'image/png'): CURLFile
    {
        $content ??= base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = tempnam(sys_get_temp_dir(), 'cb_pic_');
        file_put_contents($file, $content);
        return new CURLFile($file, $mime, $name);
    }

    public function test_a_user_uploads_and_removes_profile_and_cover_pictures(): void
    {
        $this->assertSame(401, $this->get('api/profile/avatar')['status']);
        $this->signIn('student@example.org');
        $this->assertSame(404, $this->get('api/profile/avatar')['status']); // none yet

        // Pictures only: an SVG (which could contain a script) or a fake PNG is refused.
        $svg = $this->post('api/profile/avatar', ['image' => $this->picture('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'me.svg', 'image/svg+xml')]);
        $this->assertSame(422, $svg['status']);
        $this->assertSame(422, $this->post('api/profile/cover', ['image' => $this->picture('not really a png')])['status']);

        foreach (['avatar', 'cover'] as $kind) {
            $uploaded = $this->post("api/profile/$kind", ['image' => $this->picture()]);
            $this->assertSame(200, $uploaded['status'], $uploaded['body']);
            $this->assertStringStartsWith("/api/profile/$kind?v=", $uploaded['json']['user']["{$kind}_url"]);

            $image = $this->get("api/profile/$kind");
            $this->assertSame(200, $image['status']);
            $this->assertStringContainsString('Content-Type: image/png', $image['headers']);
            $this->assertStringContainsString('X-Content-Type-Options: nosniff', $image['headers']);
        }
        $this->assertNotNull($this->get('api/session')['json']['user']['avatar_url']);

        $removed = $this->request('DELETE', 'api/profile/avatar', [], $this->csrfToken());
        $this->assertNull($removed['json']['user']['avatar_url']);
        $this->assertSame(404, $this->get('api/profile/avatar')['status']);
        $this->request('DELETE', 'api/profile/cover', [], $this->csrfToken()); // leave the demo account as it was
    }

    public function test_a_user_changes_their_name_and_password(): void
    {
        $this->signIn('riverside@example.org');

        $this->assertSame(422, $this->request('PUT', 'api/profile', ['name' => 'K'], $this->csrfToken())['status']);
        $renamed = $this->request('PUT', 'api/profile', ['name' => 'Kwame A. Darko'], $this->csrfToken());
        $this->assertSame('Kwame A. Darko', $renamed['json']['user']['name']);

        // The current password is required, and the new one follows the sign-up rules.
        $wrong = $this->request('PUT', 'api/profile/password', [
            'current_password' => 'not-my-password', 'password' => 'short', 'password_confirm' => 'other',
        ], $this->csrfToken());
        $this->assertSame(['current_password', 'password', 'password_confirm'], array_keys($wrong['json']['errors']));

        $changed = $this->request('PUT', 'api/profile/password', [
            'current_password' => 'CampusBridge2026!', 'password' => 'a-brand-new-password', 'password_confirm' => 'a-brand-new-password',
        ], $this->csrfToken());
        $this->assertSeeText('Your password was changed', $changed);

        $this->signOut();
        $this->assertSame(422, $this->signIn('riverside@example.org')['status']); // old password no longer works
        $this->assertSame(200, $this->signIn('riverside@example.org', 'a-brand-new-password')['status']);
    }
}
