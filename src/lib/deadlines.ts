/**
 * Deadline intelligence: classify opportunities by how urgent they are.
 */
import { calendarDaysBetween } from "./dates";

export type DeadlineBucket =
  "closed" | "today" | "this-week" | "next-two-weeks" | "later" | "rolling";

export const DEADLINE_BUCKET_ORDER: DeadlineBucket[] = [
  "today",
  "this-week",
  "next-two-weeks",
  "later",
  "rolling",
  "closed",
];

export const DEADLINE_BUCKET_LABELS: Record<DeadlineBucket, string> = {
  today: "Due today",
  "this-week": "Due in the next 7 days",
  "next-two-weeks": "Due in 8–14 days",
  later: "Due later",
  rolling: "Rolling / no fixed deadline",
  closed: "Closed",
};

/** Days until the deadline (calendar days, campus zone). null = rolling. */
export function daysUntilDeadline(deadline: string | null, now: Date): number | null {
  if (deadline === null) return null;
  return calendarDaysBetween(now, deadline);
}

export function isClosed(deadline: string | null, now: Date): boolean {
  return deadline !== null && Date.parse(deadline) < now.getTime();
}

export function bucketDeadline(deadline: string | null, now: Date): DeadlineBucket {
  if (deadline === null) return "rolling";
  if (isClosed(deadline, now)) return "closed";
  const days = calendarDaysBetween(now, deadline);
  if (days <= 0) return "today";
  if (days <= 7) return "this-week";
  if (days <= 14) return "next-two-weeks";
  return "later";
}

/** Sort comparator: soonest deadline first, rolling deadlines last. */
export function compareDeadlines(
  a: { deadline: string | null },
  b: { deadline: string | null },
): number {
  if (a.deadline === b.deadline) return 0;
  if (a.deadline === null) return 1;
  if (b.deadline === null) return -1;
  return Date.parse(a.deadline) - Date.parse(b.deadline);
}

/** Group items by deadline bucket, each group sorted soonest-first. */
export function groupByDeadline<T extends { deadline: string | null }>(
  items: T[],
  now: Date,
): { bucket: DeadlineBucket; items: T[] }[] {
  const groups = new Map<DeadlineBucket, T[]>();
  for (const item of items) {
    const bucket = bucketDeadline(item.deadline, now);
    groups.set(bucket, [...(groups.get(bucket) ?? []), item]);
  }
  return DEADLINE_BUCKET_ORDER.filter((b) => groups.has(b)).map((bucket) => ({
    bucket,
    items: [...(groups.get(bucket) ?? [])].sort(compareDeadlines),
  }));
}

/** Short human label such as "Closes today", "5 days left", "Closed". */
export function deadlineLabel(deadline: string | null, now: Date): string {
  if (deadline === null) return "Rolling deadline";
  if (isClosed(deadline, now)) return "Closed";
  const days = calendarDaysBetween(now, deadline);
  if (days <= 0) return "Closes today";
  if (days === 1) return "Closes tomorrow";
  return `${days} days left`;
}
