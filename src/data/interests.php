<?php
/**
 * Interests are stored in "join tables" such as opportunity_interests:
 *
 *     opportunity_id | interest_slug
 *     ---------------+-----------------
 *                  1 | computer-science
 *                  1 | research
 *
 * This helper loads them for a list of rows in ONE query and adds an
 * 'interests' list to each row: $row['interests'] = ['computer-science', 'research'].
 */
function attach_interests(array $rows, string $joinTable, string $idColumn): array
{
    if ($rows === []) {
        return [];
    }

    // Table and column names come from our own code (never from users), so it
    // is safe to put them in the SQL. The ids are passed as "?" placeholders.
    $ids = array_column($rows, 'id');
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $links = fetch_all(
        "SELECT $idColumn AS item_id, interest_slug FROM $joinTable WHERE $idColumn IN ($placeholders) ORDER BY interest_slug",
        $ids
    );

    $interestsById = [];
    foreach ($links as $link) {
        $interestsById[$link['item_id']][] = $link['interest_slug'];
    }

    foreach ($rows as $index => $row) {
        $rows[$index]['interests'] = $interestsById[$row['id']] ?? [];
    }
    return $rows;
}

/** Replaces the interests linked to one item (used when creating/editing). */
function replace_interests(string $joinTable, string $idColumn, int $itemId, array $interests): void
{
    execute("DELETE FROM $joinTable WHERE $idColumn = ?", [$itemId]);
    foreach ($interests as $interest) {
        execute("INSERT INTO $joinTable ($idColumn, interest_slug) VALUES (?, ?)", [$itemId, $interest]);
    }
}
