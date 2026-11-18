<?php
/**
 * Reading and changing campus events.
 */

/** The SELECT used by every event query: event columns + the organization's name and campus. */
const EVENT_SELECT = 'SELECT events.*, organizations.name AS organization_name,
                             organizations.campus_id AS campus_id, campuses.short_name AS campus_name
                        FROM events
                        JOIN organizations ON organizations.id = events.organization_id
                        JOIN campuses ON campuses.id = organizations.campus_id';

/**
 * Events with the given status, earliest first.
 * Published events are filtered for the person viewing the page, so
 * campus-only events never reach students of another campus.
 */
function get_events(string $status = 'published'): array
{
    [$visibleSql, $visibleParams] = $status === 'published' ? event_visibility_condition(current_user()) : ['', []];
    $rows = fetch_all(EVENT_SELECT . ' WHERE events.status = ?' . $visibleSql . ' ORDER BY events.starts_at', array_merge([$status], $visibleParams));
    return attach_interests($rows, 'event_interests', 'event_id');
}

/** One PUBLISHED event the current visitor is allowed to see, or null. */
function find_event(int $id): ?array
{
    [$visibleSql, $visibleParams] = event_visibility_condition(current_user());
    $row = fetch_one(EVENT_SELECT . " WHERE events.id = ? AND events.status = 'published'" . $visibleSql, array_merge([$id], $visibleParams));
    return $row === null ? null : attach_interests([$row], 'event_interests', 'event_id')[0];
}

/** One event in ANY status — only for organization reps editing their own events. */
function find_event_for_organization(int $eventId, int $organizationId): ?array
{
    $row = fetch_one(EVENT_SELECT . ' WHERE events.id = ? AND events.organization_id = ?', [$eventId, $organizationId]);
    return $row === null ? null : attach_interests([$row], 'event_interests', 'event_id')[0];
}

/** Every event of one organization, in any status (for the Manage page). */
function get_events_for_organization(int $organizationId): array
{
    $rows = fetch_all(EVENT_SELECT . ' WHERE events.organization_id = ? ORDER BY events.starts_at', [$organizationId]);
    return attach_interests($rows, 'event_interests', 'event_id');
}

/**
 * Creates a new event as "pending" (an admin must approve it).
 * $values are the checked form values; returns the new event's id.
 *
 * A TRANSACTION makes the two steps (insert the event, insert its interests)
 * happen together: if anything fails, nothing is saved.
 */
function create_event(array $values, string $startsAt, string $endsAt, array $organization, int $userId): int
{
    db()->beginTransaction();
    try {
        execute(
            "INSERT INTO events (title, organization_id, category, summary, description, venue, starts_at, ends_at,
                                 capacity, registration, visibility, contact, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
            [
                $values['title'], $organization['id'], $values['category'], $values['summary'], $values['description'],
                $values['venue'], $startsAt, $endsAt, $values['capacity'] === '' ? null : (int) $values['capacity'],
                $values['registration'], $values['visibility'], $organization['contact_email'], $userId,
            ]
        );
        $eventId = (int) db()->lastInsertId();
        replace_interests('event_interests', 'event_id', $eventId, $values['interests']);
        db()->commit();
        return $eventId;
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

/** Updates an event. Edited events go back to "pending" so an admin re-checks them. */
function update_event(int $eventId, array $values, string $startsAt, string $endsAt): void
{
    db()->beginTransaction();
    try {
        execute(
            "UPDATE events
                SET title = ?, category = ?, summary = ?, description = ?, venue = ?, starts_at = ?, ends_at = ?,
                    capacity = ?, registration = ?, visibility = ?, status = 'pending', review_note = NULL
              WHERE id = ?",
            [
                $values['title'], $values['category'], $values['summary'], $values['description'], $values['venue'],
                $startsAt, $endsAt, $values['capacity'] === '' ? null : (int) $values['capacity'],
                $values['registration'], $values['visibility'], $eventId,
            ]
        );
        replace_interests('event_interests', 'event_id', $eventId, $values['interests']);
        db()->commit();
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

function delete_event(int $eventId): void
{
    // The event_interests and saved_events rows are removed automatically
    // because of "ON DELETE CASCADE" in schema.sql.
    execute('DELETE FROM events WHERE id = ?', [$eventId]);
}

/** Used by admins to approve ('published') or reject ('rejected') an event. */
function set_event_status(int $id, string $status, ?string $note): void
{
    execute('UPDATE events SET status = ?, review_note = ? WHERE id = ?', [$status, $note, $id]);
}
