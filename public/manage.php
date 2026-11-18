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
$resources = get_resources_for_organization((int) $organization['id']);
$pendingCount = count(array_filter($resources, fn ($r) => $r['status'] === 'pending'));
$statusBadges = ['pending' => ['Pending review', 'warning'], 'published' => ['Published', 'success'], 'rejected' => ['Rejected', 'danger']];

$pageTitle = 'Manage ' . $organization['name'];
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('Organization area', 'Manage your organization', 'Create events for everyone or just your campus, and review resources students send you.',
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

    <section class="section" aria-labelledby="resources-heading">
        <h2 id="resources-heading" class="section-title">Student resources<?= $pendingCount > 0 ? ' · ' . e(plural($pendingCount, 'waiting', 'waiting')) : '' ?></h2>
        <p class="muted small">Check the file and the IP declaration, then publish it. You can take a public resource down, or bring it back, at any time.</p>
        <?php if ($resources === []): ?>
            <?php empty_state('No resources yet', 'When students send resources to ' . $organization['name'] . ', they appear here.'); ?>
        <?php else: ?>
            <ul class="card divided-list padded-list">
                <?php foreach ($resources as $r): ?>
                    <?php
                    [$statusText, $statusTone] = RESOURCE_STATUSES[$r['status']];
                    $scanFlags = resource_scan_flags($r);
                    $criticalCount = $scanFlags === null ? 0 : count(array_filter($scanFlags, fn ($f) => $f['severity'] === 'critical'));
                    ?>
                    <li class="review-item">
                        <div class="row-between wrap">
                            <div>
                                <strong><?= e($r['title']) ?></strong>
                                <span class="muted small"><?= e($r['uploader_name']) ?> · <?= e(strtoupper(pathinfo($r['original_name'], PATHINFO_EXTENSION))) ?>, <?= e(format_file_size((int) $r['file_size'])) ?></span>
                            </div>
                            <?= badge($statusText, $statusTone) ?>
                        </div>

                        <div class="scan-result" data-testid="scan-result">
                            <?php if ($scanFlags === null): ?>
                                <p class="flag flag-pending">⏳ Automatic privacy &amp; copyright check in progress…</p>
                            <?php elseif ($scanFlags === []): ?>
                                <p class="flag flag-ok">✓ Automatic check found no privacy or copyright issues</p>
                            <?php else: ?>
                                <p class="scan-heading">Automatic check: <?= e(plural(count($scanFlags), 'thing') . ' to look at') ?></p>
                                <ul class="plain-list" aria-label="Automatic privacy and copyright check">
                                    <?php foreach ($scanFlags as $flag): ?>
                                        <li class="flag flag-<?= e($flag['severity']) ?>">
                                            <?= $flag['severity'] === 'critical' ? '⛔ <span class="sr-only">Critical:</span>' : '⚠ <span class="sr-only">Warning:</span>' ?>
                                            <span class="flag-kind"><?= e(SCAN_KIND_LABELS[$flag['kind']] ?? 'Check') ?></span>
                                            <?= e($flag['message']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <details class="review-details">
                            <summary>File &amp; IP declaration</summary>
                            <dl>
                                <dt>Description</dt><dd><?= e($r['description']) ?></dd>
                                <dt>Created by</dt><dd><?= e(IP_OWNERSHIP[$r['ip_ownership']]) ?></dd>
                                <?php if ($r['ip_credits']): ?><dt>Credits</dt><dd><?= e($r['ip_credits']) ?></dd><?php endif; ?>
                                <dt>Licence</dt><dd><?= e(RESOURCE_LICENSES[$r['license']]) ?></dd>
                                <dt>Signed</dt><dd><?= e($r['declared_name']) ?>, <?= e(format_date($r['declared_at'], 'j M Y, H:i')) ?> — confirmed all three statements</dd>
                                <?php if ($r['review_note']): ?><dt>Your note</dt><dd><?= e($r['review_note']) ?></dd><?php endif; ?>
                            </dl>
                            <p><a class="link" href="<?= e(url('resource-download.php', ['id' => $r['id']])) ?>">Download to check (<?= e($r['original_name']) ?>)</a></p>
                        </details>

                        <?php if ($r['status'] !== 'rejected'): ?>
                            <form method="post" action="<?= e(url('manage-resource.php')) ?>" class="review-actions">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <?php if ($r['status'] === 'pending'): ?>
                                    <div class="field">
                                        <label class="sr-only" for="note-<?= (int) $r['id'] ?>">Note to the student</label>
                                        <input id="note-<?= (int) $r['id'] ?>" name="note" maxlength="300" placeholder="Note to the student (required to reject)">
                                    </div>
                                    <button class="button button-primary button-small" type="submit" name="decision" value="publish" aria-label="Publish <?= e($r['title']) ?>"<?= $criticalCount > 0 ? ' data-confirm="The automatic check found ' . $criticalCount . ' serious issue(s). Publish anyway?"' : '' ?>>Publish</button>
                                    <button class="button button-danger button-small" type="submit" name="decision" value="reject" aria-label="Reject <?= e($r['title']) ?>">Reject</button>
                                <?php elseif ($r['status'] === 'published'): ?>
                                    <button class="button button-danger button-small" type="submit" name="decision" value="hide" aria-label="Take down <?= e($r['title']) ?>">Take down</button>
                                <?php else: ?>
                                    <button class="button button-secondary button-small" type="submit" name="decision" value="publish" aria-label="Publish <?= e($r['title']) ?> again"<?= $criticalCount > 0 ? ' data-confirm="The automatic check found ' . $criticalCount . ' serious issue(s). Publish anyway?"' : '' ?>>Publish again</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
