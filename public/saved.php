<?php
/**
 * Everything the student saved: opportunities grouped by deadline urgency,
 * and the events they marked "I'm interested".
 */
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$now = now_on_campus();
$profile = get_student_profile((int) $user['id']);

$savedOpportunities = array_filter(get_opportunities(), fn ($o) => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true));
$deadlineGroups = group_by_deadline($savedOpportunities, $now);
$savedEvents = array_filter(get_events(), fn ($e) => in_array((int) $e['id'], $profile['saved_event_ids'], true));

$pageTitle = 'Saved & deadlines';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('My campus', 'Saved & deadlines', 'Everything you have saved, with deadlines grouped by urgency.',
        '<a class="button button-secondary" href="' . e(url('calendar-export.php')) . '">Export to calendar (.ics)</a>'); ?>

    <section class="section" aria-labelledby="saved-opps-heading">
        <h2 id="saved-opps-heading" class="section-title">Opportunities by deadline</h2>
        <?php if ($deadlineGroups === []): ?>
            <?php empty_state('No saved opportunities', 'Use the Save button on any opportunity to track its deadline here.',
                '<a class="button button-primary" href="' . e(url('explore.php', ['type' => 'opportunity'])) . '">Find opportunities</a>'); ?>
        <?php else: ?>
            <?php foreach ($deadlineGroups as $bucket => $opportunities): ?>
                <h3 class="bucket-heading<?= in_array($bucket, ['today', 'this-week'], true) ? ' text-danger' : '' ?>">
                    <?= e(DEADLINE_BUCKET_LABELS[$bucket]) ?> (<?= count($opportunities) ?>)
                </h3>
                <ul class="card divided-list padded-list">
                    <?php foreach ($opportunities as $o): ?>
                        <li class="row-between wrap">
                            <div>
                                <a class="list-link" href="<?= e(url('opportunity.php', ['id' => $o['id']])) ?>"><?= e($o['title']) ?></a>
                                <span class="muted small"><?= e($o['provider']) ?> · <?= e(OPPORTUNITY_CATEGORIES[$o['category']]) ?></span>
                            </div>
                            <div class="row-gap">
                                <?= deadline_badge($o['deadline'], $now) ?>
                                <?= toggle_button('opportunity', (int) $o['id'], $o['title'], true) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="saved-events-heading">
        <div class="section-heading">
            <h2 id="saved-events-heading" class="section-title">Events you’re interested in</h2>
            <a class="link" href="<?= e(url('calendar.php')) ?>">View as calendar</a>
        </div>
        <?php if ($savedEvents === []): ?>
            <?php empty_state('No events yet', 'Mark events as “I’m interested” to add them here and to your calendar.',
                '<a class="button button-primary" href="' . e(url('events.php')) . '">Browse events</a>'); ?>
        <?php else: ?>
            <ul class="card divided-list padded-list">
                <?php foreach ($savedEvents as $event): ?>
                    <li class="row-between wrap">
                        <div>
                            <a class="list-link" href="<?= e(url('event.php', ['id' => $event['id']])) ?>"><?= e($event['title']) ?></a>
                            <span class="muted small"><?= e(format_event_range($event['starts_at'], $event['ends_at'])) ?> · <?= e($event['venue']) ?></span>
                        </div>
                        <?= toggle_button('event', (int) $event['id'], $event['title'], true) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
