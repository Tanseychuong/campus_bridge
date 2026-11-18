<?php
/**
 * Reading student organizations from the database.
 */

const ORGANIZATION_SELECT = 'SELECT organizations.*, campuses.short_name AS campus_name
                               FROM organizations
                               JOIN campuses ON campuses.id = organizations.campus_id';

/** All organizations, A–Z, each with its 'interests' and 'campus_name'. */
function get_organizations(): array
{
    $rows = fetch_all(ORGANIZATION_SELECT . ' ORDER BY organizations.name');
    return attach_interests($rows, 'organization_interests', 'organization_id');
}

/** One organization by id, or null if it does not exist. */
function find_organization(int $id): ?array
{
    $row = fetch_one(ORGANIZATION_SELECT . ' WHERE organizations.id = ?', [$id]);
    if ($row === null) {
        return null;
    }
    return attach_interests([$row], 'organization_interests', 'organization_id')[0];
}
