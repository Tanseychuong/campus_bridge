<?php
/**
 * The student's dashboard: recommendations, upcoming deadlines,
 * events in their calendar (with clash warnings) and followed organizations.
 */
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$now = now_on_campus();
$profile = get_student_profile((int) $user['id']);

$opportunities = get_opportunities();
$events = get_events();
$organizations = get_organizations();

$recommendations = recommend($opportunities, $events, $profile, $now, 6);

// Saved opportunities that are still open, soonest deadline first.
$savedOpportunities = array_filter($opportunities, fn ($o) => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true));
$upcomingDeadlines = array_values(array_filter($savedOpportunities, fn ($o) => $o['deadline'] !== null && !is_closed($o['deadline'], $now)));
usort($upcomingDeadlines, 'compare_deadlines');
$dueThisWeek = array_filter($upcomingDeadlines, fn ($o) => days_until($o['deadline'], $now) <= 7);

// Saved events that have not ended yet.
$myEvents = array_values(array_filter($events, fn ($e) =>
    in_array((int) $e['id'], $profile['saved_event_ids'], true) && timestamp_of($e['ends_at']) > $now->getTimestamp()
));
$clashes = find_clashes($myEvents);
$clashingIds = clashing_event_ids($myEvents);

$followed = array_filter($organizations, fn ($org) => in_array((int) $org['id'], $profile['followed'], true));

$summary = [
    ['Saved opportunities', count($savedOpportunities), 'saved.php', false],
    ['Deadlines in 7 days', count($dueThisWeek), 'saved.php', count($dueThisWeek) > 0],
    ['Events in calendar', count($myEvents), 'calendar.php', false],
    ['Schedule clashes', count($clashes), 'calendar.php', count($clashes) > 0],
];

$pageTitle = 'My dashboard';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('My campus', 'Your dashboard', 'Recommendations, deadlines and events that matter to you.'); ?>

    <?php if ($profile['interests'] === []): ?>
        <section class="card highlight-card" aria-labelledby="start-heading">
            <h2 id="start-heading" class="small-heading">Get started: what are you interested in?</h2>
            <p class="muted">Pick a few interests and press Save. Your recommendations will appear below.</p>
            <form method="post" action="<?= e(url('preferences.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="interests">
                <input type="hidden" name="return" value="<?= e(url('dashboard.php')) ?>">
                <div class="chip-list">
                    <?php foreach (INTEREST_LABELS as $value => $label): ?>
                        <label class="chip"><input type="checkbox" name="interests[]" value="<?= e($value) ?>"> <span><?= e($label) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <button class="button button-primary" type="submit">Save my interests</button>
            </form>
        </section>
    <?php endif; ?>

    <ul class="summary-grid plain-list" aria-label="Summary">
        <?php foreach ($summary as [$label, $value, $link, $isAlert]): ?>
            <li>
                <a class="card summary-card<?= $isAlert ? ' summary-alert' : '' ?>" href="<?= e(url($link)) ?>">
                    <span class="summary-value"><?= (int) $value ?></span>
                    <span class="muted small"><?= e($label) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <section class="section" aria-labelledby="rec-heading">
        <div class="section-heading">
            <div>
                <h2 id="rec-heading" class="section-title">Recommended for you</h2>
                <p class="muted small">Ranked by a transparent rule-based score. Each card shows exactly why it was chosen.</p>
            </div>
            <a class="link-arrow" href="<?= e(url('preferences.php')) ?>">Tune recommendations →</a>
        </div>
        <?php if ($recommendations === []): ?>
            <?php empty_state(
                $profile['interests'] === [] ? 'No interests selected yet' : 'Nothing new matches right now',
                $profile['interests'] === []
                    ? 'Choose interests above or follow organizations to get personalised recommendations.'
                    : 'You may have saved everything that matches. Try adding more interests or following organizations.'
            ); ?>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($recommendations as $recommendation) { recommendation_card($recommendation, $now); } ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="grid grid-2 section">
        <section aria-labelledby="deadlines-heading">
            <div class="section-heading">
                <h2 id="deadlines-heading" class="section-title">Your upcoming deadlines</h2>
                <a class="link" href="<?= e(url('saved.php')) ?>">All saved</a>
            </div>
            <?php if ($upcomingDeadlines === []): ?>
                <?php empty_state('No saved deadlines', 'Save opportunities and their deadlines will be tracked here.'); ?>
            <?php else: ?>
                <ul class="card divided-list padded-list">
                    <?php foreach (array_slice($upcomingDeadlines, 0, 5) as $o): ?>
                        <li class="row-between">
                            <a class="list-link" href="<?= e(url('opportunity.php', ['id' => $o['id']])) ?>"><?= e($o['title']) ?></a>
                            <?= deadline_badge($o['deadline'], $now) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section aria-labelledby="events-heading">
            <div class="section-heading">
                <h2 id="events-heading" class="section-title">Coming up in your calendar</h2>
                <a class="link" href="<?= e(url('calendar.php')) ?>">Open calendar</a>
            </div>
            <?php if ($myEvents === []): ?>
                <?php empty_state('No events in your calendar', 'Mark events as “I’m interested” to plan your week.'); ?>
            <?php else: ?>
                <ul class="card divided-list padded-list">
                    <?php foreach (array_slice($myEvents, 0, 5) as $event): ?>
                        <li>
                            <a class="list-link" href="<?= e(url('event.php', ['id' => $event['id']])) ?>"><?= e($event['title']) ?></a>
                            <span class="muted small"><?= e(format_event_range($event['starts_at'], $event['ends_at'])) ?></span>
                            <?php if (isset($clashingIds[$event['id']])): ?>
                                <span class="clash-warning">⚠ Clashes with another event in your calendar</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="section" aria-labelledby="following-heading">
        <h2 id="following-heading" class="section-title">Organizations you follow</h2>
        <?php if ($followed === []): ?>
            <?php empty_state('You don’t follow any organizations yet', 'Following an organization boosts its events and opportunities in your recommendations.',
                '<a class="button button-secondary" href="' . e(url('organizations.php')) . '">Browse organizations</a>'); ?>
        <?php else: ?>
            <ul class="follow-list plain-list">
                <?php foreach ($followed as $org): ?>
                    <li><a class="card follow-chip" href="<?= e(url('organization.php', ['id' => $org['id']])) ?>"><?= org_avatar($org, 'small') ?> <?= e($org['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
