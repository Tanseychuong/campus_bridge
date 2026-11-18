"use client";

/**
 * Month calendar of the student's saved events and saved deadlines, with
 * clash detection. On small screens the agenda list below the grid is the
 * main view; the grid stays compact.
 */
import Link from "next/link";
import { useMemo, useState } from "react";
import { AlertTriangle, ChevronLeft, ChevronRight, Clock } from "lucide-react";
import type { Catalog } from "@/schemas/domain";
import { buildMonthGrid, MONTH_NAMES, shiftMonth } from "@/lib/calendar-grid";
import { dayKey, formatDate, formatEventRange, formatTime } from "@/lib/dates";
import { conflictMap, findConflicts } from "@/lib/schedule";
import { useUserData } from "@/state/hooks";
import { Button, LinkButton } from "@/components/ui/button";
import { EmptyState, Notice, SkeletonList } from "@/components/ui/primitives";
import { cn } from "@/lib/cn";
import { ExportCalendarButton } from "./export-calendar-button";

const WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

type Entry =
  | {
      kind: "event";
      id: string;
      title: string;
      start: string;
      end: string;
      href: string;
      clash: boolean;
    }
  | { kind: "deadline"; id: string; title: string; start: string; href: string };

export function CalendarView({ catalog }: { catalog: Catalog }) {
  const { data, ready } = useUserData();
  const [now] = useState(() => new Date());
  const today = dayKey(now);
  const [cursor, setCursor] = useState(() => {
    const [y, m] = today.split("-").map(Number);
    return { year: y, month: m - 1 };
  });

  const events = useMemo(
    () =>
      catalog.events
        .filter((e) => data.savedEventIds.includes(e.id))
        .sort((a, b) => Date.parse(a.start) - Date.parse(b.start)),
    [catalog, data.savedEventIds],
  );
  const opportunities = useMemo(
    () => catalog.opportunities.filter((o) => data.savedOpportunityIds.includes(o.id)),
    [catalog, data.savedOpportunityIds],
  );
  const clashes = useMemo(() => conflictMap(events), [events]);
  const clashPairs = useMemo(
    () => findConflicts(events.filter((e) => Date.parse(e.end) > now.getTime())),
    [events, now],
  );

  const entriesByDay = useMemo(() => {
    const map = new Map<string, Entry[]>();
    const add = (key: string, entry: Entry) => map.set(key, [...(map.get(key) ?? []), entry]);
    for (const e of events) {
      add(dayKey(e.start), {
        kind: "event",
        id: e.id,
        title: e.title,
        start: e.start,
        end: e.end,
        href: `/events/${e.id}`,
        clash: clashes.has(e.id),
      });
    }
    for (const o of opportunities) {
      if (o.deadline)
        add(dayKey(o.deadline), {
          kind: "deadline",
          id: o.id,
          title: o.title,
          start: o.deadline,
          href: `/opportunities/${o.id}`,
        });
    }
    for (const list of map.values()) list.sort((a, b) => Date.parse(a.start) - Date.parse(b.start));
    return map;
  }, [events, opportunities, clashes]);

  if (!ready) return <SkeletonList count={2} label="Loading calendar" />;

  const grid = buildMonthGrid(cursor.year, cursor.month);
  const monthPrefix = `${cursor.year}-${String(cursor.month + 1).padStart(2, "0")}`;
  const monthDays = [...entriesByDay.keys()].filter((k) => k.startsWith(monthPrefix)).sort();
  const monthLabel = `${MONTH_NAMES[cursor.month]} ${cursor.year}`;

  if (events.length === 0 && opportunities.length === 0) {
    return (
      <EmptyState
        title="Your calendar is empty"
        description="Mark events as “I’m interested” and save opportunities — their dates and deadlines will appear here."
        action={<LinkButton href="/events">Browse events</LinkButton>}
      />
    );
  }

  return (
    <div className="space-y-6">
      {clashPairs.length > 0 && (
        <Notice
          tone="warning"
          title={`${clashPairs.length} schedule clash${clashPairs.length === 1 ? "" : "es"} detected`}
        >
          <ul className="list-disc space-y-1 pl-4">
            {clashPairs.map(([a, b]) => (
              <li key={`${a.id}-${b.id}`}>
                <strong>{a.title}</strong> ({formatTime(a.start)}–{formatTime(a.end)}) overlaps{" "}
                <strong>{b.title}</strong> ({formatTime(b.start)}–{formatTime(b.end)}) on{" "}
                {formatDate(a.start, "EEE d MMM")}.
              </li>
            ))}
          </ul>
        </Notice>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Button
            variant="secondary"
            size="sm"
            aria-label="Previous month"
            onClick={() => setCursor(shiftMonth(cursor.year, cursor.month, -1))}
          >
            <ChevronLeft className="size-4" aria-hidden />
          </Button>
          <h2 className="min-w-40 text-center text-lg font-bold" aria-live="polite">
            {monthLabel}
          </h2>
          <Button
            variant="secondary"
            size="sm"
            aria-label="Next month"
            onClick={() => setCursor(shiftMonth(cursor.year, cursor.month, 1))}
          >
            <ChevronRight className="size-4" aria-hidden />
          </Button>
        </div>
        <ExportCalendarButton events={events} opportunities={opportunities} />
      </div>

      {/* Month grid: a visual overview. The agenda list below carries the same information for screen readers. */}
      <div className="card overflow-hidden" aria-hidden>
        <div className="border-line text-muted grid grid-cols-7 border-b bg-slate-50 text-center text-xs font-semibold">
          {WEEKDAYS.map((d) => (
            <div key={d} className="py-2">
              {d}
            </div>
          ))}
        </div>
        {grid.map((week) => (
          <div key={week[0].key} className="border-line grid grid-cols-7 border-b last:border-b-0">
            {week.map((day) => {
              const entries = entriesByDay.get(day.key) ?? [];
              return (
                <div
                  key={day.key}
                  className={cn(
                    "border-line min-h-16 border-r p-1 last:border-r-0 sm:min-h-24 sm:p-1.5",
                    !day.inMonth && "bg-slate-50/70 text-slate-400",
                  )}
                >
                  <span
                    className={cn(
                      "inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold",
                      day.key === today && "bg-brand-600 text-white",
                    )}
                  >
                    {day.dayOfMonth}
                  </span>
                  <div className="mt-1 space-y-1">
                    {entries.slice(0, 3).map((e) => (
                      <div
                        key={`${e.kind}-${e.id}`}
                        className={cn(
                          "truncate rounded px-1 py-0.5 text-[0.65rem] leading-tight font-medium",
                          e.kind === "deadline"
                            ? "bg-gold-100 text-gold-800"
                            : e.clash
                              ? "bg-red-100 text-red-800"
                              : "bg-brand-100 text-brand-800",
                        )}
                      >
                        <span className="hidden sm:inline">
                          {e.kind === "deadline" ? "Due: " : `${formatTime(e.start)} `}
                        </span>
                        {e.title}
                      </div>
                    ))}
                    {entries.length > 3 && (
                      <div className="text-muted text-[0.65rem]">+{entries.length - 3} more</div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        ))}
      </div>

      <section aria-labelledby="agenda-heading">
        <h2 id="agenda-heading" className="mb-3 text-lg font-bold">
          Agenda for {monthLabel}
        </h2>
        {monthDays.length === 0 ? (
          <p className="text-muted text-sm">Nothing saved in this month.</p>
        ) : (
          <ol className="space-y-4">
            {monthDays.map((key) => (
              <li key={key}>
                <h3
                  className={cn(
                    "mb-2 text-sm font-bold",
                    key === today ? "text-brand-700" : "text-muted",
                  )}
                >
                  {formatDate(`${key}T12:00:00Z`, "EEEE d MMMM")}
                  {key === today && " (today)"}
                </h3>
                <ul className="card divide-line divide-y">
                  {entriesByDay.get(key)!.map((e) => (
                    <li key={`${e.kind}-${e.id}`} className="flex items-start gap-3 p-3">
                      <span
                        className={cn(
                          "mt-1 size-2.5 shrink-0 rounded-full",
                          e.kind === "deadline"
                            ? "bg-gold-400"
                            : e.kind === "event" && e.clash
                              ? "bg-red-600"
                              : "bg-brand-600",
                        )}
                        aria-hidden
                      />
                      <div>
                        <Link href={e.href} className="hover:text-brand-700 font-semibold">
                          {e.kind === "deadline" ? `Deadline: ${e.title}` : e.title}
                        </Link>
                        <p className="text-muted text-sm">
                          {e.kind === "deadline" ? (
                            <span className="inline-flex items-center gap-1">
                              <Clock className="size-3.5" aria-hidden /> Closes at{" "}
                              {formatTime(e.start)}
                            </span>
                          ) : (
                            formatEventRange(e.start, e.end)
                          )}
                        </p>
                        {e.kind === "event" && e.clash && (
                          <p className="mt-1 flex items-center gap-1 text-xs font-semibold text-red-700">
                            <AlertTriangle className="size-3.5" aria-hidden /> Clashes with another
                            saved event
                          </p>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              </li>
            ))}
          </ol>
        )}
      </section>
    </div>
  );
}
