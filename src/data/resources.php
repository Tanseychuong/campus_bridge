<?php
/**
 * Study resources shared by students.
 *
 * Workflow:
 *   1. A student uploads a file, picks the organization it belongs to and
 *      signs the intellectual-property (IP) declaration  -> status 'pending'.
 *   2. That organization's rep publishes it ('published') or rejects it.
 *   3. The rep can take a published resource down ('hidden') and publish it
 *      again at any time.
 *
 * Files are saved outside public/ with a random name, so nobody can guess
 * their address; resource-download.php checks permissions before sending one.
 */

/** File types students may upload: extension => accepted MIME types (detected from the file's content). */
const RESOURCE_FILE_TYPES = [
    'pdf'  => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'txt'  => ['text/plain'],
    'md'   => ['text/plain', 'text/markdown'],
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'zip'  => ['application/zip'],
];

const RESOURCE_MAX_BYTES = 10 * 1024 * 1024; // 10 MB

const IP_OWNERSHIP = [
    'sole'        => 'I created all of it myself',
    'joint'       => 'I created it with other people, who agree to share it',
    'third_party' => 'It includes material by others that I have permission to use',
];

const RESOURCE_LICENSES = [
    'all_rights' => 'All rights reserved — others may view and download only',
    'cc_by'      => 'CC BY — others may reuse it if they credit me',
    'cc_by_nc'   => 'CC BY-NC — reuse with credit, non-commercial only',
    'cc_by_sa'   => 'CC BY-SA — reuse with credit, and share changes alike',
];

/** Short licence names for cards. */
const RESOURCE_LICENSE_SHORT = ['all_rights' => 'All rights reserved', 'cc_by' => 'CC BY', 'cc_by_nc' => 'CC BY-NC', 'cc_by_sa' => 'CC BY-SA'];

const RESOURCE_STATUSES = [
    'pending'   => ['Waiting for review', 'warning'],
    'published' => ['Public', 'success'],
    'hidden'    => ['Taken down', 'neutral'],
    'rejected'  => ['Not accepted', 'danger'],
];

const RESOURCE_SELECT = 'SELECT resources.*, organizations.name AS organization_name, users.name AS uploader_name
                           FROM resources
                           JOIN organizations ON organizations.id = resources.organization_id
                           JOIN users ON users.id = resources.uploaded_by';

/** The folder where uploaded files are kept (outside public/). */
function upload_directory(): string
{
    $directory = app_config()['upload_dir'];
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    return $directory;
}

/** Published resources, newest first. */
function get_published_resources(): array
{
    return fetch_all(RESOURCE_SELECT . " WHERE resources.status = 'published' ORDER BY resources.reviewed_at DESC, resources.id DESC");
}

/** Everything one student has uploaded, newest first. */
function get_resources_by_uploader(int $userId): array
{
    return fetch_all(RESOURCE_SELECT . ' WHERE resources.uploaded_by = ? ORDER BY resources.id DESC', [$userId]);
}

/** Everything sent to one organization, pending first (for the rep's Manage page). */
function get_resources_for_organization(int $organizationId): array
{
    return fetch_all(
        RESOURCE_SELECT . " WHERE resources.organization_id = ?
         ORDER BY FIELD(resources.status, 'pending', 'published', 'hidden', 'rejected'), resources.id DESC",
        [$organizationId]
    );
}

/** Organizations that have a rep to review resources, grouped by campus: ['Hillside Campus' => [id => name, ...], ...]. */
function get_reviewing_organizations(): array
{
    $rows = fetch_all(
        "SELECT DISTINCT organizations.id, organizations.name, campuses.name AS campus_name
           FROM organizations
           JOIN campuses ON campuses.id = organizations.campus_id
           JOIN users ON users.organization_id = organizations.id AND users.role = 'org_rep'
          ORDER BY campuses.name, organizations.name"
    );
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['campus_name']][(int) $row['id']] = $row['name'];
    }
    return $grouped;
}

function find_resource(int $id): ?array
{
    return fetch_one(RESOURCE_SELECT . ' WHERE resources.id = ?', [$id]);
}

/** Who may download a resource: everyone once it is public; otherwise the uploader, the reviewing rep and admins. */
function can_download_resource(array $resource, ?array $user): bool
{
    if ($resource['status'] === 'published') {
        return true;
    }
    if ($user === null) {
        return false;
    }
    return (int) $resource['uploaded_by'] === (int) $user['id']
        || $user['role'] === 'admin'
        || ($user['role'] === 'org_rep' && (int) $user['organization_id'] === (int) $resource['organization_id']);
}

/**
 * Checks an uploaded file ($_FILES['file']). Returns [error message or null, extension, mime type].
 * The type is detected from the file's CONTENT (finfo), not trusted from the browser.
 */
function check_uploaded_file(?array $file): array
{
    if ($file === null || !isset($file['error']) || is_array($file['error'])) {
        return ['Choose a file to upload.', null, null];
    }
    $uploadErrors = [
        UPLOAD_ERR_NO_FILE   => 'Choose a file to upload.',
        UPLOAD_ERR_INI_SIZE  => 'The file is too large (maximum 10 MB).',
        UPLOAD_ERR_FORM_SIZE => 'The file is too large (maximum 10 MB).',
        UPLOAD_ERR_PARTIAL   => 'The upload did not finish. Please try again.',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [$uploadErrors[$file['error']] ?? 'The file could not be uploaded. Please try again.', null, null];
    }
    if ($file['size'] <= 0 || $file['size'] > RESOURCE_MAX_BYTES) {
        return ['The file must be smaller than 10 MB and not empty.', null, null];
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!isset(RESOURCE_FILE_TYPES[$extension])) {
        return ['Upload a PDF, Word, PowerPoint, Excel, text, image or ZIP file.', null, null];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
    if (!in_array($mime, RESOURCE_FILE_TYPES[$extension], true)) {
        return ['The file’s content does not match its .' . $extension . ' extension.', null, null];
    }
    return [null, $extension, $mime];
}

/** Saves the uploaded file and the resource row (status 'pending'). Returns the new id. */
function create_resource(array $values, array $file, string $extension, string $mime, int $userId): int
{
    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    $target = upload_directory() . '/' . $storedName;
    // is_uploaded_file() inside move_uploaded_file() makes sure the file really came from this upload.
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    // Keep a readable original name (letters, digits, dot, dash, underscore, space) for downloads.
    $originalName = mb_substr(preg_replace('/[^\p{L}\p{N}._\- ]+/u', '_', basename((string) $file['name'])), 0, 160);

    try {
        execute(
            'INSERT INTO resources (title, description, organization_id, uploaded_by, stored_name, original_name, mime_type, file_size,
                                    ip_ownership, ip_credits, license, declared_name, declared_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $values['title'], $values['description'], (int) $values['organization_id'], $userId, $storedName, $originalName,
                $mime, (int) $file['size'], $values['ip_ownership'], $values['ip_credits'] !== '' ? $values['ip_credits'] : null,
                $values['license'], $values['declared_name'],
            ]
        );
    } catch (Throwable $error) {
        @unlink($target);
        throw $error;
    }
    return (int) db()->lastInsertId();
}

/** A rep's decision: publish, reject, hide (take down). */
function set_resource_status(int $id, string $status, ?string $note, int $reviewerId): void
{
    execute(
        'UPDATE resources SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?',
        [$status, $note, $reviewerId, $id]
    );
}

/** "2.4 MB", "830 KB". */
function format_file_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return round($bytes / 1024 / 1024, 1) . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}
