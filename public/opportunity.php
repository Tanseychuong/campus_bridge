<?php
/**
 * One opportunity: opportunity.php?id=3
 */
require __DIR__ . '/../src/bootstrap.php';

$id = query_int('id');
$opportunity = $id ? find_opportunity($id) : null;
if ($opportunity === null) {
    require __DIR__ . '/../templates/not-found.php';
    exit;
}

$now = now_on_campus();
$user = current_user();
$profile = $user ? get_student_profile((int) $user['id']) : empty_profile();
$o = $opportunity;
$closed = is_closed($o['deadline'], $now);
$isSaved = in_array((int) $o['id'], $profile['saved_opportunity_ids'], true);

// "Similar opportunities": same category or a shared interest, still open, soonest first.
$similar = array_filter(get_opportunities(), function ($other) use ($o, $now) {
    $isSimilar = $other['category'] === $o['category'] || array_intersect($other['interests'], $o['interests']) !== [];
    return $other['id'] !== $o['id'] && $isSimilar && !is_closed($other['deadline'], $now);
});
usort($similar, 'compare_deadlines');
$similar = array_slice($similar, 0, 3);

// Only https links are shown as clickable (blocks "javascript:" links even if bad data gets in).
$sourceIsSafe = str_starts_with($o['source_url'], 'https://');

$pageTitle = $o['title'];
$pageDescription = $o['summary'];
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <p class="back-link"><a href="<?= e(url('explore.php', ['type' => 'opportunity'])) ?>">← All opportunities</a></p>

    <div class="detail-layout">
        <article>
            <div class="badge-row">
                <?= badge(OPPORTUNITY_CATEGORIES[$o['category']], 'brand') ?>
                <?= deadline_badge($o['deadline'], $now) ?>
                <?= badge('Sample data', 'gold') ?>
            </div>
            <h1 class="detail-title"><?= e($o['title']) ?></h1>
            <p class="detail-subtitle">
                <?php if ($o['organization_id']): ?>
                    <a href="<?= e(url('organization.php', ['id' => $o['organization_id']])) ?>"><?= e($o['provider']) ?></a>
                <?php else: ?>
                    <?= e($o['provider']) ?>
                <?php endif; ?>
            </p>

            <?php if ($closed): ?>
                <?php notice('This opportunity has closed', 'The deadline has passed. It is kept here for reference only.', 'warning'); ?>
            <?php endif; ?>

            <section class="detail-section">
                <h2>About this opportunity</h2>
                <p class="prose"><?= nl2br(e($o['description'])) ?></p>
            </section>

            <section class="detail-section">
                <h2>Eligibility</h2>
                <?php $requirements = lines($o['eligibility']); ?>
                <?php if ($requirements === []): ?>
                    <p class="muted">The provider has not published eligibility details. Check the official source before applying.</p>
                <?php else: ?>
                    <ul class="check-list">
                        <?php foreach ($requirements as $requirement): ?><li><?= e($requirement) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </article>

        <aside>
            <div class="card side-card">
                <dl class="details">
                    <div><dt>Deadline</dt><dd><?= e($o['deadline'] ? format_date($o['deadline']) . ', ' . format_time($o['deadline']) . ' (Accra time)' : 'Rolling — no fixed deadline') ?></dd></div>
                    <div><dt>Location</dt><dd><?= e(LOCATION_MODES[$o['location_mode']] . ($o['location_place'] ? ' · ' . $o['location_place'] : '')) ?></dd></div>
                    <div><dt>Interests</dt><dd class="badge-row"><?php foreach ($o['interests'] as $interest): ?><?= badge(INTEREST_LABELS[$interest]) ?><?php endforeach; ?></dd></div>
                </dl>
                <div class="stack">
                    <?php if (!$closed && $sourceIsSafe): ?>
                        <!-- rel="noopener noreferrer" stops the other website from controlling this tab. -->
                        <a class="button button-primary" href="<?= e($o['source_url']) ?>" target="_blank" rel="noopener noreferrer">
                            Apply on official site <span class="sr-only">(opens <?= e(parse_url($o['source_url'], PHP_URL_HOST)) ?> in a new tab)</span>
                        </a>
                    <?php endif; ?>
                    <?= toggle_button('opportunity', (int) $o['id'], $o['title'], $isSaved, 'medium') ?>
                </div>
            </div>
            <p class="muted small side-note">
                Source: <strong><?= e($o['source_name']) ?></strong> (<?= e(parse_url($o['source_url'], PHP_URL_HOST) ?: 'unknown') ?>).
                Published <?= e(format_date($o['published_at'], 'j M Y')) ?>. This is fictional sample data; the link points to a reserved example domain.
            </p>
        </aside>
    </div>

    <?php if ($similar !== []): ?>
        <section class="section" aria-labelledby="similar-heading">
            <h2 id="similar-heading" class="section-title">Similar opportunities</h2>
            <div class="grid grid-3">
                <?php foreach ($similar as $other) { opportunity_card($other, $now, $profile); } ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
