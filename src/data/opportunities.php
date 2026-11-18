<?php
/**
 * Reading and updating opportunities (scholarships, internships, ...).
 */

/**
 * Opportunities with the given status ('published', 'pending' or 'rejected').
 * The LEFT JOIN adds the name of the posting organization when there is one.
 */
function get_opportunities(string $status = 'published'): array
{
    $rows = fetch_all(
        'SELECT opportunities.*, organizations.name AS organization_name
           FROM opportunities
           LEFT JOIN organizations ON organizations.id = opportunities.organization_id
          WHERE opportunities.status = ?
          ORDER BY opportunities.deadline IS NULL, opportunities.deadline, opportunities.title',
        [$status]
    );
    return attach_interests($rows, 'opportunity_interests', 'opportunity_id');
}

/** One PUBLISHED opportunity, or null. Pending and rejected ones stay hidden. */
function find_opportunity(int $id): ?array
{
    $row = fetch_one(
        "SELECT opportunities.*, organizations.name AS organization_name
           FROM opportunities
           LEFT JOIN organizations ON organizations.id = opportunities.organization_id
          WHERE opportunities.id = ? AND opportunities.status = 'published'",
        [$id]
    );
    return $row === null ? null : attach_interests([$row], 'opportunity_interests', 'opportunity_id')[0];
}

/** Used by admins to approve ('published') or reject ('rejected') an opportunity. */
function set_opportunity_status(int $id, string $status, ?string $note): void
{
    execute('UPDATE opportunities SET status = ?, review_note = ? WHERE id = ?', [$status, $note, $id]);
}
