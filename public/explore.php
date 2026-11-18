<?php
/**
 * Explore: one search across opportunities, events and organizations.
 *
 * All search settings live in the URL (explore.php?q=data&type=event), so a
 * search can be bookmarked or shared, and Back/Refresh keep it.
 */
require __DIR__ . '/../src/bootstrap.php';

$now = now_on_campus();
$user = current_user();
$profile = $user ? get_student_profile((int) $user['id']) : empty_profile();

$filters = read_explore_filters($_GET);
$documents = build_search_index(get_opportunities(), get_events(), get_organizations(), $now);
$search = run_search($documents, $filters, $now);
$results = $search['results'];
$counts = $search['counts'];

$tabs = ['all' => 'All', 'opportunity' => 'Opportunities', 'event' => 'Events', 'organization' => 'Organizations'];
$categoryLists = ['opportunity' => OPPORTUNITY_CATEGORIES, 'event' => EVENT_CATEGORIES, 'organization' => ORGANIZATION_CATEGORIES];
$activeFilterCount = count_active_filters($filters);

/** Link to the same search with a different tab (category and location are reset). */
function tab_link(array $filters, string $type): string
{
    $query = explore_query_string(array_merge($filters, ['type' => $type, 'category' => '', 'mode' => '']));
    return url('explore.php') . ($query !== '' ? '?' . $query : '');
}

$pageTitle = 'Explore';
$pageDescription = 'Search opportunities, campus events and student organizations in one place.';
require __DIR__ . '/../templates/header.php';
?>
<div class="container">
    <?php page_header('Unified discovery', 'Explore', 'One search across opportunities, campus events and student organizations.'); ?>

    <form class="search-form" action="<?= e(url('explore.php')) ?>" method="get" role="search">
        <label class="sr-only" for="site-search">Search opportunities, events and organizations</label>
        <input id="site-search" type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="100" placeholder="Try “internship”, “hackathon” or “climate”">
        <?php if ($filters['type'] !== 'all'): ?><input type="hidden" name="type" value="<?= e($filters['type']) ?>"><?php endif; ?>
        <button class="button button-primary" type="submit">Search</button>
    </form>

    <nav class="tabs" aria-label="Result types">
        <?php foreach ($tabs as $type => $label): ?>
            <a class="tab" href="<?= e(tab_link($filters, $type)) ?>" <?= $filters['type'] === $type ? 'aria-current="page"' : '' ?>>
                <?= e($label) ?> <span class="tab-count"><?= (int) $counts[$type] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="explore-layout">
        <aside>
            <details class="filters-toggle" <?= $activeFilterCount > 0 ? 'open' : '' ?> data-filters>
                <summary class="button button-secondary">Filters<?= $activeFilterCount > 0 ? " ($activeFilterCount)" : '' ?></summary>

                <!-- A normal GET form. app.js submits it automatically when something changes;
                     without JavaScript, the "Apply filters" button does the same job. -->
                <form class="card filters" action="<?= e(url('explore.php')) ?>" method="get" data-auto-submit>
                    <?php if ($filters['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif; ?>
                    <?php if ($filters['type'] !== 'all'): ?><input type="hidden" name="type" value="<?= e($filters['type']) ?>"><?php endif; ?>

                    <div class="field">
                        <label for="sort">Sort by</label>
                        <select id="sort" name="sort">
                            <?php foreach (['relevance' => 'Best match', 'soonest' => 'Soonest deadline / date', 'newest' => 'Newest'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($filters['type'] !== 'all'): ?>
                        <div class="field">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">All categories</option>
                                <?php foreach ($categoryLists[$filters['type']] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $filters['category'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($filters['type'] === 'opportunity'): ?>
                        <div class="field">
                            <label for="mode">Location</label>
                            <select id="mode" name="mode">
                                <option value="">Any location</option>
                                <?php foreach (LOCATION_MODES as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $filters['mode'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($filters['type'] !== 'organization'): ?>
                        <fieldset class="field">
                            <legend>Deadline or date</legend>
                            <?php foreach (['any' => 'Any time', '7' => 'Within 7 days', '30' => 'Within 30 days'] as $value => $label): ?>
                                <label class="radio"><input type="radio" name="within" value="<?= e($value) ?>" <?= $filters['within'] === $value ? 'checked' : '' ?>> <?= e($label) ?></label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endif; ?>

                    <fieldset class="field">
                        <legend>Interests</legend>
                        <div class="chip-list">
                            <?php foreach (INTEREST_LABELS as $value => $label): ?>
                                <label class="chip">
                                    <input type="checkbox" name="interest[]" value="<?= e($value) ?>" <?= in_array($value, $filters['interests'], true) ? 'checked' : '' ?>>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="button-row">
                        <button class="button button-primary button-small" type="submit" data-hide-with-js>Apply filters</button>
                        <?php if ($activeFilterCount > 0): ?>
                            <?php $cleared = explore_query_string(array_merge($filters, ['interests' => [], 'category' => '', 'within' => 'any', 'mode' => ''])); ?>
                            <a class="link" href="<?= e(url('explore.php') . ($cleared !== '' ? '?' . $cleared : '')) ?>">Clear filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </details>
        </aside>

        <section aria-labelledby="results-heading">
            <h2 id="results-heading" class="results-heading" aria-live="polite">
                <?= e(plural(count($results), 'result')) ?><?php if ($filters['q'] !== ''): ?> for “<?= e($filters['q']) ?>”<?php endif; ?>
            </h2>

            <?php if ($results === []): ?>
                <?php empty_state(
                    'No matches found',
                    'Try a shorter search, check the spelling or remove some filters. Closed opportunities and past events are hidden.',
                    '<a class="button button-secondary" href="' . e(url('explore.php')) . '">Reset search</a>'
                ); ?>
            <?php else: ?>
                <ul class="grid grid-2 plain-list">
                    <?php foreach ($results as $result): ?>
                        <li>
                            <?php
                            $document = $result['document'];
                            if ($document['type'] === 'opportunity') {
                                opportunity_card($document['item'], $now, $profile);
                            } elseif ($document['type'] === 'event') {
                                event_card($document['item'], $profile);
                            } else {
                                organization_card($document['item'], $profile);
                            }
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
