<?php
/**
 * User accounts and each student's personal data
 * (interests, saved items and followed organizations).
 */

function find_user_by_id(int $id): ?array
{
    return fetch_one('SELECT * FROM users WHERE id = ?', [$id]);
}

function find_user_by_email(string $email): ?array
{
    return fetch_one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
}

/**
 * Creates a student account (status 'pending', waiting for a campus rep) and returns the new user's id.
 * password_hash() turns the password into a one-way bcrypt hash (with a random
 * "salt"), so even someone who reads the database cannot see the password.
 */
function create_student_account(string $name, string $email, string $password, int $campusId): int
{
    execute(
        "INSERT INTO users (name, email, password_hash, role, campus_id, status) VALUES (?, ?, ?, 'student', ?, 'pending')",
        [$name, mb_strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $campusId]
    );
    return (int) db()->lastInsertId();
}

/**
 * Student sign-ups a reviewer may decide on: pending first, then recent decisions.
 * A campus rep sees only their own campus; an admin ($campusId null) sees every campus.
 */
function get_signups_for_review(?int $campusId): array
{
    $where = "users.role = 'student' AND (users.status = 'pending' OR users.reviewed_at > UTC_TIMESTAMP() - INTERVAL 30 DAY)";
    $params = [];
    if ($campusId !== null) {
        $where .= ' AND users.campus_id = ?';
        $params[] = $campusId;
    }
    return fetch_all(
        "SELECT users.id, users.name, users.email, users.status, users.review_note, users.created_at, users.reviewed_at,
                users.campus_id, campuses.name AS campus_name, reviewers.name AS reviewer_name
           FROM users
           JOIN campuses ON campuses.id = users.campus_id
           LEFT JOIN users AS reviewers ON reviewers.id = users.reviewed_by
          WHERE $where
          ORDER BY FIELD(users.status, 'pending', 'active', 'rejected'), users.created_at DESC",
        $params
    );
}

/** Everything the profile page shows about one account: the user row plus campus, organization and reviewer names. */
function get_account_details(int $userId): ?array
{
    return fetch_one(
        'SELECT users.*, campuses.name AS campus_name, organizations.name AS organization_name, reviewers.name AS reviewer_name
           FROM users
           LEFT JOIN campuses ON campuses.id = users.campus_id
           LEFT JOIN organizations ON organizations.id = users.organization_id
           LEFT JOIN users AS reviewers ON reviewers.id = users.reviewed_by
          WHERE users.id = ?',
        [$userId]
    );
}

function update_user_name(int $userId, string $name): void
{
    execute('UPDATE users SET name = ? WHERE id = ?', [$name, $userId]);
}

/** Stores a new password (as a one-way bcrypt hash, like at sign-up). */
function update_user_password(int $userId, string $password): void
{
    execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
}

/* ------------------------------------------------------------------
 * Profile and cover pictures
 * ------------------------------------------------------------------ */

/** Picture types we accept: detected MIME type => file extension. SVG is never accepted (it can contain scripts). */
const PROFILE_IMAGE_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
const PROFILE_IMAGE_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

/** 'avatar' or 'cover' => the users column that stores its file name. */
const PROFILE_IMAGE_COLUMNS = ['avatar' => 'avatar_name', 'cover' => 'cover_name'];

/** The folder for profile pictures (inside the uploads folder, outside public/). */
function profile_image_directory(): string
{
    $directory = upload_directory() . '/profile';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    return $directory;
}

/**
 * Checks an uploaded picture ($_FILES['image']). Returns [error message or null, extension].
 * The type comes from the file's CONTENT, and getimagesize() must read real pixel dimensions.
 */
