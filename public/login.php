<?php
/**
 * Sign-in page.
 * GET  shows the form.
 * POST checks the email and password, then sends the user back where they came from.
 */
require __DIR__ . '/../src/bootstrap.php';

$returnTo = safe_return_path($_GET['return'] ?? $_POST['return'] ?? null, url('dashboard.php'));
$email = '';
$error = '';

if (is_signed_in()) {
    redirect($returnTo);
}

if (is_post()) {
    verify_csrf();
    $email = post_text('email');
    $password = $_POST['password'] ?? '';

    // Slow down password guessing: after 5 failures, wait 30 seconds.
    $failures = $_SESSION['login_failures'] ?? 0;
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;

    if (time() < $lockedUntil) {
        $error = 'Too many attempts. Please wait 30 seconds and try again.';
    } else {
        $user = is_string($password) ? attempt_login($email, $password) : null;
        if ($user !== null) {
            unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
            log_in($user);
            flash('Welcome back, ' . $user['name'] . '!');
            redirect($returnTo);
        }

        // Same message whether the email or the password is wrong,
        // so attackers cannot find out which emails have accounts.
        $error = 'That email and password do not match an account.';
        $_SESSION['login_failures'] = $failures + 1;
        if ($_SESSION['login_failures'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 30;
            $_SESSION['login_failures'] = 0;
        }
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/../templates/header.php';
?>
<div class="container page-form">
    <div class="card form-card">
        <h1>Sign in</h1>
        <p class="muted">Sign in to save opportunities, follow organizations and get recommendations.</p>

        <?php if ($error !== ''): ?>
            <p class="form-alert" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="button button-primary" type="submit">Sign in</button>
        </form>

        <p class="muted small">No account yet? <a href="<?= e(url('register.php')) ?>">Create one</a>.</p>
        <?php notice('Demo accounts (sample data only)',
            'student@example.org, rep@example.org or admin@example.org — password <code>CampusBridge2026!</code>. '
            . 'These exist only in the sample database; change or delete them before any real use.'); ?>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
