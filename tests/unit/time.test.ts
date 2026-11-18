import { describe, expect, it } from "vitest";
import { calendarDaysBetween, dayKey, formatEventRange } from "@/lib/dates";
import { bucketDeadline, compareDeadlines, deadlineLabel, groupByDeadline } from "@/lib/deadlines";
import { conflictMap, findConflicts, overlaps } from "@/lib/schedule";
import { buildMonthGrid, shiftMonth } from "@/lib/calendar-grid";
import { NOW, daysFromNow } from "../factories";

describe("dates (Africa/Accra)", () => {
  it("counts calendar days, not 24-hour periods", () => {
    expect(calendarDaysBetween("2026-11-18T23:00:00Z", "2026-11-19T01:00:00Z")).toBe(1);
    expect(calendarDaysBetween("2026-11-18T01:00:00Z", "2026-11-18T23:00:00Z")).toBe(0);
  });
  it("formats event ranges", () => {
    expect(formatEventRange("2026-11-20T18:30:00Z", "2026-11-20T20:30:00Z")).toBe(
      "Fri 20 Nov, 18:30 – 20:30",
    );
  });
  it("produces day keys in the campus zone", () => {
    expect(dayKey("2026-11-20T23:30:00Z")).toBe("2026-11-20");
  });
});

describe("deadlines", () => {
  it("buckets deadlines by urgency", () => {
    expect(bucketDeadline(null, NOW)).toBe("rolling");
    expect(bucketDeadline(daysFromNow(-1), NOW)).toBe("closed");
    expect(bucketDeadline(daysFromNow(0), NOW)).toBe("today");
    expect(bucketDeadline(daysFromNow(7), NOW)).toBe("this-week");
    expect(bucketDeadline(daysFromNow(8), NOW)).toBe("next-two-weeks");
    expect(bucketDeadline(daysFromNow(15), NOW)).toBe("later");
  });
  it("treats a deadline earlier today as closed", () => {
    expect(bucketDeadline("2026-11-18T08:00:00.000Z", NOW)).toBe("closed");
  });
  it("labels deadlines for humans", () => {
    expect(deadlineLabel(daysFromNow(0), NOW)).toBe("Closes today");
    expect(deadlineLabel(daysFromNow(1), NOW)).toBe("Closes tomorrow");
    expect(deadlineLabel(daysFromNow(5), NOW)).toBe("5 days left");
    expect(deadlineLabel(null, NOW)).toBe("Rolling deadline");
  });
  it("sorts soonest first with rolling deadlines last", () => {
    const items = [{ deadline: null }, { deadline: daysFromNow(9) }, { deadline: daysFromNow(2) }];
    expect([...items].sort(compareDeadlines).map((i) => i.deadline)).toEqual([
      daysFromNow(2),
      daysFromNow(9),
      null,
    ]);
  });
  it("groups in a fixed bucket order", () => {
    const groups = groupByDeadline(
      [
        { deadline: daysFromNow(20) },
        { deadline: daysFromNow(2) },
        { deadline: null },
        { deadline: daysFromNow(4) },
      ],
      NOW,
    );
    expect(groups.map((g) => g.bucket)).toEqual(["this-week", "later", "rolling"]);
    expect(groups[0].items.map((i) => i.deadline)).toEqual([daysFromNow(2), daysFromNow(4)]);
  });
});

describe("schedule conflicts", () => {
  const ev = (id: string, start: string, end: string) => ({
    id,
    start: `2026-11-20T${start}:00Z`,
    end: `2026-11-20T${end}:00Z`,
  });

  it("detects overlapping ranges", () => {
    expect(overlaps(ev("a", "17:00", "19:00"), ev("b", "18:00", "19:30"))).toBe(true);
  });
  it("does not treat back-to-back events as a clash", () => {
    expect(overlaps(ev("a", "17:00", "18:00"), ev("b", "18:00", "19:00"))).toBe(false);
  });
  it("finds every clashing pair, including an event inside another", () => {
    const events = [
      ev("long", "09:00", "17:00"),
      ev("lunch", "12:00", "13:00"),
      ev("talk", "12:30", "14:00"),
      ev("evening", "18:00", "19:00"),
    ];
    const pairs = findConflicts(events).map(([a, b]) => `${a.id}-${b.id}`);
    expect(pairs.sort()).toEqual(["long-lunch", "long-talk", "lunch-talk"]);
  });
  it("builds a symmetric conflict map", () => {
    const map = conflictMap([ev("a", "10:00", "11:00"), ev("b", "10:30", "11:30")]);
    expect(map.get("a")).toEqual(["b"]);
    expect(map.get("b")).toEqual(["a"]);
  });
  it("handles empty input", () => {
    expect(findConflicts([])).toEqual([]);
  });
});

describe("month grid", () => {
  it("starts weeks on Monday and covers the whole month", () => {
    const grid = buildMonthGrid(2026, 10); // November 2026 starts on a Sunday
    expect(grid[0][0].key).toBe("2026-10-26");
    expect(grid[0][6]).toMatchObject({ key: "2026-11-01", inMonth: true });
    expect(grid.flat().filter((d) => d.inMonth)).toHaveLength(30);
    expect(grid.every((w) => w.length === 7)).toBe(true);
  });
  it("shifts months across year boundaries", () => {
    expect(shiftMonth(2026, 11, 1)).toEqual({ year: 2027, month: 0 });
    expect(shiftMonth(2027, 0, -1)).toEqual({ year: 2026, month: 11 });
  });
});
