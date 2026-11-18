<?php
/**
 * Create a new event (manage-event.php) or edit one (manage-event.php?id=4).
 *
 * Security: an org rep can only edit events that belong to THEIR organization.
 * find_event_for_organization() only finds an event if both the event id AND
 * the organization id match, so changing the id in the URL does not help.
 */
require __DIR__ . '/../src/bootstrap.php';

$user = require_role('org_rep');
$organization = $user['organization_id'] ? find_organization((int) $user['organization_id']) : null;
if ($organization === null) {
    redirect(url('manage.php'));
}

$now = now_on_campus();
$eventId = query_int('id') ?? (int) ($_POST['id'] ?? 0);
$existingEvent = $eventId ? find_event_for_organization($eventId, (int) $organization['id']) : null;
if ($eventId && $existingEvent === null) {
    require __DIR__ . '/../templates/not-found.php';
    exit;
}

// The form's starting values: the existing event, or sensible defaults for a new one.
if ($existingEvent) {
    $values = [
        'title' => $existingEvent['title'], 'category' => $existingEvent['category'], 'summary' => $existingEvent['summary'],
        'description' => $existingEvent['description'], 'venue' => $existingEvent['venue'],
        'date' => format_date($existingEvent['starts_at'], 'Y-m-d'),
        'start_time' => format_time($existingEvent['starts_at']), 'end_time' => format_time($existingEvent['ends_at']),
        'capacity' => $existingEvent['capacity'] === null ? '' : (string) $existingEvent['capacity'],
        'registration' => $existingEvent['registration'], 'interests' => $existingEvent['interests'],
    ];
} else {
    $values = [
        'title' => '', 'category' => 'tech', 'summary' => '', 'description' => '', 'venue' => '',
        'date' => $now->modify('+7 days')->format('Y-m-d'), 'start_time' => '18:00', 'end_time' => '19:30',
        'capacity' => '', 'registration' => 'open', 'interests' => [],
    ];
}
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach (['title', 'category', 'summary', 'description', 'venue', 'date', 'start_time', 'end_time', 'capacity', 'registration'] as $field) {
        $values[$field] = post_text($field);
    }
    $values['interests'] = post_list('interests');

    $errors = validate_event_form($values, $now);
    if ($errors === []) {
        [$startsAt, $endsAt] = event_form_times($values);
        if ($existingEvent) {
            update_event((int) $existingEvent['id'], $values, $startsAt, $endsAt);
            flash('“' . $values['title'] . '” updated and sent for review again.');
        } else {
            create_event($values, $startsAt, $endsAt, $organization, (int) $user['id']);
            flash('“' . $values['title'] . '” submitted for admin review.');
        }
        redirect(url('manage.php'));
    }
}

$pageTitle = $existingEvent ? 'Edit event' : 'Create event';
require __DIR__ . '/../templates/header.php';
?>
<div class="container page-form-wide">
    <p class="back-link"><a href="<?= e(url('manage.php')) ?>">← Back to your events</a></p>
    <div class="card form-card">
        <h1><?= e($pageTitle) ?></h1>
        <p class="muted">For <?= e($organization['name']) ?>. Times are in Accra time (GMT).</p>

        <?php if ($errors !== []): ?>
            <p class="form-alert" role="alert" tabindex="-1" data-focus-on-load>Please fix <?= e(plural(count($errors), 'field')) ?> below.</p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('manage-event.php', $existingEvent ? ['id' => $existingEvent['id']] : [])) ?>" class="stack" novalidate>
            <?= csrf_field() ?>
            <?php if ($existingEvent): ?><input type="hidden" name="id" value="<?= (int) $existingEvent['id'] ?>"><?php endif; ?>

            <div class="field">
                <label for="title">Title <span class="required">*</span></label>
                <input id="title" name="title" maxlength="120" value="<?= e($values['title']) ?>"<?= error_attributes($errors, 'title') ?>>
                <?= field_error($errors, 'title') ?>
            </div>

            <div class="grid grid-2">
                <div class="field">
                    <label for="category">Category <span class="required">*</span></label>
                    <select id="category" name="category"<?= error_attributes($errors, 'category') ?>>
                        <?php foreach (EVENT_CATEGORIES as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $values['category'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'category') ?>
                </div>
                <div class="field">
                    <label for="venue">Venue <span class="required">*</span></label>
                    <input id="venue" name="venue" maxlength="120" value="<?= e($values['venue']) ?>"<?= error_attributes($errors, 'venue') ?>>
                    <?= field_error($errors, 'venue') ?>
                </div>
            </div>

            <div class="field">
                <label for="summary">Short summary <span class="required">*</span></label>
                <input id="summary" name="summary" maxlength="220" value="<?= e($values['summary']) ?>"<?= error_attributes($errors, 'summary') ?>>
                <p class="hint">One sentence shown on event cards (10–220 characters).</p>
                <?= field_error($errors, 'summary') ?>
            </div>

            <div class="field">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" rows="5" maxlength="3000"<?= error_attributes($errors, 'description') ?>><?= e($values['description']) ?></textarea>
                <?= field_error($errors, 'description') ?>
            </div>

            <div class="grid grid-3">
                <div class="field">
                    <label for="date">Date <span class="required">*</span></label>
                    <input id="date" name="date" type="date" value="<?= e($values['date']) ?>"<?= error_attributes($errors, 'date') ?>>
                    <?= field_error($errors, 'date') ?>
                </div>
                <div class="field">
                    <label for="start_time">Start time <span class="required">*</span></label>
                    <input id="start_time" name="start_time" type="time" value="<?= e($values['start_time']) ?>"<?= error_attributes($errors, 'start_time') ?>>
                    <?= field_error($errors, 'start_time') ?>
                </div>
                <div class="field">
                    <label for="end_time">End time <span class="required">*</span></label>
                    <input id="end_time" name="end_time" type="time" value="<?= e($values['end_time']) ?>"<?= error_attributes($errors, 'end_time') ?>>
                    <?= field_error($errors, 'end_time') ?>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="field">
                    <label for="capacity">Capacity</label>
                    <input id="capacity" name="capacity" inputmode="numeric" value="<?= e($values['capacity']) ?>"<?= error_attributes($errors, 'capacity') ?>>
                    <p class="hint">Leave empty for no limit.</p>
                    <?= field_error($errors, 'capacity') ?>
                </div>
                <div class="field">
                    <label for="registration">Registration <span class="required">*</span></label>
                    <select id="registration" name="registration">
                        <option value="open" <?= $values['registration'] === 'open' ? 'selected' : '' ?>>Open entry</option>
                        <option value="rsvp" <?= $values['registration'] === 'rsvp' ? 'selected' : '' ?>>RSVP required</option>
                    </select>
                </div>
            </div>

            <fieldset class="field"<?= isset($errors['interests']) ? ' aria-describedby="interests-error"' : '' ?>>
                <legend>Interest tags <span class="required">*</span></legend>
                <div class="chip-list">
                    <?php foreach (INTEREST_LABELS as $value => $label): ?>
                        <label class="chip">
                            <input type="checkbox" name="interests[]" value="<?= e($value) ?>" <?= in_array($value, $values['interests'], true) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= field_error($errors, 'interests') ?>
            </fieldset>

            <div class="button-row">
                <button class="button button-primary" type="submit"><?= $existingEvent ? 'Save changes' : 'Submit for review' ?></button>
                <a class="button button-secondary" href="<?= e(url('manage.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
