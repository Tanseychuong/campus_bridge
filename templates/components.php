<?php
/**
 * Reusable pieces of HTML ("components").
 *
 * Each function prints a small piece of a page, so pages stay short:
 *     opportunity_card($opportunity, $now, $profile);
 *
 * Remember: every piece of data goes through e() before it is printed.
 */

/** The CampusBridge logo: an arch (a bridge) joining two pillars. */
function logo_mark(): void
{
    ?>
    <svg class="logo-mark" viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="9" fill="#2747bd"/>
        <path d="M6 22c2.5-7 6-10.5 10-10.5S23.5 15 26 22" fill="none" stroke="#efb23c" stroke-width="2.6" stroke-linecap="round"/>
        <path d="M6 22h20" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
        <path d="M11 16.5V22M16 11.5V22M21 16.5V22" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".85"/>
    </svg>
    <?php
}

/** A small coloured label. $tone: neutral, brand, gold, success, warning, danger */
function badge(string $text, string $tone = 'neutral'): string
{
    return '<span class="badge badge-' . e($tone) . '">' . e($text) . '</span>';
}

/** A badge showing how long is left before a deadline, coloured by urgency. */
function deadline_badge(?string $deadline, DateTimeImmutable $now): string
{
    $tones = ['today' => 'danger', 'this-week' => 'danger', 'next-two-weeks' => 'warning', 'later' => 'neutral', 'rolling' => 'neutral', 'closed' => 'neutral'];
    return badge(deadline_label($deadline, $now), $tones[deadline_bucket($deadline, $now)]);
}

/** Page title block at the top of most pages. $actionsHtml must already be safe HTML. */
function page_header(string $eyebrow, string $title, string $description = '', string $actionsHtml = ''): void
{
    ?>
    <div class="page-header">
        <div>
            <?php if ($eyebrow !== ''): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif; ?>
            <h1><?= e($title) ?></h1>
            <?php if ($description !== ''): ?><p class="lead"><?= e($description) ?></p><?php endif; ?>
        </div>
        <?php if ($actionsHtml !== ''): ?><div class="page-actions"><?= $actionsHtml ?></div><?php endif; ?>
    </div>
    <?php
}

/** A friendly message when there is nothing to show. $actionHtml must already be safe HTML. */
function empty_state(string $title, string $description = '', string $actionHtml = ''): void
{
    ?>
    <div class="card empty-state">
        <h2 class="empty-title"><?= e($title) ?></h2>
        <?php if ($description !== ''): ?><p class="muted"><?= e($description) ?></p><?php endif; ?>
        <?php if ($actionHtml !== ''): ?><div class="empty-action"><?= $actionHtml ?></div><?php endif; ?>
    </div>
    <?php
}

/** An information or warning box. $bodyHtml must already be safe HTML. */
function notice(string $title, string $bodyHtml, string $tone = 'info'): void
{
    ?>
    <div class="notice notice-<?= e($tone) ?>">
        <?php if ($title !== ''): ?><p class="notice-title"><?= e($title) ?></p><?php endif; ?>
        <div><?= $bodyHtml ?></div>
    </div>
    <?php
}

/**
 * An organization's coloured monogram (we never show real club logos).
 * Drawn as SVG so the colour comes from an attribute, which our strict
 * Content-Security-Policy allows (inline style="" attributes are blocked).
 */
function org_avatar(array $organization, string $size = 'medium'): string
{
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $organization['color']) ? $organization['color'] : '#475569';
    return '<svg class="avatar avatar-' . e($size) . '" viewBox="0 0 48 48" aria-hidden="true">'
        . '<rect width="48" height="48" rx="12" fill="' . e($color) . '"/>'
        . '<text x="24" y="29" text-anchor="middle" font-size="13" font-weight="700" fill="#fff">' . e($organization['short_name']) . '</text>'
        . '</svg>';
}

/* ------------------------------------------------------------------
 * Buttons that change data. They are small <form method="post"> elements,
 * so they work without JavaScript and are protected by a CSRF token.
 * ------------------------------------------------------------------ */

/**
 * Save / "I'm interested" / Follow button.
 * $kind: 'opportunity', 'event' or 'organization'
 * $isOn: is it already saved/followed?
 */
