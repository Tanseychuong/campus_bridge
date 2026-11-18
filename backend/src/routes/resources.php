<?php
/**
 * Study resources shared by students (see data/resources.php for the workflow).
 */

/** GET /api/resources — public resources, plus the signed-in student's own uploads and their status. */
route('GET', '/api/resources', function (): array {
    $user = current_user();
    return [
        'resources'  => array_map('present_resource', get_published_resources()),
        'my_uploads' => $user ? array_map('present_resource', get_resources_by_uploader((int) $user['id'])) : [],
        // Guests see the button too; it leads them to sign in first.
        'can_share'  => $user === null || $user['role'] === 'student',
    ];
});

/** GET /api/resources/options — the organizations a student can send a resource to, grouped by campus. */
route('GET', '/api/resources/options', function (): array {
    require_role('student');
    $groups = [];
    foreach (get_reviewing_organizations() as $campusName => $organizations) {
        $groups[] = [
            'campus'        => $campusName,
            'organizations' => array_map(fn ($id, $name) => ['id' => (int) $id, 'name' => $name], array_keys($organizations), $organizations),
        ];
    }
    return ['groups' => $groups];
});

/**
 * POST /api/resources   (multipart form: the fields below + "file")
 * A student uploads a file and signs the intellectual-property declaration.
 * It stays private until the chosen organization's rep publishes it.
 */
route('POST', '/api/resources', function (): array {
    $user = require_role('student');

    // A file bigger than PHP's post_max_size empties the whole form.
    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        abort(413, 'That file is too large. The maximum is 10 MB.');
    }

    $organizations = array_replace([], ...array_values(get_reviewing_organizations())); // flat [id => name], keeping the ids
    $values = [];
    foreach (['title', 'description', 'organization_id', 'ip_ownership', 'ip_credits', 'license', 'declared_name',
              'confirm_rights', 'confirm_no_confidential', 'confirm_takedown'] as $field) {
        $values[$field] = input_text($field);
    }

    $errors = validate_resource_form($values, array_map('intval', array_keys($organizations)));
    [$fileError, $extension, $mime] = check_uploaded_file($_FILES['file'] ?? null);
    if ($fileError !== null) {
        $errors['file'] = $fileError;
    }
    if ($errors !== []) {
        abort_with_form_errors($errors);
    }

    $id = create_resource($values, $_FILES['file'], $extension, $mime, (int) $user['id']);
    http_response_code(201);
    return [
        'id'      => $id,
        'message' => '“' . $values['title'] . '” was sent to ' . $organizations[(int) $values['organization_id']] . ' for review.',
    ];
});

/**
 * GET /api/resources/{id}/download
 * Public resources can be downloaded by anyone. Pending, hidden and rejected
 * ones only by the student who uploaded them, the reviewing rep and admins.
 * The file is always sent as a download ("attachment"), never shown inside
 * our site, so an uploaded HTML or SVG file can never run as part of CampusBridge.
 */
route('GET', '/api/resources/{id}/download', function (array $params): ?array {
    $resource = find_resource($params['id']);
    if ($resource === null || !can_download_resource($resource, current_user())) {
        abort(404, 'Resource not found.');
    }
    $path = upload_directory() . '/' . basename($resource['stored_name']);
    if (!is_file($path)) {
        abort(404, 'The file is missing.');
    }

    $asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $resource['original_name']);
    header('Content-Type: ' . $resource['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($resource['original_name']));
    header('Cache-Control: private, no-store');
    readfile($path);
    return null; // we sent the file ourselves, not JSON
});
