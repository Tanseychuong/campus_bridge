<?php
/**
 * Review hints for administrators.
 *
 * These simple checks point a human reviewer at common problems in new
 * listings. They never approve or reject anything by themselves.
 *
 * Returns a list like [['severity' => 'critical', 'message' => '...'], ...]
 */
function moderation_flags(array $item, DateTimeImmutable $now): array
{
    $flags = [];
    $text = ($item['title'] ?? '') . ' ' . ($item['description'] ?? '');

    // Scams aimed at students often ask for a "registration fee".
    if (preg_match('/\b(pay|payment|fee|deposit|send money)\b/i', $text)) {
        $flags[] = ['severity' => 'critical', 'message' => 'Mentions payment or fees — a common sign of scams targeting students'];
    }

    if (isset($item['provider']) && preg_match('/\b(unknown|unverified|anonymous)\b/i', $item['provider'])) {
        $flags[] = ['severity' => 'critical', 'message' => 'Provider cannot be verified'];
    }

    if (array_key_exists('eligibility', $item) && trim($item['eligibility']) === '') {
        $flags[] = ['severity' => 'warning', 'message' => 'No eligibility information'];
    }

    $date = $item['deadline'] ?? $item['starts_at'] ?? null;
    if ($date !== null && timestamp_of($date) - $now->getTimestamp() < 3 * 86400) {
        $flags[] = ['severity' => 'warning', 'message' => 'Less than 3 days away — students may not have time to act'];
    }

    return $flags;
}
