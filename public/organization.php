<?php
/**
 * One student organization: organization.php?id=1
 */
require __DIR__ . '/../src/bootstrap.php';

$id = query_int('id');
$organization = $id ? find_organization($id) : null;
if ($organization === null) {
    require __DIR__ . '/../templates/not-found.php';
    exit;
}

$now = now_on_campus();
$user = current_user();
$profile = $user ? get_student_profile((int) $user['id']) : empty_profile();
$org = $organization;
$isFollowing = in_array((int) $org['id'], $profile['followed'], true);

$events = array_filter(get_events(), fn ($e) => $e['organization_id'] === $org['id'] && timestamp_of($e['ends_at']) > $now->getTimestamp());
$opportunities = array_filter(get_opportunities(), fn ($o) => $o['organization_id'] === $org['id'] && !is_closed($o['deadline'], $now));

$pageTitle = $org['name'];
$pageDescription = $org['summary'];
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <p class="back-link"><a href="<?= e(url('organizations.php')) ?>">← All organizations</a></p>

    <header class="card org-header">
        <?= org_avatar($org, 'large') ?>
        <div class="grow">
            <div class="badge-row"><?= badge(ORGANIZATION_CATEGORIES[$org['category']], 'brand') ?> <?= badge('Sample data', 'gold') ?></div>
            <h1 class="detail-title"><?= e($org['name']) ?></h1>
            <p class="muted"><?= e($org['summary']) ?></p>
        </div>
        <?= toggle_button('organization', (int) $org['id'], $org['name'], $isFollowing, 'medium') ?>
    </header>

    <div class="detail-layout">
        <div>
            <section class="detail-section">
                <h2>About</h2>
                <p class="prose"><?= nl2br(e($org['description'])) ?></p>
                <h3>What we do</h3>
                <ul class="tile-list">
                    <?php foreach (lines($org['activities']) as $activity): ?><li><?= e($activity) ?></li><?php endforeach; ?>
                </ul>
            </section>

            <section class="detail-section" aria-labelledby="events-heading">
                <h2 id="events-heading">Upcoming events</h2>
                <?php if ($events === []): ?>
                    <?php empty_state('No upcoming events', 'Follow this organization so its future events appear in your recommendations.'); ?>
                <?php else: ?>
                    <div class="grid grid-2"><?php foreach ($events as $event) { event_card($event, $profile); } ?></div>
                <?php endif; ?>
            </section>

            <?php if ($opportunities !== []): ?>
                <section class="detail-section" aria-labelledby="opps-heading">
                    <h2 id="opps-heading">Opportunities from <?= e($org['short_name']) ?></h2>
                    <div class="grid grid-2"><?php foreach ($opportunities as $o) { opportunity_card($o, $now, $profile); } ?></div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="card side-card">
            <dl class="details">
                <div><dt>How to join</dt><dd><?= e($org['membership']) ?></dd></div>
                <div><dt>Meetings</dt><dd><?= e($org['meeting_info']) ?></dd></div>
                <div><dt>Community</dt><dd><?= (int) $org['member_count'] ?> members (sample figure) · founded <?= (int) $org['founded_year'] ?></dd></div>
                <div><dt>Contact</dt><dd class="break-all"><?= e($org['contact_email']) ?></dd></div>
                <div><dt>Interests</dt><dd class="badge-row"><?php foreach ($org['interests'] as $interest): ?><?= badge(INTEREST_LABELS[$interest]) ?><?php endforeach; ?></dd></div>
            </dl>
        </aside>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
