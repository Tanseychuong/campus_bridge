/**
 * iCalendar (.ics, RFC 5545) export so students can add saved events and
 * deadlines to Outlook, Google Calendar or Apple Calendar.
 */
import { dayKey } from "./dates";

export interface IcsEvent {
  uid: string;
  title: string;
  description?: string;
  location?: string;
  start: string;
  end: string;
}

export interface IcsDeadline {
  uid: string;
  title: string;
  deadline: string;
}

/** Escape text per RFC 5545 §3.3.11. */
export function escapeIcsText(value: string): string {
  return value
    .replace(/\\/g, "\\\\")
    .replace(/;/g, "\\;")
    .replace(/,/g, "\\,")
    .replace(/\r?\n/g, "\\n");
}

/** 2026-11-20T18:30:00.000Z -> 20261120T183000Z */
export function toIcsUtc(iso: string): string {
  return new Date(iso)
    .toISOString()
    .replace(/[-:]/g, "")
    .replace(/\.\d{3}/, "");
}

/** Fold lines longer than 75 octets (RFC 5545 §3.1). */
export function foldLine(line: string): string {
  const encoder = new TextEncoder();
  if (encoder.encode(line).length <= 75) return line;
  const parts: string[] = [];
  let current = "";
  let limit = 75;
  for (const char of line) {
    if (encoder.encode(current + char).length > limit) {
      parts.push(current);
      current = char;
      limit = 74; // continuation lines begin with a space, which uses one octet
    } else {
      current += char;
    }
  }
  parts.push(current);
  return parts.join("\r\n ");
}

function nextDay(yyyyMmDd: string): string {
  const next = new Date(Date.parse(`${yyyyMmDd}T00:00:00Z`) + 86_400_000);
  return next.toISOString().slice(0, 10);
}

export function buildIcsCalendar(
  events: IcsEvent[],
  deadlines: IcsDeadline[],
  now: Date = new Date(),
): string {
  const stamp = toIcsUtc(now.toISOString());
  const lines: string[] = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//CampusBridge//Student Planner//EN",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
  ];

  for (const e of events) {
    lines.push(
      "BEGIN:VEVENT",
      `UID:${e.uid}@campusbridge.local`,
      `DTSTAMP:${stamp}`,
      `DTSTART:${toIcsUtc(e.start)}`,
      `DTEND:${toIcsUtc(e.end)}`,
      `SUMMARY:${escapeIcsText(e.title)}`,
    );
    if (e.location) lines.push(`LOCATION:${escapeIcsText(e.location)}`);
    if (e.description) lines.push(`DESCRIPTION:${escapeIcsText(e.description)}`);
    lines.push("END:VEVENT");
  }

  for (const d of deadlines) {
    // An all-day entry on the deadline's calendar date (campus time zone).
    const day = dayKey(d.deadline);
    lines.push(
      "BEGIN:VEVENT",
      `UID:${d.uid}@campusbridge.local`,
      `DTSTAMP:${stamp}`,
      `DTSTART;VALUE=DATE:${day.replace(/-/g, "")}`,
      `DTEND;VALUE=DATE:${nextDay(day).replace(/-/g, "")}`,
      `SUMMARY:${escapeIcsText(`Deadline: ${d.title}`)}`,
      "END:VEVENT",
    );
  }

  lines.push("END:VCALENDAR");
  return lines.map(foldLine).join("\r\n") + "\r\n";
}
