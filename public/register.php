<?php
/**
 * Create a student account.
 * New accounts are always "student". Admins and org reps are set up in the database.
 */
require __DIR__ . '/../src/bootstrap.php';

if (is_signed_in()) {
    redirect(url('dashboard.php'));
}

$values = ['name' => '', 'email' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();
    $values = ['name' => post_text('name'), 'email' => post_text('email')];
    $input = $values + [
        'password'         => (string) ($_POST['password'] ?? ''),
        'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
    ];

    $errors = validate_registration($input);
    if (!isset($errors['email']) && find_user_by_email($values['email']) !== null) {
        $errors['email'] = 'An account with this email already exists. Try signing in.';
    }

    if ($errors === []) {
        $userId = create_student_account($values['name'], $values['email'], $input['password']);
        log_in(find_user_by_id($userId));
        flash('Your account is ready. Choose a few interests to get recommendations.');
        redirect(url('dashboard.php'));
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/../templates/header.php';
?>
<div class="container page-form">
    <div class="card form-card">
        <h1>Create your account</h1>
        <p class="muted">Free for students. We only store your name, email and the choices you make in CampusBridge.</p>

        <?php if ($errors !== []): ?>
            <p class="form-alert" role="alert">Please fix <?= e(plural(count($errors), 'field')) ?> below.</p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('register.php')) ?>" class="stack" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Name</label>
                <input id="name" name="name" autocomplete="name" maxlength="80" value="<?= e($values['name']) ?>"<?= error_attributes($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" value="<?= e($values['email']) ?>"<?= error_attributes($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password"<?= error_attributes($errors, 'password') ?>>
                <p class="hint">At least 10 characters.</p>
                <?= field_error($errors, 'password') ?>
            </div>
            <div class="field">
                <label for="password_confirm">Repeat password</label>
                <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password"<?= error_attributes($errors, 'password_confirm') ?>>
                <?= field_error($errors, 'password_confirm') ?>
            </div>
            <button class="button button-primary" type="submit">Create account</button>
        </form>
        <p class="muted small">Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a>.</p>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
