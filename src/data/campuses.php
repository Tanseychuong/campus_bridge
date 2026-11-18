<?php
/**
 * Campuses. CampusBridge is shared by several campuses: every organization
 * and student belongs to one, and organizers choose whether an event is for
 * everyone or only for their own campus.
 */

/** All campuses as [id => name], A–Z. */
function get_campuses(): array
{
    static $campuses = null;
    if ($campuses === null) {
        $campuses = [];
        foreach (fetch_all('SELECT id, name FROM campuses ORDER BY name') as $row) {
            $campuses[(int) $row['id']] = $row['name'];
        }
    }
    return $campuses;
}

/**
 * The SQL condition that hides campus-only events from people on other campuses.
 * Returns [sql, params] to add to a WHERE clause on a query that joins organizations.
 *
 *   administrators  -> see everything
 *   signed in       -> public events + campus-only events of their own campus
 *   guests          -> public events only
 */
function event_visibility_condition(?array $viewer): array
{
    if ($viewer !== null && $viewer['role'] === 'admin') {
        return ['', []];
    }
    if ($viewer === null || $viewer['campus_id'] === null) {
        return [" AND events.visibility = 'public'", []];
    }
    return [" AND (events.visibility = 'public' OR organizations.campus_id = ?)", [(int) $viewer['campus_id']]];
}