function check_profile_image(?array $file): array
{
    if ($file === null || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['Choose a picture to upload.', null];
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > PROFILE_IMAGE_MAX_BYTES) {
        return ['The picture is too large (maximum 5 MB).', null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0) {
        return ['The picture could not be uploaded. Please try again.', null];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    $size = @getimagesize($file['tmp_name']);
    if (!isset(PROFILE_IMAGE_TYPES[$mime]) || $size === false || $size[0] < 1 || $size[1] < 1) {
        return ['Upload a PNG, JPEG or WebP picture.', null];
    }
    return [null, PROFILE_IMAGE_TYPES[$mime]];
}

/** Saves a new avatar or cover for a user and deletes the old file. Returns the new file name. */
function save_profile_image(array $user, string $kind, array $file, string $extension): string
{
    $column = PROFILE_IMAGE_COLUMNS[$kind];
    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], profile_image_directory() . '/' . $name)) {
        throw new RuntimeException('Could not save the picture.');
    }
    execute("UPDATE users SET $column = ? WHERE id = ?", [$name, (int) $user['id']]);
    delete_profile_image_file($user[$column]);
    return $name;
}

/** Removes a user's avatar or cover (the initials / plain colour are shown again). */
function remove_profile_image(array $user, string $kind): void
{
    $column = PROFILE_IMAGE_COLUMNS[$kind];
    execute("UPDATE users SET $column = NULL WHERE id = ?", [(int) $user['id']]);
    delete_profile_image_file($user[$column]);
}

function delete_profile_image_file(?string $name): void
{
    if ($name) {
        @unlink(profile_image_directory() . '/' . basename($name));
    }
}

/** A campus rep's (or admin's) decision on a sign-up: 'active' or 'rejected'. */
function set_account_status(int $userId, string $status, ?string $note, int $reviewerId): void
{
    execute(
        'UPDATE users SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?',
        [$status, $note, $reviewerId, $userId]
    );
}

/**
 * Everything the recommender and the personal pages need to know about a student.
 * Returns ['interests' => [...], 'followed' => [orgIds], 'saved_opportunity_ids' => [...], 'saved_event_ids' => [...]]
 */
function get_student_profile(int $userId): array
{
    $ids = fn (string $sql) => array_map('intval', array_column(fetch_all($sql, [$userId]), 'id'));

    return [
        'interests'             => array_column(fetch_all('SELECT interest_slug FROM user_interests WHERE user_id = ? ORDER BY interest_slug', [$userId]), 'interest_slug'),
        'followed'              => $ids('SELECT organization_id AS id FROM follows WHERE user_id = ?'),
        'saved_opportunity_ids' => $ids('SELECT opportunity_id AS id FROM saved_opportunities WHERE user_id = ?'),
        'saved_event_ids'       => $ids('SELECT event_id AS id FROM saved_events WHERE user_id = ?'),
    ];
}

/** An empty profile, used for guests. */
function empty_profile(): array
{
    return ['interests' => [], 'followed' => [], 'saved_opportunity_ids' => [], 'saved_event_ids' => []];
}

/** The profile of whoever is making this request (empty for guests). */
function current_profile(): array
{
    $user = current_user();
    return $user ? get_student_profile((int) $user['id']) : empty_profile();
}

function set_user_interests(int $userId, array $interests): void
{
    db()->beginTransaction();
    try {
        replace_interests('user_interests', 'user_id', $userId, $interests);
        db()->commit();
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

/**
 * Saves or un-saves something: if the row exists we delete it, otherwise we add it.
 * $table is one of our own table names, never user input.
 * Returns true if it is now saved, false if it was removed.
 */
function toggle_link(string $table, string $column, int $userId, int $itemId): bool
{
    $removed = execute("DELETE FROM $table WHERE user_id = ? AND $column = ?", [$userId, $itemId]);
    if ($removed > 0) {
        return false;
    }
    execute("INSERT INTO $table (user_id, $column) VALUES (?, ?)", [$userId, $itemId]);
    return true;
}

/** Deletes a student's interests, saved items and follows (the account stays). */
function reset_student_data(int $userId): void
{
    foreach (['user_interests', 'saved_opportunities', 'saved_events', 'follows'] as $table) {
        execute("DELETE FROM $table WHERE user_id = ?", [$userId]);
    }
}
