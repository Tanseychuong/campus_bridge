<?php
/**
 * Home page: search box, deadlines coming up, and this week's events.
 */
require __DIR__ . '/../src/bootstrap.php';

$now = now_on_campus();
$user = current_user();
$profile = $user ? get_student_profile((int) $user['id']) : empty_profile();

// Open opportunities that have a deadline, soonest first.
$openWithDeadline = array_values(array_filter(
    get_opportunities(),
    fn ($o) => $o['deadline'] !== null && !is_closed($o['deadline'], $now)
));
usort($openWithDeadline, 'compare_deadlines');
$closingSoon = array_slice($openWithDeadline, 0, 3);
$deadlinesComingUp = array_slice($openWithDeadline, 3, 3);

// Events that have not ended and start within 7 days.
$upcomingEvents = array_values(array_filter(get_events(), fn ($e) => timestamp_of($e['ends_at']) > $now->getTimestamp()));
$thisWeek = array_slice(array_values(array_filter($upcomingEvents, fn ($e) => days_until($e['starts_at'], $now) <= 7)), 0, 3);

$stats = [
    'open opportunities'    => count(array_filter(get_opportunities(), fn ($o) => !is_closed($o['deadline'], $now))),
    'upcoming events'       => count($upcomingEvents),
    'student organizations' => count(get_organizations()),
];

require __DIR__ . '/../templates/header.php';
?>

<section class="hero">
    <div class="container hero-grid">
        <div>
            <p class="pill">Explainable recommendations · Deadline tracking</p>
            <h1 class="hero-title">Every campus opportunity, <span class="text-brand">one bridge away.</span></h1>
            <p class="hero-lead">
                Scholarships, internships, events and student organizations — in one place, ranked for
                your interests, with the deadlines you can’t afford to miss.
            </p>
            <form class="search-form search-form-large" action="<?= e(url('explore.php')) ?>" method="get" role="search">
                <label class="sr-only" for="hero-search">Search opportunities, events and organizations</label>
                <input id="hero-search" type="search" name="q" maxlength="100" placeholder="Try “internship”, “hackathon” or “climate”">
                <button class="button button-primary" type="submit">Search</button>
            </form>
            <div class="button-row">
                <a class="button button-gold" href="<?= e(url($user ? 'preferences.php' : 'register.php')) ?>">Personalise my feed →</a>
                <a class="button button-secondary" href="<?= e(url('explore.php', ['type' => 'opportunity'])) ?>">Browse opportunities</a>
            </div>
        </div>

        <div class="card hero-card">
            <div class="row-between">
                <h2 class="small-heading">Closing soon</h2>
                <?= badge('Sample data', 'gold') ?>
            </div>
            <ul class="divided-list">
                <?php foreach ($closingSoon as $o): ?>
                    <li>
                        <a class="list-link" href="<?= e(url('opportunity.php', ['id' => $o['id']])) ?>"><?= e($o['title']) ?></a>
                        <span class="muted small"><?= e($o['provider']) ?> · <?= e(deadline_label($o['deadline'], $now)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="stats">
                <?php foreach ($stats as $label => $value): ?>
                    <div><dt><?= e($label) ?></dt><dd><?= (int) $value ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>
</section>

<section class="container section" aria-labelledby="why-heading">
    <h2 id="why-heading" class="section-title">Built for the way students actually find opportunities</h2>
    <p class="muted">Opportunities are often scattered across email, group chats and posters, so deadlines are easy to miss. CampusBridge brings them together and helps you act on time.</p>
    <div class="grid grid-3">
        <div class="card feature"><h3>One search for everything</h3><p class="muted">Search opportunities, events and organizations together, with filters that stay in the address bar so you can share them.</p></div>
        <div class="card feature"><h3>Recommendations you can understand</h3><p class="muted">Every suggestion explains why: “Matches your interests: Research” or “Deadline in 6 days”. Change your interests and the ranking changes.</p></div>
        <div class="card feature"><h3>Deadlines and clashes, handled</h3><p class="muted">Saved deadlines are grouped by urgency, your calendar warns about overlapping events, and you can export everything to your own calendar app.</p></div>
    </div>
</section>

<section class="container section" aria-labelledby="deadlines-heading">
    <div class="section-heading">
        <h2 id="deadlines-heading" class="section-title">Deadlines coming up</h2>
        <a class="link-arrow" href="<?= e(url('explore.php', ['type' => 'opportunity', 'sort' => 'soonest'])) ?>">See all →</a>
    </div>
    <div class="grid grid-3">
        <?php foreach ($deadlinesComingUp as $o) { opportunity_card($o, $now, $profile); } ?>
    </div>
</section>

<section class="container section" aria-labelledby="week-heading">
    <div class="section-heading">
        <h2 id="week-heading" class="section-title">This week on campus</h2>
        <a class="link-arrow" href="<?= e(url('events.php')) ?>">All events →</a>
    </div>
    <div class="grid grid-3">
        <?php foreach ($thisWeek as $event) { event_card($event, $profile); } ?>
    </div>
</section>

<div class="container">
    <?php notice('An independent student project', 'CampusBridge was built for the CS341 Web Technologies course. It is not affiliated with or endorsed by any university, and every organization, opportunity and event shown is fictional sample data. <a href="' . e(url('about.php')) . '">Learn more</a>.'); ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
