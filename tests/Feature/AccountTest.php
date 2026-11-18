<?php

/** Creating accounts, signing in and out, and form protection. */
final class AccountTest extends WebTestCase
{
    public function test_registration_shows_errors_for_bad_input(): void
    {
        $response = $this->submit('register.php', 'register.php', [
            'name' => 'A', 'email' => 'not-an-email', 'password' => 'short', 'password_confirm' => 'different',
        ]);
        $this->assertSame(200, $response['status']);
        $this->assertSeeText('Please fix 5 fields below.', $response);
        $this->assertStringContainsString('aria-invalid="true"', $response['body']);
    }

    public function test_a_student_can_register_and_is_signed_in(): void
    {
        $email = 'new-' . uniqid() . '@example.org';
        $response = $this->submit('register.php', 'register.php', [
            'name' => 'Yaw Boateng', 'email' => $email, 'password' => 'a-long-password', 'password_confirm' => 'a-long-password', 'campus_id' => '1',
        ]);
        $this->assertSeeText('Your dashboard', $response);
        $this->assertSeeText('Yaw Boateng', $response);

        // The same email cannot be used twice.
        $this->submit('index.php', 'logout.php', []);
        $again = $this->submit('register.php', 'register.php', [
            'name' => 'Someone', 'email' => $email, 'password' => 'a-long-password', 'password_confirm' => 'a-long-password', 'campus_id' => '1',
        ]);
        $this->assertSeeText('An account with this email already exists', $again);
    }

    public function test_wrong_password_shows_a_neutral_message(): void
    {
        $response = $this->signIn('student@example.org', 'wrong-password');
        $this->assertSeeText('That email and password do not match an account.', $response);
    }

    public function test_demo_student_can_sign_in_and_out(): void
    {
        $dashboard = $this->signIn('student@example.org');
        $this->assertSeeText('Welcome back, Ama Mensah (demo student)!', $dashboard);

        $home = $this->submit('dashboard.php', 'logout.php', []);
        $this->assertSeeText('You have signed out.', $home);
        $this->assertSame(302, $this->get('dashboard.php')['status']);
    }

    public function test_after_sign_in_the_user_returns_to_the_page_they_wanted(): void
    {
        $this->get('saved.php'); // redirected to login.php?return=/saved.php
        $response = $this->submit('login.php?return=' . urlencode('/saved.php'), 'login.php', [
            'email' => 'student@example.org', 'password' => 'CampusBridge2026!', 'return' => '/saved.php',
        ]);
        $this->assertSeeText('Saved & deadlines', $response);
    }

    public function test_redirects_to_other_websites_are_blocked(): void
    {
        $response = $this->submit('login.php', 'login.php', [
            'email' => 'student@example.org', 'password' => 'CampusBridge2026!', 'return' => 'https://evil.example/',
        ], false);
        $this->assertStringNotContainsString('evil.example', $response['location']);
    }

    public function test_forms_without_a_valid_csrf_token_are_rejected(): void
    {
        $this->signIn('student@example.org');
        $response = $this->request('POST', 'toggle.php', ['kind' => 'opportunity', 'id' => 1, 'csrf_token' => 'forged']);
        $this->assertSame(400, $response['status']);
    }

    public function test_sign_out_only_accepts_post(): void
    {
        $this->assertSame(405, $this->get('logout.php')['status']);
    }
}