function toggle_button(string $kind, int $id, string $title, bool $isOn, string $size = 'small'): string
{
    $labels = [
        'opportunity'  => ['Save', 'Saved', 'Save '],
        'event'        => ["I'm interested", 'Interested', "I'm interested in "],
        'organization' => ['Follow', 'Following', 'Follow '],
    ];
    [$offText, $onText, $accessiblePrefix] = $labels[$kind];

    return '<form class="inline-form" method="post" action="' . e(url('toggle.php')) . '">'
        . csrf_field()
        . '<input type="hidden" name="kind" value="' . e($kind) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="return" value="' . e(current_path()) . '">'
        . '<button type="submit" class="button button-' . ($isOn ? 'primary' : 'secondary') . ' button-' . e($size) . '"'
        . ' aria-pressed="' . ($isOn ? 'true' : 'false') . '" aria-label="' . e($accessiblePrefix . $title) . '">'
        . e($isOn ? $onText : $offText)
        . '</button></form>';
}

/* ------------------------------------------------------------------
 * Cards
 * ------------------------------------------------------------------ */

/*
 * Cards are kept deliberately short: a label, the title, who it is from and
 * one key fact. Everything else lives on the detail page, one click away.
 */

function opportunity_card(array $opportunity, DateTimeImmutable $now, array $profile): void
{
    $o = $opportunity;
    $isSaved = in_array((int) $o['id'], $profile['saved_opportunity_ids'], true);
    ?>
    <article class="card item-card">
        <p class="card-kicker"><?= e(OPPORTUNITY_CATEGORIES[$o['category']]) ?></p>
        <h3 class="card-title"><a href="<?= e(url('opportunity.php', ['id' => $o['id']])) ?>"><?= e($o['title']) ?></a></h3>
        <p class="card-subtitle"><?= e($o['provider']) ?></p>
        <div class="card-footer">
            <?= deadline_badge($o['deadline'], $now) ?>
            <?= toggle_button('opportunity', (int) $o['id'], $o['title'], $isSaved) ?>
        </div>
    </article>
    <?php
}

function event_card(array $event, array $profile): void
{
    $e = $event;
    $isSaved = in_array((int) $e['id'], $profile['saved_event_ids'], true);
    ?>
    <article class="card item-card">
        <p class="card-kicker">
            <?= e(EVENT_CATEGORIES[$e['category']]) ?>
            <?php if (($e['visibility'] ?? 'public') === 'campus'): ?><span class="campus-only"><?= e($e['campus_name']) ?> only</span><?php endif; ?>
        </p>
        <h3 class="card-title"><a href="<?= e(url('event.php', ['id' => $e['id']])) ?>"><?= e($e['title']) ?></a></h3>
        <p class="card-subtitle"><?= e($e['organization_name']) ?></p>
        <div class="card-footer">
            <span class="card-when"><?= e(format_event_range($e['starts_at'], $e['ends_at'])) ?></span>
            <?= toggle_button('event', (int) $e['id'], $e['title'], $isSaved) ?>
        </div>
    </article>
    <?php
}

function organization_card(array $organization, array $profile): void
{
    $org = $organization;
    $isFollowing = in_array((int) $org['id'], $profile['followed'], true);
    ?>
    <article class="card item-card">
        <div class="org-heading">
            <?= org_avatar($org) ?>
            <div>
                <h3 class="card-title"><a href="<?= e(url('organization.php', ['id' => $org['id']])) ?>"><?= e($org['name']) ?></a></h3>
                <p class="card-subtitle"><?= e(ORGANIZATION_CATEGORIES[$org['category']] . (isset($org['campus_name']) ? ' · ' . $org['campus_name'] : '')) ?></p>
            </div>
        </div>
        <p class="card-text clamp-2"><?= e($org['summary']) ?></p>
        <div class="card-footer card-footer-end">
            <?= toggle_button('organization', (int) $org['id'], $org['name'], $isFollowing) ?>
        </div>
    </article>
    <?php
}

