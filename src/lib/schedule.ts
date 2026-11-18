/**
 * Schedule intelligence: detect overlapping events.
 *
 * Two time ranges [aStart, aEnd) and [bStart, bEnd) overlap exactly when
 *   aStart < bEnd  AND  bStart < aEnd.
 * Touching ranges (one ends at 18:00, the next starts at 18:00) do NOT clash.
 *
 * Algorithm (sweep line): sort by start time and keep a list of "active"
 * events that have not ended yet. Each new event clashes with every active
 * event. Cost: O(n log n + k), where k is the number of clashes.
 */
export interface TimeRange {
  id: string;
  start: string;
  end: string;
}

export function overlaps(a: TimeRange, b: TimeRange): boolean {
  return Date.parse(a.start) < Date.parse(b.end) && Date.parse(b.start) < Date.parse(a.end);
}

export function findConflicts<T extends TimeRange>(events: T[]): [T, T][] {
  const sorted = [...events].sort((a, b) => Date.parse(a.start) - Date.parse(b.start));
  const conflicts: [T, T][] = [];
  let active: T[] = [];
  for (const event of sorted) {
    const start = Date.parse(event.start);
    active = active.filter((other) => Date.parse(other.end) > start);
    for (const other of active) conflicts.push([other, event]);
    active.push(event);
  }
  return conflicts;
}

/** Map each event id to the ids of the events it clashes with. */
export function conflictMap<T extends TimeRange>(events: T[]): Map<string, string[]> {
  const map = new Map<string, string[]>();
  for (const [a, b] of findConflicts(events)) {
    map.set(a.id, [...(map.get(a.id) ?? []), b.id]);
    map.set(b.id, [...(map.get(b.id) ?? []), a.id]);
  }
  return map;
}
