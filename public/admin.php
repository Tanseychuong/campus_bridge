<?php
/**
 * Administrator area: review new listings and see content analytics.
 * Only users with the "admin" role can open this page.
 */
require __DIR__ . '/../src/bootstrap.php';

require_role('admin');
$now = now_on_campus();

// Everything waiting for a decision, opportunities and events together.
$queue = [];
foreach (get_opportunities('pending') as $o) {
    $queue[] = [
        'type' => 'opportunity', 'id' => $o['id'], 'title' => $o['title'], 'by' => $o['provider'], 'summary' => $o['summary'],
        'when' => $o['deadline'] ? 'Deadline ' . format_date($o['deadline'], 'j M Y') : 'Rolling deadline',
        'flags' => moderation_flags($o, $now),
    ];
}
foreach (get_events('pending') as $event) {
    $queue[] = [
        'type' => 'event', 'id' => $event['id'], 'title' => $event['title'], 'by' => $event['organization_name'], 'summary' => $event['summary'],
        'when' => format_event_range($event['starts_at'], $event['ends_at']),
        'flags' => moderation_flags($event, $now),
    ];
}

// Analytics, calculated from the published content.
$publishedOpportunities = get_opportunities();
$openOpportunities = array_filter($publishedOpportunities, fn ($o) => !is_closed($o['deadline'], $now));
$upcomingEvents = array_filter(get_events(), fn ($e) => timestamp_of($e['ends_at']) > $now->getTimestamp());
$accountCounts = fetch_all('SELECT role, COUNT(*) AS total FROM users GROUP BY role');

$pageTitle = 'Review & analytics';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('Administration', 'Review & analytics', 'Approve or reject new listings, and understand the content on the platform.'); ?>

    <section aria-labelledby="queue-heading">
        <h2 id="queue-heading" class="section-title">Review queue (<?= count($queue) ?>)</h2>
        <p class="muted small">New listings wait here until an administrator approves them. Automated hints point out common problems; the decision is always yours.</p>

        <?php if ($queue === []): ?>
            <?php empty_state('All caught up', 'There is nothing waiting for review.'); ?>
        <?php else: ?>
            <ul class="plain-list stack">
                <?php foreach ($queue as $item): ?>
                    <li class="card padded">
                        <div class="badge-row">
                            <?= $item['type'] === 'opportunity' ? badge('Opportunity', 'brand') : badge('Event', 'gold') ?>
                        </div>
                        <h3 class="card-title"><?= e($item['title']) ?></h3>
                        <p class="muted small"><?= e($item['by']) ?> · <?= e($item['when']) ?></p>
                        <p><?= e($item['summary']) ?></p>

                        <?php if ($item['flags'] === []): ?>
                            <p class="flag flag-ok">✓ No automated issues found</p>
                        <?php else: ?>
                            <ul class="plain-list" aria-label="Automated review hints">
                                <?php foreach ($item['flags'] as $flag): ?>
                                    <li class="flag flag-<?= e($flag['severity']) ?>">
                                        <?= $flag['severity'] === 'critical' ? '⛔ <span class="sr-only">Critical:</span>' : '⚠ <span class="sr-only">Warning:</span>' ?>
                                        <?= e($flag['message']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <form method="post" action="<?= e(url('admin-review.php')) ?>" class="review-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="<?= e($item['type']) ?>">
                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                            <div class="field">
                                <label for="note-<?= e($item['type'] . $item['id']) ?>">Note to the organizer <span class="muted">(required when rejecting)</span></label>
                                <input id="note-<?= e($item['type'] . $item['id']) ?>" name="note" maxlength="300">
                            </div>
                            <div class="button-row">
                                <button class="button button-primary button-small" type="submit" name="decision" value="approve" aria-label="Approve <?= e($item['title']) ?>">Approve</button>
                                <button class="button button-danger button-small" type="submit" name="decision" value="reject" aria-label="Reject <?= e($item['title']) ?>">Reject</button>
                            </div>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="analytics-heading">
        <h2 id="analytics-heading" class="section-title">Content analytics</h2>
        <p class="muted small">Calculated from the content in the database. With the sample data these numbers describe fictional content, not real university usage.</p>

        <div class="grid grid-2">
            <?php bar_chart('Open opportunities by category', 'Published opportunities that have not closed.', count_by_category($openOpportunities, OPPORTUNITY_CATEGORIES), 'opportunities'); ?>
            <?php bar_chart('Upcoming events by category', 'Published events that have not ended.', count_by_category($upcomingEvents, EVENT_CATEGORIES), 'events'); ?>
        </div>
        <?php bar_chart('Application deadlines per week', 'How many deadlines fall in each of the next 8 weeks — useful for timing reminders.', deadlines_per_week($publishedOpportunities, $now), 'deadlines'); ?>

        <div class="card padded">
            <h3 class="chart-title">Accounts by role</h3>
            <table class="simple-table">
                <thead><tr><th scope="col">Role</th><th scope="col">Accounts</th></tr></thead>
                <tbody>
                    <?php foreach ($accountCounts as $row): ?>
                        <tr><td><?= e(ROLE_LABELS[$row['role']]) ?></td><td><?= (int) $row['total'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
