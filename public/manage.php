<?php
/**
 * Organization rep area: see and manage the events of YOUR organization.
 * Only users with the "org_rep" role can open this page.
 */
require __DIR__ . '/../src/bootstrap.php';

$user = require_role('org_rep');
$organization = $user['organization_id'] ? find_organization((int) $user['organization_id']) : null;
if ($organization === null) {
    http_response_code(403);
    $pageTitle = 'Not allowed';
    require __DIR__ . '/../templates/forbidden.php';
    exit;
}

$events = get_events_for_organization((int) $organization['id']);
$statusBadges = ['pending' => ['Pending review', 'warning'], 'published' => ['Published', 'success'], 'rejected' => ['Rejected', 'danger']];

$pageTitle = 'Manage ' . $organization['name'];
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('Organization area', 'Manage your organization', 'Create and update events. New and edited events are checked by an administrator before they appear publicly.',
        '<a class="button button-primary" href="' . e(url('manage-event.php')) . '">Create event</a>'); ?>

    <div class="card org-header">
        <?= org_avatar($organization, 'large') ?>
        <div class="grow">
            <p class="eyebrow">You manage</p>
            <h2 class="small-heading"><?= e($organization['name']) ?></h2>
            <a class="link" href="<?= e(url('organization.php', ['id' => $organization['id']])) ?>">View public profile</a>
        </div>
    </div>

    <section class="section" aria-labelledby="events-heading">
        <h2 id="events-heading" class="section-title">Your events</h2>
        <?php if ($events === []): ?>
            <?php empty_state('No events yet', 'Create an event to see how the review workflow works.'); ?>
        <?php else: ?>
            <ul class="card divided-list padded-list">
                <?php foreach ($events as $event): ?>
                    <?php [$statusText, $statusTone] = $statusBadges[$event['status']]; ?>
                    <li class="row-between wrap">
                        <div>
                            <strong><?= e($event['title']) ?></strong>
                            <span class="muted small"><?= e(format_event_range($event['starts_at'], $event['ends_at'])) ?></span>
                            <span><?= badge($statusText, $statusTone) ?></span>
                            <?php if ($event['status'] === 'rejected' && $event['review_note']): ?>
                                <span class="small">Reviewer’s note: “<?= e($event['review_note']) ?>”</span>
                            <?php endif; ?>
                        </div>
                        <div class="row-gap">
                            <a class="button button-secondary button-small" href="<?= e(url('manage-event.php', ['id' => $event['id']])) ?>" aria-label="Edit <?= e($event['title']) ?>">Edit</a>
                            <form method="post" action="<?= e(url('manage-event-delete.php')) ?>" data-confirm="Delete “<?= e($event['title']) ?>”? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                                <button class="button button-danger button-small" type="submit" aria-label="Delete <?= e($event['title']) ?>">Delete</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
