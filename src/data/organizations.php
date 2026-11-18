<?php
/**
 * Reading student organizations from the database.
 */

/** All organizations, A–Z, each with its 'interests'. */
function get_organizations(): array
{
    $rows = fetch_all('SELECT * FROM organizations ORDER BY name');
    return attach_interests($rows, 'organization_interests', 'organization_id');
}

/** One organization by id, or null if it does not exist. */
function find_organization(int $id): ?array
{
    $row = fetch_one('SELECT * FROM organizations WHERE id = ?', [$id]);
    if ($row === null) {
        return null;
    }
    return attach_interests([$row], 'organization_interests', 'organization_id')[0];
}
