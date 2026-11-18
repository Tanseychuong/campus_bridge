<?php

/** Creating accounts, signing in and out, and request protection. */
final class AccountTest extends WebTestCase
{
    public function test_registration_shows_errors_for_bad_input(): void
    {
        $response = $this->post('api/register', [
            'name' => 'A', 'email' => 'not-an-email', 'password' => 'short', 'password_confirm' => 'different',
        ]);
        $this->assertSame(422, $response['status']);
        $this->assertSame('Please fix 5 fields below.', $response['json']['error']);
        $this->assertSame(['name', 'email', 'password', 'password_confirm', 'campus_id'], array_keys($response['json']['errors']));
    }

    public function test_a_student_can_register_and_is_signed_in(): void
    {
        $email = 'new-' . uniqid() . '@example.org';
        $response = $this->post('api/register', [
            'name' => 'Yaw Boateng', 'email' => $email, 'password' => 'a-long-password', 'password_confirm' => 'a-long-password', 'campus_id' => '1',
        ]);
        $this->assertSame(201, $response['status']);
        $this->assertSame('student', $response['json']['user']['role']);
        $this->assertSame('Yaw Boateng', $this->get('api/session')['json']['user']['name']);
        $this->assertArrayNotHasKey('password_hash', $response['json']['user']); // the hash never leaves the server

        // The same email cannot be used twice.
        $this->signOut();
        $again = $this->post('api/register', [
            'name' => 'Someone', 'email' => $email, 'password' => 'a-long-password', 'password_confirm' => 'a-long-password', 'campus_id' => '1',
        ]);
        $this->assertSeeText('An account with this email already exists', $again);
    }

    public function test_wrong_password_shows_a_neutral_message(): void
    {
        $response = $this->signIn('student@example.org', 'wrong-password');
        $this->assertSame(422, $response['status']);
        $this->assertSame('That email and password do not match an account.', $response['json']['error']);
    }

    public function test_demo_student_can_sign_in_and_out(): void
    {
        $response = $this->signIn('student@example.org');
        $this->assertSame('Welcome back, Ama Mensah (demo student)!', $response['json']['message']);
        $this->assertSame(200, $this->get('api/dashboard')['status']);

        $this->signOut();
        $this->assertNull($this->get('api/session')['json']['user']);
        $this->assertSame(401, $this->get('api/dashboard')['status']);
    }

    public function test_requests_without_a_valid_csrf_token_are_rejected(): void
    {
        $this->signIn('student@example.org');
        $response = $this->request('POST', 'api/toggle', ['kind' => 'opportunity', 'id' => 1], 'forged');
        $this->assertSame(400, $response['status']);
    }

    public function test_sign_out_only_accepts_post(): void
    {
        $response = $this->get('api/logout');
        $this->assertSame(405, $response['status']);
        $this->assertStringContainsString('Allow: POST', $response['headers']);
    }
}
