<?php
/**
 * Shared study resources: everything organization reps have made public,
 * plus (for signed-in students) the status of their own uploads.
 */
require __DIR__ . '/../src/bootstrap.php';

$user = current_user();
$resources = get_published_resources();
$myUploads = $user ? get_resources_by_uploader((int) $user['id']) : [];
$canShare = $user === null || $user['role'] === 'student';

$pageTitle = 'Resources';
$pageDescription = 'Notes, slides and templates shared by students and checked by organization reps.';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('Shared by students', 'Resources', 'Notes, slides and templates from students. Each one is reviewed by an organization rep before it appears here.',
        $canShare ? '<a class="button button-primary" href="' . e(url('resource-upload.php')) . '">Share a resource</a>' : ''); ?>

    <?php if ($myUploads !== []): ?>
        <section class="section" aria-labelledby="mine-heading">
            <h2 id="mine-heading" class="section-title">Your uploads</h2>
            <ul class="card divided-list padded-list">
                <?php foreach ($myUploads as $r): ?>
                    <?php [$statusText, $statusTone] = RESOURCE_STATUSES[$r['status']]; ?>
                    <li class="row-between wrap">
                        <div>
                            <strong><?= e($r['title']) ?></strong>
                            <span class="muted small"><?= e($r['organization_name']) ?></span>
                            <?php if ($r['review_note']): ?><span class="small">Rep’s note: “<?= e($r['review_note']) ?>”</span><?php endif; ?>
                        </div>
                        <?= badge($statusText, $statusTone) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="section" aria-labelledby="all-heading">
        <h2 id="all-heading" class="section-title">Public resources</h2>
        <?php if ($resources === []): ?>
            <?php empty_state('No public resources yet', 'Share your notes or slides — a rep reviews them before they go live.'); ?>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($resources as $resource) { resource_card($resource); } ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
