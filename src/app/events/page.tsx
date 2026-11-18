import type { Metadata } from "next";
import Link from "next/link";
import { CalendarDays, CalendarX } from "lucide-react";
import { z } from "zod";
import { catalogRepository } from "@/services";
import { calendarDaysBetween, dayKey, formatDate } from "@/lib/dates";
import { EVENT_CATEGORIES, EVENT_CATEGORY_LABELS } from "@/schemas/taxonomy";
import type { CampusEvent } from "@/schemas/domain";
import { EventCard } from "@/components/features/cards";
import { LinkButton } from "@/components/ui/button";
import { EmptyState, PageHeader } from "@/components/ui/primitives";
import { cn } from "@/lib/cn";

export const metadata: Metadata = {
  title: "Campus events",
  description: "Upcoming campus events grouped by day, filterable by category.",
};

const categoryParam = z.enum(EVENT_CATEGORIES).nullable().catch(null);

function dayHeading(key: string, now: Date) {
  const days = calendarDaysBetween(now, `${key}T12:00:00Z`);
  const label = formatDate(`${key}T12:00:00Z`, "EEEE d MMMM");
  if (days === 0) return `Today · ${label}`;
  if (days === 1) return `Tomorrow · ${label}`;
  return label;
}

export default async function EventsPage({ searchParams }: PageProps<"/events">) {
  const raw = (await searchParams).category;
  const category = categoryParam.parse(typeof raw === "string" ? raw : null);
  const now = new Date();
  const catalog = await catalogRepository.getCatalog();
  const orgs = new Map(catalog.organizations.map((o) => [o.id, o]));

  const upcoming = catalog.events
    .filter((e) => Date.parse(e.end) > now.getTime())
    .filter((e) => !category || e.category === category)
    .sort((a, b) => Date.parse(a.start) - Date.parse(b.start));

  const byDay = new Map<string, CampusEvent[]>();
  for (const e of upcoming) byDay.set(dayKey(e.start), [...(byDay.get(dayKey(e.start)) ?? []), e]);

  return (
    <div className="container-page">
      <PageHeader
        eyebrow="What's on"
        title="Campus events"
        description="Upcoming events from student organizations. Mark the ones you're interested in to build your calendar."
        actions={
          <LinkButton href="/calendar" variant="secondary">
            <CalendarDays className="size-4" aria-hidden /> My calendar
          </LinkButton>
        }
      />

      <nav aria-label="Event categories" className="-mx-1 flex gap-2 overflow-x-auto pb-2">
        {[null, ...EVENT_CATEGORIES].map((c) => {
          const current = c === category;
          return (
            <Link
              key={c ?? "all"}
              href={c ? `/events?category=${c}` : "/events"}
              aria-current={current ? "page" : undefined}
              className={cn(
                "shrink-0 rounded-full px-4 py-2 text-sm font-semibold",
                current
                  ? "bg-ink text-white"
                  : "ring-line bg-white text-slate-700 ring-1 hover:bg-slate-50",
              )}
            >
              {c ? EVENT_CATEGORY_LABELS[c] : "All"}
            </Link>
          );
        })}
      </nav>

      {byDay.size === 0 ? (
        <div className="mt-6">
          <EmptyState
            icon={<CalendarX className="size-6" aria-hidden />}
            title="No upcoming events in this category"
            action={
              <LinkButton href="/events" variant="secondary">
                Show all events
              </LinkButton>
            }
          />
        </div>
      ) : (
        <div className="mt-6 space-y-10">
          {[...byDay.entries()].map(([key, events]) => (
            <section key={key} aria-labelledby={`day-${key}`}>
              <h2
                id={`day-${key}`}
                className="text-muted mb-3 text-sm font-bold tracking-wide uppercase"
              >
                {dayHeading(key, now)}
              </h2>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {events.map((e) => (
                  <EventCard key={e.id} event={e} organization={orgs.get(e.organizationId)} />
                ))}
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}
