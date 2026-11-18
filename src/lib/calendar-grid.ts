/**
 * Builds the grid of days for a month view (weeks start on Monday).
 * Pure function: no React, easy to unit test.
 *
 * Africa/Accra is UTC+0 all year, so UTC calendar maths matches campus days.
 */
export interface CalendarDay {
  key: string; // yyyy-MM-dd
  dayOfMonth: number;
  inMonth: boolean;
}

const DAY_MS = 86_400_000;

/** `month` is 0-based (0 = January), like JavaScript Dates. */
export function buildMonthGrid(year: number, month: number): CalendarDay[][] {
  const first = new Date(Date.UTC(year, month, 1));
  const mondayOffset = (first.getUTCDay() + 6) % 7; // Mon=0 … Sun=6
  const gridStart = Date.UTC(year, month, 1 - mondayOffset);
  const daysInMonth = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
  const totalCells = Math.ceil((mondayOffset + daysInMonth) / 7) * 7;

  const weeks: CalendarDay[][] = [];
  for (let i = 0; i < totalCells; i++) {
    const d = new Date(gridStart + i * DAY_MS);
    if (i % 7 === 0) weeks.push([]);
    weeks[weeks.length - 1].push({
      key: d.toISOString().slice(0, 10),
      dayOfMonth: d.getUTCDate(),
      inMonth: d.getUTCMonth() === month,
    });
  }
  return weeks;
}

export function shiftMonth(year: number, month: number, delta: number) {
  const d = new Date(Date.UTC(year, month + delta, 1));
  return { year: d.getUTCFullYear(), month: d.getUTCMonth() };
}

export const MONTH_NAMES = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December",
];
