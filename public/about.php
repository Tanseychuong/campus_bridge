<?php
/**
 * About CampusBridge: the problem, how recommendations work, privacy.
 */
require __DIR__ . '/../src/bootstrap.php';

$points = RECOMMENDATION_POINTS;
$rules = [
    ["Each interest the item shares with you (up to {$points['max_matching_interests']})", "+{$points['per_matching_interest']} each"],
    ['Posted by an organization you follow', "+{$points['followed_organization']}"],
    ['Same category as items you already saved', "+{$points['similar_to_saved']}"],
    ['Deadline 3–14 days away (best time to apply)', "+{$points['deadline_soon']}"],
    ['Deadline 0–2 or 15–30 days away', "+{$points['deadline_near']}"],
    ['Event within the next 7 days', "+{$points['event_this_week']}"],
    ['Event in 8–14 days', "+{$points['event_next_week']}"],
    ['Eligibility information missing', (string) $points['missing_information']],
];

$pageTitle = 'About';
require __DIR__ . '/../templates/header.php';
?>
<div class="container page-narrow prose-page">
    <?php page_header('About', 'About CampusBridge'); ?>

    <section aria-labelledby="problem">
        <h2 id="problem">The problem</h2>
        <p>
            University students hear about scholarships, internships, events and clubs through many disconnected
            channels — email, group chats, posters and social media. Our working hypotheses are that this makes it
            easy to miss deadlines, hard to discover activities outside your immediate circle, and slow to find what
            is relevant to you. We are checking these hypotheses with a short student survey and interviews.
        </p>
    </section>

    <section aria-labelledby="approach">
        <h2 id="approach">Our approach</h2>
        <ul>
            <li><strong>Unified discovery</strong> — one search across opportunities, events and organizations.</li>
            <li><strong>Explainable recommendations</strong> — a transparent points system, not a black box.</li>
            <li><strong>Deadline and schedule intelligence</strong> — urgency grouping, clash detection and calendar export.</li>
            <li><strong>Role-specific workflows</strong> — organization reps submit events; administrators review them.</li>
        </ul>
    </section>

    <section id="recommendations" aria-labelledby="recommendations-title">
        <h2 id="recommendations-title">How recommendations work</h2>
        <p>
            CampusBridge uses <strong>simple rules, not machine learning</strong>. Every published opportunity and
            upcoming event you have not saved yet earns points from the rules below. Items must match at least one of
            your interests or come from an organization you follow. The highest scores are shown first, and each card
            lists the rules that applied.
        </p>
        <table class="simple-table card">
            <caption class="sr-only">Recommendation scoring rules</caption>
            <thead><tr><th scope="col">Rule</th><th scope="col">Points</th></tr></thead>
            <tbody>
                <?php foreach ($rules as [$rule, $value]): ?>
                    <tr><td><?= e($rule) ?></td><td><strong><?= e($value) ?></strong></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p>Ties go to the soonest date, then alphabetical order, so the result is always the same for the same data.</p>
    </section>

    <section id="privacy" aria-labelledby="privacy-title">
        <h2 id="privacy-title">Privacy &amp; data</h2>
        <ul>
            <li>An account stores your name, email and a <strong>hashed</strong> password (we never store the password itself).</li>
            <li>We also store the interests, saved items and follows you choose. You can delete them from Preferences.</li>
            <li>Organization reps can only change their own organization’s events, and only administrators can publish listings. These rules are checked on the server for every request.</li>
        </ul>
    </section>

    <?php notice('Independent project disclaimer', 'CampusBridge is a student project for the CS341 Web Technologies course. It is not affiliated with, endorsed by or operated by any university. All organizations, opportunities, events and figures in the sample database are fictional; links point to the reserved example.org domain.'); ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
