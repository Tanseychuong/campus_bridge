<?php
/**
 * Downloads the student's saved events and deadlines as an .ics file,
 * which Outlook, Google Calendar and Apple Calendar can import.
 */
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$now = now_on_campus();
$profile = get_student_profile((int) $user['id']);

$events = array_filter(get_events(), fn ($e) => in_array((int) $e['id'], $profile['saved_event_ids'], true));
$deadlines = array_filter(get_opportunities(), fn ($o) => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true) && $o['deadline'] !== null);

// These headers tell the browser "this is a calendar file, download it".
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="campusbridge.ics"');
echo build_ics_calendar($events, $deadlines, $now);
