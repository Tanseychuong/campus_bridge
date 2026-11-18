<?php
/**
 * Students share a resource: upload a file and sign the intellectual-property
 * declaration. It stays private until the chosen organization's rep publishes it.
 */
require __DIR__ . '/../src/bootstrap.php';

$user = require_role('student');
$organizationsByCampus = get_reviewing_organizations();
$organizations = array_replace([], ...array_values($organizationsByCampus)); // flat [id => name], keeping the ids

$values = [
    'title' => '', 'description' => '', 'organization_id' => '', 'ip_ownership' => 'sole', 'ip_credits' => '',
    'license' => 'cc_by', 'declared_name' => '', 'confirm_rights' => '', 'confirm_no_confidential' => '', 'confirm_takedown' => '',
];
$errors = [];

if (is_post()) {
    // A file bigger than PHP's post_max_size empties the whole form, including the CSRF token.
    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('That file is too large. The maximum is 10 MB.', 'error');
        redirect(url('resource-upload.php'));
    }
    verify_csrf();
    foreach (array_keys($values) as $field) {
        $values[$field] = post_text($field);
    }

    $errors = validate_resource_form($values, array_map('intval', array_keys($organizations)));
    [$fileError, $extension, $mime] = check_uploaded_file($_FILES['file'] ?? null);
    if ($fileError !== null) {
        $errors['file'] = $fileError;
    }

    if ($errors === []) {
        create_resource($values, $_FILES['file'], $extension, $mime, (int) $user['id']);
        flash('“' . $values['title'] . '” was sent to ' . $organizations[(int) $values['organization_id']] . ' for review.');
        redirect(url('resources.php'));
    }
}

$pageTitle = 'Share a resource';
require __DIR__ . '/../templates/header.php';
?>
<div class="container page-form-wide">
    <p class="back-link"><a href="<?= e(url('resources.php')) ?>">← Resources</a></p>
    <div class="card form-card">
        <h1>Share a resource</h1>
        <p class="muted">An automatic check looks for personal data and copyrighted material, then an organization rep reviews it before anyone else can see it.</p>

        <?php if ($errors !== []): ?>
            <p class="form-alert" role="alert" tabindex="-1" data-focus-on-load>Please fix <?= e(plural(count($errors), 'field')) ?> below.</p>
        <?php endif; ?>

        <?php if ($organizations === []): ?>
            <?php notice('No reviewers yet', 'No organization has a rep who can review resources at the moment.', 'warning'); ?>
        <?php else: ?>
        <form method="post" action="<?= e(url('resource-upload.php')) ?>" enctype="multipart/form-data" class="stack-large" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="title">Title <span class="required">*</span></label>
                <input id="title" name="title" maxlength="120" value="<?= e($values['title']) ?>"<?= error_attributes($errors, 'title') ?>>
                <?= field_error($errors, 'title') ?>
            </div>

            <div class="field">
                <label for="description">Short description <span class="required">*</span></label>
                <input id="description" name="description" maxlength="300" value="<?= e($values['description']) ?>"<?= error_attributes($errors, 'description') ?>>
                <p class="hint">One or two sentences, e.g. “My summary of week 1–6 lectures with worked examples.”</p>
                <?= field_error($errors, 'description') ?>
            </div>

            <div class="grid grid-2">
                <div class="field">
                    <label for="organization_id">Reviewed by <span class="required">*</span></label>
                    <select id="organization_id" name="organization_id"<?= error_attributes($errors, 'organization_id') ?>>
                        <option value="">Choose an organization…</option>
                        <?php foreach ($organizationsByCampus as $campusName => $campusOrganizations): ?>
                            <optgroup label="<?= e($campusName) ?>">
                                <?php foreach ($campusOrganizations as $id => $name): ?>
                                    <option value="<?= (int) $id ?>" <?= $values['organization_id'] === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'organization_id') ?>
                </div>
                <div class="field">
                    <label for="file">File <span class="required">*</span></label>
                    <input id="file" name="file" type="file" accept="<?= e(implode(',', array_map(fn ($ext) => '.' . $ext, array_keys(RESOURCE_FILE_TYPES)))) ?>"<?= error_attributes($errors, 'file') ?>>
                    <p class="hint">PDF, Word, PowerPoint, Excel, text, image or ZIP. Max 10 MB.</p>
                    <?= field_error($errors, 'file') ?>
                </div>
            </div>

            <fieldset class="ip-box stack">
                <legend>Intellectual property declaration</legend>

                <div class="field" role="radiogroup" aria-labelledby="ownership-label"<?= isset($errors['ip_ownership']) ? ' aria-describedby="ip_ownership-error"' : '' ?>>
                    <p class="field-label" id="ownership-label"><strong>Who created this work?</strong> <span class="required">*</span></p>
                    <?php foreach (IP_OWNERSHIP as $value => $label): ?>
                        <label class="radio"><input type="radio" name="ip_ownership" value="<?= e($value) ?>" <?= $values['ip_ownership'] === $value ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <?php endforeach; ?>
                    <?= field_error($errors, 'ip_ownership') ?>
                </div>

                <div class="field">
                    <label for="ip_credits">Co-authors or sources</label>
                    <textarea id="ip_credits" name="ip_credits" rows="2" maxlength="400"<?= error_attributes($errors, 'ip_credits') ?>><?= e($values['ip_credits']) ?></textarea>
                    <p class="hint">Required if others helped create it or if it includes their material (with permission).</p>
                    <?= field_error($errors, 'ip_credits') ?>
                </div>

                <div class="field">
                    <label for="license">How may others use it? <span class="required">*</span></label>
                    <select id="license" name="license"<?= error_attributes($errors, 'license') ?>>
                        <?php foreach (RESOURCE_LICENSES as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $values['license'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'license') ?>
                </div>

                <div class="field"<?= isset($errors['declaration']) ? ' aria-describedby="declaration-error"' : '' ?>>
                    <p class="field-label"><strong>I confirm that:</strong> <span class="required">*</span></p>
                    <label class="check"><input type="checkbox" name="confirm_rights" value="1" <?= $values['confirm_rights'] === '1' ? 'checked' : '' ?>>
                        This is my own work, or I have the right to share every part of it, and it does not infringe anyone’s copyright.</label>
                    <label class="check"><input type="checkbox" name="confirm_no_confidential" value="1" <?= $values['confirm_no_confidential'] === '1' ? 'checked' : '' ?>>
                        It contains no exam papers, answer keys, confidential material or other people’s personal data.</label>
                    <label class="check"><input type="checkbox" name="confirm_takedown" value="1" <?= $values['confirm_takedown'] === '1' ? 'checked' : '' ?>>
                        I understand the rep may refuse it or take it down at any time, and that I keep ownership of my work.</label>
                    <?= field_error($errors, 'declaration') ?>
                </div>

                <div class="field">
                    <label for="declared_name">Type your full name to sign <span class="required">*</span></label>
                    <input id="declared_name" name="declared_name" maxlength="80" autocomplete="name" value="<?= e($values['declared_name']) ?>"<?= error_attributes($errors, 'declared_name') ?>>
                    <?= field_error($errors, 'declared_name') ?>
                </div>
            </fieldset>

            <div class="button-row">
                <button class="button button-primary" type="submit">Submit for review</button>
                <a class="button button-secondary" href="<?= e(url('resources.php')) ?>">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
