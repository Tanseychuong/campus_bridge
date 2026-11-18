<?php
/**
 * Directory of student organizations, with a category filter: organizations.php?category=tech
 */
require __DIR__ . '/../src/bootstrap.php';

$user = current_user();
$profile = $user ? get_student_profile((int) $user['id']) : empty_profile();

$category = query_text('category');
if (!isset(ORGANIZATION_CATEGORIES[$category])) {
    $category = '';
}

$organizations = array_filter(get_organizations(), fn ($org) => $category === '' || $org['category'] === $category);

$pageTitle = 'Student organizations';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('Directory', 'Student organizations', 'Find clubs and societies that match your interests. Follow them to see their events in your recommendations.'); ?>

    <nav class="tabs" aria-label="Organization categories">
        <a class="tab" href="<?= e(url('organizations.php')) ?>" <?= $category === '' ? 'aria-current="page"' : '' ?>>All</a>
        <?php foreach (ORGANIZATION_CATEGORIES as $value => $label): ?>
            <a class="tab" href="<?= e(url('organizations.php', ['category' => $value])) ?>" <?= $category === $value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <p class="muted results-heading" aria-live="polite"><?= e(plural(count($organizations), 'organization')) ?></p>
    <ul class="grid grid-3 plain-list">
        <?php foreach ($organizations as $org): ?>
            <li><?php organization_card($org, $profile); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