/** A recommended item. The main reason is shown; the full score breakdown is one click away. */
function recommendation_card(array $recommendation, DateTimeImmutable $now): void
{
    $item = $recommendation['item'];
    $isOpportunity = $recommendation['type'] === 'opportunity';
    $link = $isOpportunity ? url('opportunity.php', ['id' => $item['id']]) : url('event.php', ['id' => $item['id']]);
    $reasons = $recommendation['reasons'];
    ?>
    <article class="card item-card recommendation" data-testid="recommendation">
        <p class="card-kicker"><?= e($isOpportunity ? OPPORTUNITY_CATEGORIES[$item['category']] : EVENT_CATEGORIES[$item['category']]) ?></p>
        <h3 class="card-title"><a href="<?= e($link) ?>"><?= e($item['title']) ?></a></h3>
        <p class="card-subtitle"><?= e($isOpportunity ? $item['provider'] : $item['organization_name']) ?></p>
        <?php if ($reasons !== []): ?>
            <details class="why-box">
                <summary><?= e($reasons[0]['text']) ?></summary>
                <ul class="why-list">
                    <?php foreach ($reasons as $reason): ?>
                        <li>
                            <span><?= e($reason['text']) ?></span>
                            <strong class="<?= $reason['points'] < 0 ? 'text-danger' : 'text-brand' ?>"><?= $reason['points'] > 0 ? '+' . $reason['points'] : $reason['points'] ?></strong>
                        </li>
                    <?php endforeach; ?>
                    <li class="why-total"><span>Score</span><strong><?= (int) $recommendation['score'] ?></strong></li>
                </ul>
            </details>
        <?php endif; ?>
        <div class="card-footer">
            <span class="muted small"><?= e($isOpportunity ? deadline_label($item['deadline'], $now) : format_event_range($item['starts_at'], $item['ends_at'])) ?></span>
            <?= toggle_button($recommendation['type'], (int) $item['id'], $item['title'], false) ?>
        </div>
    </article>
    <?php
}

/** A published study resource with its download button. */
function resource_card(array $resource): void
{
    $r = $resource;
    $extension = strtoupper(pathinfo($r['original_name'], PATHINFO_EXTENSION));
    ?>
    <article class="card item-card">
        <p class="card-kicker"><?= e($extension) ?> · <?= e(format_file_size((int) $r['file_size'])) ?></p>
        <h3 class="card-title"><?= e($r['title']) ?></h3>
        <p class="card-subtitle"><?= e($r['uploader_name']) ?> · <?= e($r['organization_name']) ?></p>
        <p class="card-text clamp-2"><?= e($r['description']) ?></p>
        <div class="card-footer">
            <span class="muted small"><?= e(RESOURCE_LICENSE_SHORT[$r['license']]) ?></span>
            <a class="button button-secondary button-small" href="<?= e(url('resource-download.php', ['id' => $r['id']])) ?>"
               aria-label="Download <?= e($r['title']) ?>">Download</a>
        </div>
    </article>
    <?php
}

/* ------------------------------------------------------------------
 * Forms
 * ------------------------------------------------------------------ */

/** Prints a field's error message (if there is one) with an id that the input can point to. */
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
}

/** Extra attributes for an input that has an error, so screen readers announce it. */
function error_attributes(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($field) . '-error"' : '';
}

/* ------------------------------------------------------------------
 * Charts
 * ------------------------------------------------------------------ */

/**
 * A simple horizontal bar chart drawn with SVG, plus the same numbers as a table.
 * $data is ['Label' => number, ...]
 */
function bar_chart(string $title, string $description, array $data, string $unit): void
{
    $max = max(1, max($data ?: [0]));
    $rowHeight = 30;
    $labelWidth = 150;
    $chartWidth = 300;
    $height = count($data) * $rowHeight;
    ?>
    <figure class="card chart">
        <figcaption>
            <h3 class="chart-title"><?= e($title) ?></h3>
            <p class="muted small"><?= e($description) ?></p>
        </figcaption>
        <svg class="chart-svg" viewBox="0 0 <?= $labelWidth + $chartWidth + 40 ?> <?= $height ?>" role="img"
             aria-label="<?= e($title . ': ' . implode(', ', array_map(fn ($label, $value) => "$label $value", array_keys($data), $data))) ?>">
            <?php $row = 0; foreach ($data as $label => $value): ?>
                <?php $y = $row * $rowHeight; $barWidth = $value / $max * $chartWidth; ?>
                <text x="<?= $labelWidth - 8 ?>" y="<?= $y + 19 ?>" text-anchor="end" class="chart-label"><?= e((string) $label) ?></text>
                <rect x="<?= $labelWidth ?>" y="<?= $y + 6 ?>" width="<?= round($barWidth, 1) ?>" height="18" rx="4" class="chart-bar"><title><?= e("$label: $value $unit") ?></title></rect>
                <text x="<?= $labelWidth + $barWidth + 6 ?>" y="<?= $y + 19 ?>" class="chart-value"><?= (int) $value ?></text>
            <?php $row++; endforeach; ?>
        </svg>
        <details class="chart-table">
            <summary>View as table</summary>
            <table>
                <thead><tr><th scope="col">Category</th><th scope="col"><?= e(ucfirst($unit)) ?></th></tr></thead>
                <tbody>
                <?php foreach ($data as $label => $value): ?>
                    <tr><td><?= e((string) $label) ?></td><td><?= (int) $value ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    </figure>
    <?php
}
