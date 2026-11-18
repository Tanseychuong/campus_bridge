<?php
/**
 * Upcoming events, grouped by day, with a category filter: events.php?category=tech
 */
require __DIR__ . '/../src/bootstrap.php';

$now = now_on_campus();
$user = current_user();
$profile = $user ? get_student_profile((int) $user['id']) : empty_profile();

// Only accept a category from our own list; anything else means "all".
$category = query_text('category');
if (!isset(EVENT_CATEGORIES[$category])) {
    $category = '';
}

// Group upcoming events by the day they start: ['2026-11-20' => [event, event], ...]
$eventsByDay = [];
foreach (get_events() as $event) {
    $hasEnded = timestamp_of($event['ends_at']) <= $now->getTimestamp();
    $wrongCategory = $category !== '' && $event['category'] !== $category;
    if (!$hasEnded && !$wrongCategory) {
        $eventsByDay[day_key($event['starts_at'])][] = $event;
    }
}

/** "Today · Wednesday 18 November", "Tomorrow · …", or just the date. */
function day_heading(string $day, DateTimeImmutable $now): string
{
    $date = new DateTimeImmutable($day, campus_timezone());
    $daysAway = calendar_days_between($now, $date);
    $label = $date->format('l j F');
    return match ($daysAway) {
        0 => "Today · $label",
        1 => "Tomorrow · $label",
        default => $label,
    };
}

$pageTitle = 'Campus events';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('What’s on', 'Campus events', 'Upcoming events from student organizations. Mark the ones you’re interested in to build your calendar.',
        '<a class="button button-secondary" href="' . e(url('calendar.php')) . '">My calendar</a>'); ?>

    <nav class="tabs" aria-label="Event categories">
        <a class="tab" href="<?= e(url('events.php')) ?>" <?= $category === '' ? 'aria-current="page"' : '' ?>>All</a>
        <?php foreach (EVENT_CATEGORIES as $value => $label): ?>
            <a class="tab" href="<?= e(url('events.php', ['category' => $value])) ?>" <?= $category === $value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($eventsByDay === []): ?>
        <?php empty_state('No upcoming events in this category', '', '<a class="button button-secondary" href="' . e(url('events.php')) . '">Show all events</a>'); ?>
    <?php else: ?>
        <?php foreach ($eventsByDay as $day => $events): ?>
            <section class="day-group" aria-labelledby="day-<?= e($day) ?>">
                <h2 class="day-heading" id="day-<?= e($day) ?>"><?= e(day_heading($day, $now)) ?></h2>
                <div class="grid grid-3">
                    <?php foreach ($events as $event) { event_card($event, $profile); } ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
