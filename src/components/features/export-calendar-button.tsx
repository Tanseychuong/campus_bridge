"use client";

import { Download } from "lucide-react";
import type { CampusEvent, Opportunity } from "@/schemas/domain";
import { buildIcsCalendar } from "@/lib/ics";
import { downloadTextFile } from "@/lib/download";
import { Button } from "@/components/ui/button";

/** Exports saved events and deadlines as an .ics file for any calendar app. */
export function ExportCalendarButton({
  events,
  opportunities,
}: {
  events: CampusEvent[];
  opportunities: Opportunity[];
}) {
  const deadlines = opportunities.filter((o) => o.deadline !== null);
  const empty = events.length === 0 && deadlines.length === 0;

  function exportIcs() {
    const ics = buildIcsCalendar(
      events.map((e) => ({
        uid: e.id,
        title: e.title,
        description: e.summary,
        location: e.venue,
        start: e.start,
        end: e.end,
      })),
      deadlines.map((o) => ({ uid: o.id, title: o.title, deadline: o.deadline! })),
    );
    downloadTextFile("campusbridge.ics", ics, "text/calendar;charset=utf-8");
  }

  return (
    <Button
      variant="secondary"
      onClick={exportIcs}
      disabled={empty}
      title={empty ? "Save something first" : undefined}
    >
      <Download className="size-4" aria-hidden />
      Export to calendar (.ics)
    </Button>
  );
}
