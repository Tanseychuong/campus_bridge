/**
 * Date & time helpers.
 *
 * All dates are stored as ISO-8601 strings with an offset (usually UTC "Z").
 * All dates are DISPLAYED and compared by calendar day in the campus time
 * zone, Africa/Accra (UTC+0, no daylight saving). Being explicit about the
 * zone means the app behaves the same on a server in another country or on a
 * visiting student's laptop.
 */
import { TZDate } from "@date-fns/tz";
import { differenceInCalendarDays, format } from "date-fns";

export const CAMPUS_TIME_ZONE = "Africa/Accra";

type DateInput = string | number | Date;

/** Convert any date input to a Date that does its maths in the campus zone. */
export function inCampusZone(value: DateInput): TZDate {
  const time = typeof value === "string" ? Date.parse(value) : value;
  return new TZDate(time instanceof Date ? time.getTime() : time, CAMPUS_TIME_ZONE);
}

/** Midnight at the start of the given day, in the campus zone. */
export function startOfCampusDay(value: DateInput): TZDate {
  const d = inCampusZone(value);
  d.setHours(0, 0, 0, 0);
  return d;
}

/**
 * Whole calendar days from `from` to `to` in the campus zone.
 * e.g. from Mon 23:00 to Tue 01:00 is 1 day (different calendar days).
 */
export function calendarDaysBetween(from: DateInput, to: DateInput): number {
  return differenceInCalendarDays(inCampusZone(to), inCampusZone(from));
}

/** yyyy-MM-dd key for grouping by day in the campus zone. */
export function dayKey(value: DateInput): string {
  return format(inCampusZone(value), "yyyy-MM-dd");
}

export function formatDate(value: DateInput, pattern = "EEE d MMM yyyy"): string {
  return format(inCampusZone(value), pattern);
}

export function formatTime(value: DateInput): string {
  return format(inCampusZone(value), "HH:mm");
}

/** "Thu 20 Nov, 18:30 – 20:30" (or a two-day range if it crosses midnight). */
export function formatEventRange(start: DateInput, end: DateInput): string {
  const sameDay = dayKey(start) === dayKey(end);
  if (sameDay) {
    return `${formatDate(start, "EEE d MMM")}, ${formatTime(start)} – ${formatTime(end)}`;
  }
  return `${formatDate(start, "EEE d MMM, HH:mm")} – ${formatDate(end, "EEE d MMM, HH:mm")}`;
}

/** "today", "tomorrow", "in 5 days", "yesterday", "3 days ago". */
export function relativeDays(days: number): string {
  if (days === 0) return "today";
  if (days === 1) return "tomorrow";
  if (days === -1) return "yesterday";
  return days > 0 ? `in ${days} days` : `${-days} days ago`;
}
