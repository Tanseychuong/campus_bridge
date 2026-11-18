"use client";

/**
 * Personal dashboard. The server passes in the public catalogue; this client
 * component combines it with the student's local preferences (interests,
 * saved items, follows) and runs the pure business logic:
 * recommend(), deadline bucketing and conflict detection.
 */
import Link from "next/link";
import { useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowRight,
  Bookmark,
  CalendarClock,
  CalendarDays,
  Sparkles,
} from "lucide-react";
import type { Catalog } from "@/schemas/domain";
import { recommend } from "@/lib/recommend/score";
import { compareDeadlines, deadlineLabel, isClosed } from "@/lib/deadlines";
import { calendarDaysBetween, formatEventRange } from "@/lib/dates";
import { conflictMap, findConflicts } from "@/lib/schedule";
import { useUserData } from "@/state/hooks";
import { InterestPicker } from "./interest-picker";
import { RecommendationCard } from "./recommendation-card";
import { LinkButton } from "@/components/ui/button";
import { Badge, EmptyState, SectionHeading, SkeletonList } from "@/components/ui/primitives";
import { OrgAvatar } from "@/components/ui/org-avatar";

export function DashboardView({ catalog }: { catalog: Catalog }) {
  const { data, ready, setInterests } = useUserData();
  const [now] = useState(() => new Date());
  const orgs = useMemo(() => new Map(catalog.organizations.map((o) => [o.id, o])), [catalog]);

  const recommendations = useMemo(
    () =>
      recommend({
        ...catalog,
        profile: data,
        now,
        limit: 6,
      }),
    [catalog, data, now],
  );

  const savedOpps = catalog.opportunities.filter((o) => data.savedOpportunityIds.includes(o.id));
  const upcomingDeadlines = savedOpps
    .filter((o) => o.deadline && !isClosed(o.deadline, now))
    .sort(compareDeadlines);
  const dueThisWeek = upcomingDeadlines.filter((o) => calendarDaysBetween(now, o.deadline!) <= 7);
  const myEvents = catalog.events
    .filter((e) => data.savedEventIds.includes(e.id) && Date.parse(e.end) > now.getTime())
    .sort((a, b) => Date.parse(a.start) - Date.parse(b.start));
  const clashes = conflictMap(myEvents);
  const clashCount = findConflicts(myEvents).length;
  const followed = catalog.organizations.filter((o) => data.followedOrganizationIds.includes(o.id));

  if (!ready) return <SkeletonList count={4} label="Loading your dashboard" />;

  const stats = [
    { label: "Saved opportunities", value: savedOpps.length, icon: Bookmark, href: "/saved" },
    {
      label: "Deadlines in 7 days",
      value: dueThisWeek.length,
      icon: CalendarClock,
      href: "/saved",
      alert: dueThisWeek.length > 0,
    },
    { label: "Events in calendar", value: myEvents.length, icon: CalendarDays, href: "/calendar" },
    {
      label: "Schedule clashes",
      value: clashCount,
      icon: AlertTriangle,
      href: "/calendar",
      alert: clashCount > 0,
    },
  ];

  return (
    <div className="space-y-12">
      {data.interests.length === 0 && (
        <section
          className="card border-brand-200 to-brand-50 bg-gradient-to-br from-white p-6"
          aria-label="Get started"
        >
          <p className="text-brand-700 flex items-center gap-2 text-sm font-bold">
            <Sparkles className="size-4" aria-hidden /> Get started
          </p>
          <div className="mt-3">
            <InterestPicker
              value={data.interests}
              onChange={setInterests}
              legend="What are you interested in?"
              description="Pick a few interests and your recommendations will appear below instantly. You can change them any time."
            />
          </div>
        </section>
      )}

      <section aria-label="Summary">
        <ul className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {stats.map(({ label, value, icon: Icon, href, alert }) => (
            <li key={label}>
              <Link href={href} className="card card-interactive flex items-center gap-3 p-4">
                <span
                  className={`flex size-10 items-center justify-center rounded-xl ${alert ? "bg-red-50 text-red-700" : "bg-brand-50 text-brand-600"}`}
                >
                  <Icon className="size-5" aria-hidden />
                </span>
                <span>
                  <span className="block text-2xl font-extrabold">{value}</span>
                  <span className="text-muted block text-xs">{label}</span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="rec-heading">
        <SectionHeading
          id="rec-heading"
          title="Recommended for you"
          description={
            data.interests.length > 0
              ? "Ranked by a transparent rule-based score. Each card shows exactly why it was chosen."
              : undefined
          }
          action={
            <LinkButton href="/preferences" variant="ghost" size="sm">
              Tune recommendations <ArrowRight className="size-4" aria-hidden />
            </LinkButton>
          }
        />
        {recommendations.length === 0 ? (
          <EmptyState
            icon={<Sparkles className="size-6" aria-hidden />}
            title={
              data.interests.length === 0
                ? "No interests selected yet"
                : "Nothing new matches right now"
            }
            description={
              data.interests.length === 0
                ? "Choose interests above or follow organizations to get personalised recommendations."
                : "You may have saved everything that matches. Try adding more interests or following organizations."
            }
          />
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {recommendations.map((r) => (
              <RecommendationCard
                key={`${r.type}:${r.item.id}`}
                recommendation={r}
                now={now}
                subtitle={
                  r.type === "opportunity" ? r.item.provider : orgs.get(r.item.organizationId)?.name
                }
              />
            ))}
          </div>
        )}
      </section>

      <div className="grid gap-8 lg:grid-cols-2">
        <section aria-labelledby="deadlines-heading">
          <SectionHeading
            id="deadlines-heading"
            title="Your upcoming deadlines"
            action={
              <LinkButton href="/saved" variant="ghost" size="sm">
                All saved
              </LinkButton>
            }
          />
          {upcomingDeadlines.length === 0 ? (
            <EmptyState
              title="No saved deadlines"
              description="Save opportunities and their deadlines will be tracked here."
            />
          ) : (
            <ul className="card divide-line divide-y">
              {upcomingDeadlines.slice(0, 5).map((o) => (
                <li key={o.id} className="flex items-center justify-between gap-3 p-4">
                  <Link
                    href={`/opportunities/${o.id}`}
                    className="hover:text-brand-700 font-semibold"
                  >
                    {o.title}
                  </Link>
                  <Badge tone={calendarDaysBetween(now, o.deadline!) <= 7 ? "danger" : "neutral"}>
                    {deadlineLabel(o.deadline, now)}
                  </Badge>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section aria-labelledby="events-heading">
          <SectionHeading
            id="events-heading"
            title="Coming up in your calendar"
            action={
              <LinkButton href="/calendar" variant="ghost" size="sm">
                Open calendar
              </LinkButton>
            }
          />
          {myEvents.length === 0 ? (
            <EmptyState
              title="No events in your calendar"
              description="Mark events as “I’m interested” to plan your week."
            />
          ) : (
            <ul className="card divide-line divide-y">
              {myEvents.slice(0, 5).map((e) => (
                <li key={e.id} className="p-4">
                  <Link href={`/events/${e.id}`} className="hover:text-brand-700 font-semibold">
                    {e.title}
                  </Link>
                  <p className="text-muted text-sm">{formatEventRange(e.start, e.end)}</p>
                  {clashes.has(e.id) && (
                    <p className="mt-1 flex items-center gap-1 text-xs font-semibold text-red-700">
                      <AlertTriangle className="size-3.5" aria-hidden /> Clashes with another event
                      in your calendar
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>

      <section aria-labelledby="following-heading">
        <SectionHeading id="following-heading" title="Organizations you follow" />
        {followed.length === 0 ? (
          <EmptyState
            title="You don't follow any organizations yet"
            description="Following an organization boosts its events and opportunities in your recommendations."
            action={
              <LinkButton href="/organizations" variant="secondary">
                Browse organizations
              </LinkButton>
            }
          />
        ) : (
          <ul className="flex flex-wrap gap-3">
            {followed.map((o) => (
              <li key={o.id}>
                <Link
                  href={`/organizations/${o.id}`}
                  className="card card-interactive flex items-center gap-3 py-2 pr-4 pl-2"
                >
                  <OrgAvatar org={o} size="sm" />
                  <span className="text-sm font-semibold">{o.name}</span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
