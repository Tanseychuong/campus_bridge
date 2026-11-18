<?php
/**
 * Top of every page: <head>, site header and navigation.
 *
 * Before including this file, a page can set:
 *   $pageTitle       (text in the browser tab)
 *   $pageDescription (optional, for search engines)
 */
// On the error page the database may be down, so we do not look up the user there.
$user = defined("SHOWING_ERROR_PAGE") ? null : current_user();
$flash = take_flash();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

// The links in the main menu: [file, label]
$discoverLinks = [['explore.php', 'Explore'], ['events.php', 'Events'], ['organizations.php', 'Organizations']];
$personalLinks = [['dashboard.php', 'Dashboard'], ['saved.php', 'Saved'], ['calendar.php', 'Calendar']];
if ($user && $user['role'] === 'org_rep') {
    $personalLinks[] = ['manage.php', 'Manage'];
}
if ($user && $user['role'] === 'admin') {
    $personalLinks[] = ['admin.php', 'Admin'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($pageTitle) ? $pageTitle . ' · CampusBridge' : 'CampusBridge — Discover opportunities, events and student organizations') ?></title>
    <meta name="description" content="<?= e($pageDescription ?? 'Scholarships, internships, campus events and student organizations in one place, with explainable recommendations and deadline tracking.') ?>">
    <meta name="theme-color" content="#2747bd">
    <link rel="icon" href="<?= e(url('assets/icon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>">
    <script src="<?= e(url('assets/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header">
    <div class="container header-row">
        <a class="logo" href="<?= e(url('index.php')) ?>" aria-label="CampusBridge home">
            <?php logo_mark(); ?>
            <span>Campus<strong>Bridge</strong></span>
        </a>

        <button class="menu-button" type="button" aria-expanded="false" aria-controls="site-menu" data-menu-button>
            <span class="sr-only">Menu</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <div class="site-menu" id="site-menu" data-menu>
            <nav aria-label="Main">
                <ul class="nav-list">
                    <?php foreach ($discoverLinks as [$file, $label]): ?>
                        <li><a href="<?= e(url($file)) ?>" <?= $currentPage === $file ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                    <li class="nav-divider" aria-hidden="true"></li>
                    <?php foreach ($personalLinks as [$file, $label]): ?>
                        <li><a href="<?= e(url($file)) ?>" <?= $currentPage === $file ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="account-area">
                <?php if ($user): ?>
                    <a class="account-name" href="<?= e(url('preferences.php')) ?>" title="Preferences">
                        <?= e($user['name']) ?>
                        <span class="role-tag"><?= e(ROLE_LABELS[$user['role']]) ?></span>
                    </a>
                    <form method="post" action="<?= e(url('logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button class="button button-ghost button-small" type="submit">Sign out</button>
                    </form>
                <?php else: ?>
                    <a class="button button-ghost button-small" href="<?= e(url('login.php')) ?>">Sign in</a>
                    <a class="button button-primary button-small" href="<?= e(url('register.php')) ?>">Create account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main id="main" tabindex="-1">
<?php if ($flash): ?>
    <div class="container">
        <p class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
    </div>
<?php endif; ?>
