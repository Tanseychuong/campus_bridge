/**
 * Aggregations for the admin analytics view. Pure functions over the
 * catalogue: the numbers describe the SAMPLE content, not real student usage.
 */
import type { Catalog } from "@/schemas/domain";
import { EVENT_CATEGORY_LABELS, OPPORTUNITY_CATEGORY_LABELS } from "@/schemas/taxonomy";
import { isClosed } from "./deadlines";
import { formatDate, startOfCampusDay } from "./dates";

export interface Datum {
  label: string;
  value: number;
}

/** Count items per key, keeping every known key (zeros included) in a fixed order. */
export function countBy<T>(
  items: T[],
  keyOf: (item: T) => string,
  labels: Record<string, string>,
): Datum[] {
  const counts = new Map<string, number>();
  for (const item of items) counts.set(keyOf(item), (counts.get(keyOf(item)) ?? 0) + 1);
  return Object.entries(labels).map(([key, label]) => ({ label, value: counts.get(key) ?? 0 }));
}

export function openOpportunitiesByCategory(catalog: Catalog, now: Date): Datum[] {
  const open = catalog.opportunities.filter(
    (o) => o.status === "published" && !isClosed(o.deadline, now),
  );
  return countBy(open, (o) => o.category, OPPORTUNITY_CATEGORY_LABELS).sort(
    (a, b) => b.value - a.value,
  );
}

export function upcomingEventsByCategory(catalog: Catalog, now: Date): Datum[] {
  const upcoming = catalog.events.filter(
    (e) => e.status === "published" && Date.parse(e.end) > now.getTime(),
  );
  return countBy(upcoming, (e) => e.category, EVENT_CATEGORY_LABELS).sort(
    (a, b) => b.value - a.value,
  );
}

/** Number of opportunity deadlines falling in each of the next `weeks` 7-day windows. */
export function deadlinesPerWeek(catalog: Catalog, now: Date, weeks = 8): Datum[] {
  const start = startOfCampusDay(now).getTime();
  const week = 7 * 86_400_000;
  const buckets: Datum[] = Array.from({ length: weeks }, (_, i) => ({
    label: `w/c ${formatDate(start + i * week, "d MMM")}`,
    value: 0,
  }));
  for (const o of catalog.opportunities) {
    if (o.status !== "published" || !o.deadline) continue;
    const index = Math.floor((Date.parse(o.deadline) - start) / week);
    if (index >= 0 && index < weeks) buckets[index].value++;
  }
  return buckets;
}
