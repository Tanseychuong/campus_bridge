<?php
/**
 * One campus event: event.php?id=5
 */
require __DIR__ . '/../src/bootstrap.php';

$id = query_int('id');
$event = $id ? find_event($id) : null;
if ($event === null) {
    require __DIR__ . '/../templates/not-found.php';
    exit;
}

$now = now_on_campus();
$user = current_user();
$profile = $user ? get_student_profile((int) $user['id']) : empty_profile();
$e = $event;
$hasEnded = timestamp_of($e['ends_at']) <= $now->getTimestamp();
$isSaved = in_array((int) $e['id'], $profile['saved_event_ids'], true);
$organization = find_organization((int) $e['organization_id']);

// Other upcoming events from the same organization.
$moreEvents = array_slice(array_values(array_filter(get_events(), fn ($other) =>
    $other['organization_id'] === $e['organization_id']
    && $other['id'] !== $e['id']
    && timestamp_of($other['ends_at']) > $now->getTimestamp()
)), 0, 3);

$pageTitle = $e['title'];
$pageDescription = $e['summary'];
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <p class="back-link"><a href="<?= e(url('events.php')) ?>">← All events</a></p>

    <div class="detail-layout">
        <article>
            <div class="badge-row">
                <?= badge(EVENT_CATEGORIES[$e['category']], 'gold') ?>
                <?php foreach ($e['interests'] as $interest): ?><?= badge(INTEREST_LABELS[$interest]) ?><?php endforeach; ?>
                <?= badge('Sample data', 'gold') ?>
            </div>
            <h1 class="detail-title"><?= e($e['title']) ?></h1>
            <?php if ($organization): ?>
                <p class="org-link">
                    <a href="<?= e(url('organization.php', ['id' => $organization['id']])) ?>"><?= org_avatar($organization, 'small') ?> <?= e($organization['name']) ?></a>
                </p>
            <?php endif; ?>

            <?php if ($hasEnded): ?>
                <?php notice('This event has ended', 'It is kept here for reference only.', 'warning'); ?>
            <?php endif; ?>

            <section class="detail-section">
                <h2>About this event</h2>
                <p class="prose"><?= nl2br(e($e['description'])) ?></p>
            </section>
        </article>

        <aside>
            <div class="card side-card">
                <dl class="details">
                    <div><dt>When</dt><dd><?= e(format_event_range($e['starts_at'], $e['ends_at'])) ?> (Accra time)</dd></div>
                    <div><dt>Venue</dt><dd><?= e($e['venue']) ?></dd></div>
                    <div><dt>Capacity</dt><dd><?= e($e['capacity'] ? $e['capacity'] . ' people' : 'No limit') ?></dd></div>
                    <div><dt>Registration</dt><dd><?= e($e['registration'] === 'rsvp' ? 'RSVP required' : 'Open entry — just turn up') ?></dd></div>
                    <div><dt>Contact</dt><dd class="break-all"><?= e($e['contact']) ?></dd></div>
                </dl>
                <?php if (!$hasEnded): ?>
                    <?= toggle_button('event', (int) $e['id'], $e['title'], $isSaved, 'medium') ?>
                <?php endif; ?>
            </div>
            <?php notice('', '“I’m interested” adds the event to <a href="' . e(url('calendar.php')) . '">your calendar</a>. It is <strong>not</strong> a reservation — '
                . ($e['registration'] === 'rsvp' ? 'RSVP with the organizer to secure a place.' : 'no registration is needed.')); ?>
        </aside>
    </div>

    <?php if ($moreEvents !== [] && $organization): ?>
        <section class="section" aria-labelledby="more-heading">
            <h2 id="more-heading" class="section-title">More from <?= e($organization['name']) ?></h2>
            <div class="grid grid-3">
                <?php foreach ($moreEvents as $other) { event_card($other, $profile); } ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
