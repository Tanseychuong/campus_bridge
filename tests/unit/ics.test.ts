import { describe, expect, it } from "vitest";
import { buildIcsCalendar, escapeIcsText, foldLine, toIcsUtc } from "@/lib/ics";
import { NOW } from "../factories";

describe("iCalendar export", () => {
  it("escapes special characters", () => {
    expect(escapeIcsText("a,b;c\\d\ne")).toBe("a\\,b\\;c\\\\d\\ne");
  });
  it("formats UTC timestamps", () => {
    expect(toIcsUtc("2026-11-20T18:30:00.000Z")).toBe("20261120T183000Z");
  });
  it("folds long lines at 75 octets", () => {
    const folded = foldLine("SUMMARY:" + "x".repeat(200));
    for (const line of folded.split("\r\n")) {
      expect(new TextEncoder().encode(line).length).toBeLessThanOrEqual(75);
    }
    expect(folded.replace(/\r\n /g, "")).toBe("SUMMARY:" + "x".repeat(200));
  });
  it("builds a valid calendar with events and all-day deadlines", () => {
    const ics = buildIcsCalendar(
      [
        {
          uid: "e1",
          title: "Workshop, part 1",
          location: "Lab 2",
          start: "2026-11-20T18:30:00Z",
          end: "2026-11-20T20:30:00Z",
        },
      ],
      [{ uid: "o1", title: "Scholarship", deadline: "2026-11-30T23:59:00Z" }],
      NOW,
    );
    expect(ics.startsWith("BEGIN:VCALENDAR\r\n")).toBe(true);
    expect(ics.endsWith("END:VCALENDAR\r\n")).toBe(true);
    expect(ics).toContain("SUMMARY:Workshop\\, part 1");
    expect(ics).toContain("DTSTART:20261120T183000Z");
    expect(ics).toContain("DTSTART;VALUE=DATE:20261130");
    expect(ics).toContain("DTEND;VALUE=DATE:20261201");
    expect(ics.match(/BEGIN:VEVENT/g)).toHaveLength(2);
  });
});
