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

    public function test_a_new_student_waits_for_their_campus_rep_before_signing_in(): void
    {
        $email = 'new-' . uniqid() . '@example.org';
        $response = $this->post('api/register', [
            'name' => 'Yaw Boateng', 'email' => $email, 'password' => 'a-long-password', 'password_confirm' => 'a-long-password', 'campus_id' => '1',
        ]);
        $this->assertSame(201, $response['status']);
        $this->assertTrue($response['json']['pending']);
        $this->assertSeeText('A campus representative for Hillside Campus will check your account', $response);
        $this->assertNull($this->get('api/session')['json']['user']); // not signed in yet

        // Right password, but not approved yet: a clear message, still not signed in.
        $blocked = $this->signIn($email, 'a-long-password');
        $this->assertSame(403, $blocked['status']);
        $this->assertSeeText('waiting for approval by your campus representative', $blocked);
        $this->assertNull($this->get('api/session')['json']['user']);

        // The Hillside campus rep approves; now the student can sign in.
        $this->signIn('campus@example.org');
        $studentId = $this->idOf('users', $email);
        $this->assertSeeText('Yaw Boateng can now sign in', $this->post("api/approvals/$studentId", ['decision' => 'approve']));
        $this->signOut();

        $signedIn = $this->signIn($email, 'a-long-password');
        $this->assertSame(200, $signedIn['status']);
        $this->assertSame('student', $signedIn['json']['user']['role']);
        $this->assertArrayNotHasKey('password_hash', $signedIn['json']['user']); // the hash never leaves the server

        // The same email cannot be used twice.
        $this->signOut();
        $again = $this->post('api/register', [
            'name' => 'Someone', 'email' => $email, 'password' => 'a-long-password', 'password_confirm' => 'a-long-password', 'campus_id' => '1',
        ]);
        $this->assertSeeText('An account with this email already exists', $again);
    }

    public function test_campus_reps_only_review_their_own_campus_and_rejections_need_a_reason(): void
    {
        $pendingId = $this->idOf('users', 'pending@example.org'); // a Hillside student from the sample data

        // Students and org reps cannot review sign-ups.
        $this->signIn('student@example.org');
        $this->assertSame(403, $this->get('api/approvals')['status']);
        $this->signOut();
        $this->signIn('rep@example.org');
        $this->assertSame(403, $this->post("api/approvals/$pendingId", ['decision' => 'approve'])['status']);
        $this->signOut();

        // The Riverside campus rep cannot see or decide on a Hillside student.
        $this->signIn('campus2@example.org');
        $this->assertDontSeeText('pending@example.org', $this->get('api/approvals'));
        $this->assertSame(404, $this->post("api/approvals/$pendingId", ['decision' => 'approve'])['status']);
        $this->signOut();

        // The Hillside campus rep sees them, and must give a reason to reject.
        $this->signIn('campus@example.org');
        $queue = $this->get('api/approvals');
        $this->assertSame('Hillside Campus', $queue['json']['scope']);
        $this->assertSeeText('pending@example.org', $queue);
        $this->assertSame(422, $this->post("api/approvals/$pendingId", ['decision' => 'reject'])['status']);
        $this->post("api/approvals/$pendingId", ['decision' => 'reject', 'note' => 'Please sign up with your student email.']);
        $this->signOut();

        // The student sees the reason when they try to sign in.
        $blocked = $this->signIn('pending@example.org');
        $this->assertSame(403, $blocked['status']);
        $this->assertSeeText('Reason: Please sign up with your student email.', $blocked);

        // An admin can review every campus, and can reverse the decision.
        $this->signIn('admin@example.org');
        $this->assertSame('every campus', $this->get('api/approvals')['json']['scope']);
        $this->post("api/approvals/$pendingId", ['decision' => 'approve']);
        $this->signOut();
        $this->assertSame(200, $this->signIn('pending@example.org')['status']);
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
