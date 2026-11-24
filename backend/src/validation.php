<?php
/**
 * Checking what people type into forms.
 *
 * Each function returns an array of error messages, one per field:
 *     ['title' => 'Title must be at least 5 characters']
 * An empty array means everything is valid.
 *
 * This runs on the SERVER, so it cannot be skipped by turning off
 * JavaScript or editing the page in the browser.
 */

/** Checks the "create / edit event" form used by organization reps. */
function validate_event_form(array $input, DateTimeImmutable $now): array
{
    $errors = [];

    $title = $input['title'] ?? '';
    if (mb_strlen($title) < 5) {
        $errors['title'] = 'Title must be at least 5 characters.';
    } elseif (mb_strlen($title) > 120) {
        $errors['title'] = 'Title must be 120 characters or fewer.';
    }

    if (!isset(EVENT_CATEGORIES[$input['category'] ?? ''])) {
        $errors['category'] = 'Choose a category.';
    }

    $summary = $input['summary'] ?? '';
    if (mb_strlen($summary) < 10 || mb_strlen($summary) > 220) {
        $errors['summary'] = 'The summary must be between 10 and 220 characters.';
    }

    $description = $input['description'] ?? '';
    if (mb_strlen($description) < 20 || mb_strlen($description) > 3000) {
        $errors['description'] = 'The description must be between 20 and 3000 characters.';
    }

    $venue = $input['venue'] ?? '';
    if (mb_strlen($venue) < 2 || mb_strlen($venue) > 120) {
        $errors['venue'] = 'Enter a venue.';
    }

    $date = $input['date'] ?? '';
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    $dateIsValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
    $startIsValid = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime) === 1;
    $endIsValid = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endTime) === 1;

    if (!$dateIsValid) {
        $errors['date'] = 'Choose a valid date.';
    }
    if (!$startIsValid) {
        $errors['start_time'] = 'Enter a start time like 18:30.';
    }
    if (!$endIsValid) {
        $errors['end_time'] = 'Enter an end time like 20:00.';
    } elseif ($startIsValid && $endTime <= $startTime) {
        $errors['end_time'] = 'The end time must be after the start time.';
    }
    if ($dateIsValid && $startIsValid) {
        $start = new DateTimeImmutable("$date $startTime", campus_timezone());
        if ($start <= $now) {
            $errors['date'] = 'The event must start in the future.';
        }
    }

    $capacity = $input['capacity'] ?? '';
    if ($capacity !== '' && (!ctype_digit($capacity) || (int) $capacity < 1 || (int) $capacity > 5000)) {
        $errors['capacity'] = 'Capacity must be a whole number between 1 and 5000, or empty for no limit.';
    }

    if (!in_array($input['registration'] ?? '', ['open', 'rsvp'], true)) {
        $errors['registration'] = 'Choose how people register.';
    }

    if (!isset(EVENT_VISIBILITY[$input['visibility'] ?? ''])) {
        $errors['visibility'] = 'Choose who can see this event.';
    }

    $interests = $input['interests'] ?? [];
    $validInterests = array_filter($interests, fn ($interest) => isset(INTEREST_LABELS[$interest]));
    if ($validInterests === [] || count($validInterests) !== count($interests)) {
        $errors['interests'] = 'Choose at least one interest tag.';
    }

    return $errors;
}

/** Turns the date and times typed in the event form into UTC database values. */
function event_form_times(array $input): array
{
    $start = new DateTimeImmutable($input['date'] . ' ' . $input['start_time'], campus_timezone());
    $end = new DateTimeImmutable($input['date'] . ' ' . $input['end_time'], campus_timezone());
    return [to_database($start), to_database($end)];
}

/** A person's name: the error message, or null when it is fine. Used by sign-up and the profile page. */
function name_error(string $name): ?string
{
    return mb_strlen($name) < 2 || mb_strlen($name) > 80 ? 'Enter your name (2–80 characters).' : null;
}

/** A new password and its confirmation: ['password' => msg, 'password_confirm' => msg] for what is wrong. */
function new_password_errors(string $password, string $confirm): array
{
    $errors = [];
    if (strlen($password) < 10) {
        $errors['password'] = 'Use at least 10 characters for your password.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Use 72 characters or fewer.'; // bcrypt only uses the first 72 bytes
    }
    if ($confirm !== $password) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }
    return $errors;
}

/** Checks the sign-up form. $campusIds are the campuses a student may choose. */
function validate_registration(array $input, array $campusIds): array
{
    $errors = [];

    if (($nameError = name_error($input['name'] ?? '')) !== null) {
        $errors['name'] = $nameError;
    }

    if (filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    }

    $errors += new_password_errors($input['password'] ?? '', $input['password_confirm'] ?? '');

    if (!in_array((int) ($input['campus_id'] ?? 0), $campusIds, true)) {
        $errors['campus_id'] = 'Choose your campus.';
    }

    return $errors;
}

/**
 * Checks the "share a resource" form, including the intellectual-property declaration.
 * $organizationIds are the organizations a resource can be sent to.
 */
function validate_resource_form(array $input, array $organizationIds): array
{
    $errors = [];

    $title = $input['title'] ?? '';
    if (mb_strlen($title) < 5 || mb_strlen($title) > 120) {
        $errors['title'] = 'The title must be between 5 and 120 characters.';
    }

    $description = $input['description'] ?? '';
    if (mb_strlen($description) < 10 || mb_strlen($description) > 300) {
        $errors['description'] = 'Describe the resource in 10–300 characters.';
    }

    if (!in_array((int) ($input['organization_id'] ?? 0), $organizationIds, true)) {
        $errors['organization_id'] = 'Choose the organization that should review it.';
    }

    $ownership = $input['ip_ownership'] ?? '';
    if (!isset(IP_OWNERSHIP[$ownership])) {
        $errors['ip_ownership'] = 'Tell us who created this work.';
    } elseif ($ownership !== 'sole' && mb_strlen($input['ip_credits'] ?? '') < 3) {
        $errors['ip_credits'] = 'Name the co-authors or the sources you have permission to use.';
    }
    if (mb_strlen($input['ip_credits'] ?? '') > 400) {
        $errors['ip_credits'] = 'Keep the credits under 400 characters.';
    }

    if (!isset(RESOURCE_LICENSES[$input['license'] ?? ''])) {
        $errors['license'] = 'Choose how others may use it.';
    }

    foreach (['confirm_rights', 'confirm_no_confidential', 'confirm_takedown'] as $box) {
        if (($input[$box] ?? '') !== '1') {
            $errors['declaration'] = 'Please confirm all three statements in the declaration.';
        }
    }

    $name = $input['declared_name'] ?? '';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        $errors['declared_name'] = 'Type your full name to sign the declaration.';
    }

    return $errors;
}
