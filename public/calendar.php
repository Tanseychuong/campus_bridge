<?php
/**
 * Month calendar of saved events and saved deadlines, with clash detection.
 * Pick a month with ?month=2026-12 (defaults to the current month).
 */
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$now = now_on_campus();
$profile = get_student_profile((int) $user['id']);

// Which month to show. Anything that is not "yyyy-mm" falls back to this month.
$monthParameter = query_text('month');
$firstOfMonth = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParameter)
    ? new DateTimeImmutable($monthParameter . '-01', campus_timezone())
    : $now->modify('first day of this month')->setTime(0, 0);

$savedEvents = array_values(array_filter(get_events(), fn ($e) => in_array((int) $e['id'], $profile['saved_event_ids'], true)));
$savedOpportunities = array_filter(get_opportunities(), fn ($o) => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true) && $o['deadline'] !== null);

$upcomingEvents = array_filter($savedEvents, fn ($e) => timestamp_of($e['ends_at']) > $now->getTimestamp());
$clashes = find_clashes($upcomingEvents);
$clashingIds = clashing_event_ids($savedEvents);

// Put every entry on its day: ['2026-11-20' => [entry, entry], ...]
$entriesByDay = [];
foreach ($savedEvents as $event) {
    $entriesByDay[day_key($event['starts_at'])][] = [
        'kind' => 'event', 'title' => $event['title'], 'time' => $event['starts_at'],
        'link' => url('event.php', ['id' => $event['id']]),
        'detail' => format_event_range($event['starts_at'], $event['ends_at']),
        'clash' => isset($clashingIds[$event['id']]),
    ];
}
foreach ($savedOpportunities as $o) {
    $entriesByDay[day_key($o['deadline'])][] = [
        'kind' => 'deadline', 'title' => 'Deadline: ' . $o['title'], 'time' => $o['deadline'],
        'link' => url('opportunity.php', ['id' => $o['id']]),
        'detail' => 'Closes at ' . format_time($o['deadline']),
        'clash' => false,
    ];
}
foreach ($entriesByDay as $day => $entries) {
    usort($entries, fn ($a, $b) => strcmp($a['time'], $b['time']));
    $entriesByDay[$day] = $entries;
}

// Build the grid: weeks start on Monday, and we show whole weeks.
$gridStart = $firstOfMonth->modify('monday this week');
$gridEnd = $firstOfMonth->modify('last day of this month')->modify('sunday this week');
$weeks = [];
$currentWeek = [];
for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')) {
    $currentWeek[] = $day;
    if (count($currentWeek) === 7) {   // a full week: start a new row
        $weeks[] = $currentWeek;
        $currentWeek = [];
    }
}

$monthKey = $firstOfMonth->format('Y-m');
$today = $now->format('Y-m-d');
$monthDays = array_filter(array_keys($entriesByDay), fn ($day) => str_starts_with($day, $monthKey));
sort($monthDays);
$hasAnything = $savedEvents !== [] || $savedOpportunities !== [];

$pageTitle = 'My calendar';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('My campus', 'My calendar', 'Your saved events and deadlines in one view, with automatic clash detection. Times are in Accra time (GMT).'); ?>

    <?php if (!$hasAnything): ?>
        <?php empty_state('Your calendar is empty', 'Mark events as “I’m interested” and save opportunities — their dates and deadlines will appear here.',
            '<a class="button button-primary" href="' . e(url('events.php')) . '">Browse events</a>'); ?>
    <?php else: ?>

        <?php if ($clashes !== []): ?>
            <?php
            $items = '';
            foreach ($clashes as [$first, $second]) {
                $items .= '<li><strong>' . e($first['title']) . '</strong> (' . e(format_time($first['starts_at']) . '–' . format_time($first['ends_at'])) . ') overlaps <strong>'
                    . e($second['title']) . '</strong> (' . e(format_time($second['starts_at']) . '–' . format_time($second['ends_at'])) . ') on ' . e(format_date($first['starts_at'], 'D j M')) . '.</li>';
            }
            notice(plural(count($clashes), 'schedule clash', 'schedule clashes') . ' detected', '<ul>' . $items . '</ul>', 'warning');
            ?>
        <?php endif; ?>

        <div class="row-between wrap calendar-toolbar">
            <div class="row-gap">
                <a class="button button-secondary button-small" href="<?= e(url('calendar.php', ['month' => $firstOfMonth->modify('-1 month')->format('Y-m')])) ?>" aria-label="Previous month">←</a>
                <h2 class="month-title"><?= e($firstOfMonth->format('F Y')) ?></h2>
                <a class="button button-secondary button-small" href="<?= e(url('calendar.php', ['month' => $firstOfMonth->modify('+1 month')->format('Y-m')])) ?>" aria-label="Next month">→</a>
            </div>
            <a class="button button-secondary" href="<?= e(url('calendar-export.php')) ?>">Export to calendar (.ics)</a>
        </div>

        <!-- The grid is a visual overview. The agenda list below has the same information for screen readers. -->
        <div class="card month-grid" aria-hidden="true">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?>
                <div class="weekday"><?= $weekday ?></div>
            <?php endforeach; ?>
            <?php foreach ($weeks as $week): foreach ($week as $day): ?>
                <?php $key = $day->format('Y-m-d'); $entries = $entriesByDay[$key] ?? []; ?>
                <div class="day<?= $day->format('Y-m') !== $monthKey ? ' day-outside' : '' ?>">
                    <span class="day-number<?= $key === $today ? ' day-today' : '' ?>"><?= $day->format('j') ?></span>
                    <?php foreach (array_slice($entries, 0, 3) as $entry): ?>
                        <span class="day-entry entry-<?= $entry['kind'] === 'deadline' ? 'deadline' : ($entry['clash'] ? 'clash' : 'event') ?>"><?= e($entry['title']) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($entries) > 3): ?><span class="muted small">+<?= count($entries) - 3 ?> more</span><?php endif; ?>
                </div>
            <?php endforeach; endforeach; ?>
        </div>

        <section class="section" aria-labelledby="agenda-heading">
            <h2 id="agenda-heading" class="section-title">Agenda for <?= e($firstOfMonth->format('F Y')) ?></h2>
            <?php if ($monthDays === []): ?>
                <p class="muted">Nothing saved in this month.</p>
            <?php else: ?>
                <ol class="plain-list agenda">
                    <?php foreach ($monthDays as $day): ?>
                        <li>
                            <h3 class="day-heading"><?= e((new DateTimeImmutable($day))->format('l j F')) ?><?= $day === $today ? ' (today)' : '' ?></h3>
                            <ul class="card divided-list padded-list">
                                <?php foreach ($entriesByDay[$day] as $entry): ?>
                                    <li>
                                        <a class="list-link" href="<?= e($entry['link']) ?>"><?= e($entry['title']) ?></a>
                                        <span class="muted small"><?= e($entry['detail']) ?></span>
                                        <?php if ($entry['clash']): ?><span class="clash-warning">⚠ Clashes with another saved event</span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
