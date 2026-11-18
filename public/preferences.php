<?php
/**
 * Preferences: choose interests, see followed organizations, reset personal data.
 *
 * One page handles two different forms. A hidden field called "action" says which:
 *   action=interests  -> save the chosen interests
 *   action=reset      -> delete the student's saved items, follows and interests
 */
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$userId = (int) $user['id'];

if (is_post()) {
    verify_csrf();
    $action = post_text('action');

    if ($action === 'interests') {
        // Keep only interests that exist in our list.
        $chosen = array_values(array_filter(post_list('interests'), fn ($i) => isset(INTEREST_LABELS[$i])));
        set_user_interests($userId, $chosen);
        flash($chosen === [] ? 'Interests cleared.' : 'Interests saved. Your recommendations are updated.');
    } elseif ($action === 'reset') {
        reset_student_data($userId);
        flash('Your interests, saved items and follows were deleted.', 'info');
    }

    redirect(safe_return_path(post_text('return'), url('preferences.php')));
}

$now = now_on_campus();
$profile = get_student_profile($userId);
$preview = recommend(get_opportunities(), get_events(), $profile, $now, 3);
$followed = array_filter(get_organizations(), fn ($org) => in_array((int) $org['id'], $profile['followed'], true));

$pageTitle = 'Preferences';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('My campus', 'Preferences', 'Control what CampusBridge recommends to you.'); ?>

    <div class="detail-layout">
        <div class="stack-large">
            <section class="card padded" aria-labelledby="interests-heading">
                <form method="post" action="<?= e(url('preferences.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="interests">
                    <fieldset>
                        <legend id="interests-heading" class="small-heading">Your interests</legend>
                        <p class="muted small">Recommendations earn points for every interest they match.</p>
                        <div class="chip-list">
                            <?php foreach (INTEREST_LABELS as $value => $label): ?>
                                <label class="chip">
                                    <input type="checkbox" name="interests[]" value="<?= e($value) ?>" <?= in_array($value, $profile['interests'], true) ? 'checked' : '' ?>>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <button class="button button-primary" type="submit">Save interests</button>
                </form>
            </section>

            <section class="card padded" aria-labelledby="followed-heading">
                <h2 id="followed-heading" class="small-heading">Organizations you follow</h2>
                <p class="muted small">Items from followed organizations get a recommendation boost.</p>
                <?php if ($followed === []): ?>
                    <p class="muted">Not following anyone yet. <a href="<?= e(url('organizations.php')) ?>">Browse organizations</a>.</p>
                <?php else: ?>
                    <ul class="divided-list">
                        <?php foreach ($followed as $org): ?>
                            <li class="row-between">
                                <a class="list-link row-gap" href="<?= e(url('organization.php', ['id' => $org['id']])) ?>"><?= org_avatar($org, 'small') ?> <?= e($org['name']) ?></a>
                                <?= toggle_button('organization', (int) $org['id'], $org['name'], true) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="card padded" id="data" aria-labelledby="data-heading">
                <h2 id="data-heading" class="small-heading">Your data</h2>
                <p class="muted small">
                    Your account stores your name, email, a hashed password, and the interests, saved items and follows you choose.
                    Resetting removes the interests, saved items and follows; your account stays.
                </p>
                <form method="post" action="<?= e(url('preferences.php')) ?>" data-confirm="Delete all your interests, saved items and follows? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset">
                    <button class="button button-danger button-small" type="submit">Reset my data</button>
                </form>
            </section>
        </div>

        <aside class="card side-card" aria-labelledby="preview-heading">
            <h2 id="preview-heading" class="small-heading">Your top recommendations</h2>
            <p class="muted small">Based on your saved settings.</p>
            <?php if ($preview === []): ?>
                <p class="muted">Select interests and save to see recommendations.</p>
            <?php else: ?>
                <ol class="preview-list">
                    <?php foreach ($preview as $position => $recommendation): ?>
                        <li>
                            <span class="small text-brand">#<?= $position + 1 ?> · score <?= (int) $recommendation['score'] ?></span>
                            <strong><?= e($recommendation['item']['title']) ?></strong>
                            <span class="muted small"><?= e($recommendation['reasons'][0]['text']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <a class="link" href="<?= e(url('dashboard.php')) ?>">Open my dashboard →</a>
        </aside>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
