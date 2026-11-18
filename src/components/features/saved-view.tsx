"use client";

import Link from "next/link";
import { useState } from "react";
import { BookmarkX, CalendarX, Trash2 } from "lucide-react";
import type { Catalog } from "@/schemas/domain";
import { DEADLINE_BUCKET_LABELS, deadlineLabel, groupByDeadline } from "@/lib/deadlines";
import { formatEventRange } from "@/lib/dates";
import { OPPORTUNITY_CATEGORY_LABELS } from "@/schemas/taxonomy";
import { useUserData } from "@/state/hooks";
import { Button, LinkButton } from "@/components/ui/button";
import {
  Badge,
  EmptyState,
  Notice,
  SectionHeading,
  SkeletonList,
} from "@/components/ui/primitives";
import { ExportCalendarButton } from "./export-calendar-button";

export function SavedView({ catalog }: { catalog: Catalog }) {
  const { data, ready, toggleSavedOpportunity, toggleSavedEvent } = useUserData();
  const [now] = useState(() => new Date());

  if (!ready) return <SkeletonList count={3} label="Loading saved items" />;

  const opportunities = catalog.opportunities.filter((o) =>
    data.savedOpportunityIds.includes(o.id),
  );
  const events = catalog.events
    .filter((e) => data.savedEventIds.includes(e.id))
    .sort((a, b) => Date.parse(a.start) - Date.parse(b.start));
  const groups = groupByDeadline(opportunities, now);
  // Saved ids may point at items that no longer exist (removed from the catalogue).
  const missing =
    data.savedOpportunityIds.length -
    opportunities.length +
    (data.savedEventIds.length - events.length);

  return (
    <div className="space-y-12">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-muted text-sm">
          {opportunities.length} opportunit{opportunities.length === 1 ? "y" : "ies"} ·{" "}
          {events.length} event
          {events.length === 1 ? "" : "s"}
        </p>
        <ExportCalendarButton events={events} opportunities={opportunities} />
      </div>

      {missing > 0 && (
        <Notice tone="warning">
          {missing} saved item{missing === 1 ? " is" : "s are"} no longer available and{" "}
          {missing === 1 ? "has" : "have"} been hidden.
        </Notice>
      )}

      <section aria-labelledby="saved-opps-heading">
        <SectionHeading
          id="saved-opps-heading"
          title="Opportunities by deadline"
          description="Grouped by urgency so the most pressing deadlines come first."
        />
        {opportunities.length === 0 ? (
          <EmptyState
            icon={<BookmarkX className="size-6" aria-hidden />}
            title="No saved opportunities"
            description="Use the Save button on any opportunity to track its deadline here."
            action={<LinkButton href="/explore?type=opportunity">Find opportunities</LinkButton>}
          />
        ) : (
          <div className="space-y-6">
            {groups.map((group) => (
              <div key={group.bucket}>
                <h3
                  className={`mb-2 text-sm font-bold ${group.bucket === "today" || group.bucket === "this-week" ? "text-red-700" : "text-muted"}`}
                >
                  {DEADLINE_BUCKET_LABELS[group.bucket]} ({group.items.length})
                </h3>
                <ul className="card divide-line divide-y">
                  {group.items.map((o) => (
                    <li
                      key={o.id}
                      className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                      <div>
                        <Link
                          href={`/opportunities/${o.id}`}
                          className="hover:text-brand-700 font-semibold"
                        >
                          {o.title}
                        </Link>
                        <p className="text-muted mt-0.5 flex flex-wrap items-center gap-2 text-sm">
                          {o.provider}
                          <Badge>{OPPORTUNITY_CATEGORY_LABELS[o.category]}</Badge>
                          <Badge
                            tone={
                              group.bucket === "today" || group.bucket === "this-week"
                                ? "danger"
                                : "neutral"
                            }
                          >
                            {deadlineLabel(o.deadline, now)}
                          </Badge>
                        </p>
                      </div>
                      <Button
                        variant="danger"
                        size="sm"
                        onClick={() => toggleSavedOpportunity(o.id)}
                        aria-label={`Remove ${o.title} from saved items`}
                      >
                        <Trash2 className="size-4" aria-hidden /> Remove
                      </Button>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        )}
      </section>

      <section aria-labelledby="saved-events-heading">
        <SectionHeading
          id="saved-events-heading"
          title="Events you're interested in"
          action={
            <LinkButton href="/calendar" variant="ghost" size="sm">
              View as calendar
            </LinkButton>
          }
        />
        {events.length === 0 ? (
          <EmptyState
            icon={<CalendarX className="size-6" aria-hidden />}
            title="No events yet"
            description="Mark events as “I’m interested” to add them here and to your calendar."
            action={<LinkButton href="/events">Browse events</LinkButton>}
          />
        ) : (
          <ul className="card divide-line divide-y">
            {events.map((e) => {
              const past = Date.parse(e.end) <= now.getTime();
              return (
                <li
                  key={e.id}
                  className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div>
                    <Link href={`/events/${e.id}`} className="hover:text-brand-700 font-semibold">
                      {e.title}
                    </Link>
                    <p className="text-muted mt-0.5 text-sm">
                      {formatEventRange(e.start, e.end)} · {e.venue} {past && <Badge>Ended</Badge>}
                    </p>
                  </div>
                  <Button
                    variant="danger"
                    size="sm"
                    onClick={() => toggleSavedEvent(e.id)}
                    aria-label={`Remove ${e.title} from my calendar`}
                  >
                    <Trash2 className="size-4" aria-hidden /> Remove
                  </Button>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </div>
  );
}
