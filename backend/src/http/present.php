<?php
/**
 * "Presenters": they turn a database row into the JSON the React app shows.
 *
 * Why not send the raw rows?
 *   - Some columns must never leave the server (password hashes, file paths).
 *   - All the logic stays in PHP: labels, dates in campus time, "5 days left",
 *     "is it saved?". React only displays what it is given, so the rules live
 *     in one place and are covered by the PHP tests.
 */

/** ['research', 'arts'] -> [['slug' => 'research', 'label' => 'Research'], ...] */
function present_interests(array $slugs): array
{
    return array_map(fn ($slug) => ['slug' => $slug, 'label' => INTEREST_LABELS[$slug] ?? $slug], $slugs);
}

/** The colour of a deadline badge: red when urgent, amber when close, grey otherwise. */
function deadline_tone(?string $deadline, DateTimeImmutable $now): string
{
    $tones = ['today' => 'danger', 'this-week' => 'danger', 'next-two-weeks' => 'warning', 'later' => 'neutral', 'rolling' => 'neutral', 'closed' => 'neutral'];
    return $tones[deadline_bucket($deadline, $now)];
}

function present_opportunity(array $o, DateTimeImmutable $now, array $profile): array
{
    // Only https links are clickable (blocks "javascript:" links even if bad data gets in).
    $sourceIsSafe = str_starts_with($o['source_url'], 'https://');

    return [
        'type'            => 'opportunity',
        'id'              => (int) $o['id'],
        'title'           => $o['title'],
        'provider'        => $o['provider'],
        'organization_id' => $o['organization_id'] === null ? null : (int) $o['organization_id'],
        'campus_name'     => $o['campus_name'] ?? null, // null: from an outside provider, open to every campus
        'category'        => $o['category'],
        'category_label'  => OPPORTUNITY_CATEGORIES[$o['category']],
        'summary'         => $o['summary'],
        'description'     => $o['description'],
        'eligibility'     => lines($o['eligibility'] ?? ''),
        'deadline'        => [
            'label' => deadline_label($o['deadline'], $now),   // "5 days left"
            'tone'  => deadline_tone($o['deadline'], $now),
            'full'  => $o['deadline'] ? format_date($o['deadline']) . ', ' . format_time($o['deadline']) . ' (Accra time)' : 'Rolling — no fixed deadline',
        ],
        'is_closed'       => is_closed($o['deadline'], $now),
        'location'        => LOCATION_MODES[$o['location_mode']] . ($o['location_place'] ? ' · ' . $o['location_place'] : ''),
        'interests'       => present_interests($o['interests']),
        'source'          => [
            'name' => $o['source_name'],
            'host' => parse_url($o['source_url'], PHP_URL_HOST) ?: 'unknown',
            'url'  => $sourceIsSafe ? $o['source_url'] : null,
        ],
        'published'       => format_date($o['published_at'], 'j M Y'),
        'is_saved'        => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true),
    ];
}

function present_event(array $e, DateTimeImmutable $now, array $profile): array
{
    return [
        'type'              => 'event',
        'id'                => (int) $e['id'],
        'title'             => $e['title'],
        'organization_id'   => (int) $e['organization_id'],
        'organization_name' => $e['organization_name'],
        'category'          => $e['category'],
        'category_label'    => EVENT_CATEGORIES[$e['category']],
        'summary'           => $e['summary'],
        'description'       => $e['description'],
        'venue'             => $e['venue'],
        'when'              => format_event_range($e['starts_at'], $e['ends_at']),  // "Fri 20 Nov, 18:30 – 20:30"
        'capacity'          => $e['capacity'] ? $e['capacity'] . ' people' : 'No limit',
        'registration'      => $e['registration'],
        'open_to'           => ($e['visibility'] ?? 'public') === 'campus' ? $e['campus_name'] . ' students only' : 'Students from every campus',
        'is_campus_only'    => ($e['visibility'] ?? 'public') === 'campus',
        'campus_name'       => $e['campus_name'] ?? null,
        'contact'           => $e['contact'],
        'interests'         => present_interests($e['interests']),
        'has_ended'         => timestamp_of($e['ends_at']) <= $now->getTimestamp(),
        'is_saved'          => in_array((int) $e['id'], $profile['saved_event_ids'], true),
    ];
}

function present_organization(array $org, array $profile): array
{
    return [
        'type'           => 'organization',
        'id'             => (int) $org['id'],
        'name'           => $org['name'],
        'short_name'     => $org['short_name'],
        // Only a plain "#rrggbb" colour reaches the page.
        'color'          => preg_match('/^#[0-9a-fA-F]{6}$/', $org['color']) ? $org['color'] : '#475569',
        'category'       => $org['category'],
        'category_label' => ORGANIZATION_CATEGORIES[$org['category']],
        'campus_name'    => $org['campus_name'] ?? null,
        'summary'        => $org['summary'],
        'description'    => $org['description'],
        'activities'     => lines($org['activities']),
        'membership'     => $org['membership'],
        'meeting_info'   => $org['meeting_info'],
        'member_count'   => (int) $org['member_count'],
        'founded_year'   => (int) $org['founded_year'],
        'contact_email'  => $org['contact_email'],
        'interests'      => present_interests($org['interests']),
        'is_following'   => in_array((int) $org['id'], $profile['followed'], true),
    ];
}

/** A recommendation from recommend(): the item, its score and the reasons for it. */
function present_recommendation(array $recommendation, DateTimeImmutable $now, array $profile): array
{
    $item = $recommendation['type'] === 'opportunity'
        ? present_opportunity($recommendation['item'], $now, $profile)
        : present_event($recommendation['item'], $now, $profile);

    return [
        'type'    => $recommendation['type'],
        'score'   => (int) $recommendation['score'],
        'reasons' => array_map(fn ($reason) => ['text' => $reason['text'], 'points' => (int) $reason['points']], $recommendation['reasons']),
        'item'    => $item,
    ];
}

/** A study resource, as shown on cards and lists. */
function present_resource(array $r): array
{
    [$statusLabel, $statusTone] = RESOURCE_STATUSES[$r['status']];
    return [
        'id'                => (int) $r['id'],
        'title'             => $r['title'],
        'description'       => $r['description'],
        'organization_name' => $r['organization_name'],
        'uploader_name'     => $r['uploader_name'],
        'original_name'     => $r['original_name'],
        'extension'         => strtoupper(pathinfo($r['original_name'], PATHINFO_EXTENSION)),
        'size'              => format_file_size((int) $r['file_size']),
        'license'           => RESOURCE_LICENSE_SHORT[$r['license']],
        'status'            => $r['status'],
        'status_label'      => $statusLabel,
        'status_tone'       => $statusTone,
        'review_note'       => $r['review_note'],
    ];
}
