/**
 * Content cards. These are server-compatible components (no hooks); the
 * interactive save/follow buttons inside them are client islands.
 *
 * `now` is passed in rather than read inside, so the same card renders
 * identically on the server and in tests.
 */
import Link from "next/link";
import { CalendarClock, Clock, MapPin, Users } from "lucide-react";
import type { CampusEvent, Opportunity, Organization } from "@/schemas/domain";
import {
  EVENT_CATEGORY_LABELS,
  INTEREST_LABELS,
  LOCATION_MODE_LABELS,
  OPPORTUNITY_CATEGORY_LABELS,
  ORGANIZATION_CATEGORY_LABELS,
} from "@/schemas/taxonomy";
import { formatDate, formatEventRange } from "@/lib/dates";
import { bucketDeadline, deadlineLabel } from "@/lib/deadlines";
import { Badge } from "@/components/ui/primitives";
import { OrgAvatar } from "@/components/ui/org-avatar";
import { FollowButton, SaveEventButton, SaveOpportunityButton } from "./action-buttons";
import type { ReactNode } from "react";

export function DeadlineBadge({ deadline, now }: { deadline: string | null; now: Date }) {
  const bucket = bucketDeadline(deadline, now);
  const tone =
    bucket === "closed"
      ? "neutral"
      : bucket === "today" || bucket === "this-week"
        ? "danger"
        : bucket === "next-two-weeks"
          ? "warning"
          : "neutral";
  return (
    <Badge tone={tone}>
      <Clock className="size-3" aria-hidden />
      {deadlineLabel(deadline, now)}
    </Badge>
  );
}

function CardShell({ children, footer }: { children: ReactNode; footer?: ReactNode }) {
  return (
    <article className="card card-interactive relative flex h-full flex-col p-5">
      <div className="flex-1">{children}</div>
      {footer && (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3">{footer}</div>
      )}
    </article>
  );
}

export function OpportunityCard({
  opportunity: o,
  now,
  extra,
}: {
  opportunity: Opportunity;
  now: Date;
  extra?: ReactNode;
}) {
  return (
    <CardShell
      footer={
        <>
          <span className="text-muted text-xs">
            {o.deadline
              ? `Deadline ${formatDate(o.deadline, "d MMM yyyy")}`
              : "Rolling applications"}
          </span>
          <SaveOpportunityButton id={o.id} title={o.title} />
        </>
      }
    >
      <div className="flex flex-wrap items-center gap-2">
        <Badge tone="brand">{OPPORTUNITY_CATEGORY_LABELS[o.category]}</Badge>
        <DeadlineBadge deadline={o.deadline} now={now} />
      </div>
      <h3 className="text-ink mt-3 text-base leading-snug font-bold">
        <Link href={`/opportunities/${o.id}`} className="stretched-link hover:text-brand-700">
          {o.title}
        </Link>
      </h3>
      <p className="mt-1 text-sm font-medium text-slate-600">{o.provider}</p>
      <p className="text-muted mt-2 line-clamp-2 text-sm">{o.summary}</p>
      <p className="text-muted mt-3 flex items-center gap-1.5 text-xs">
        <MapPin className="size-3.5" aria-hidden />
        {LOCATION_MODE_LABELS[o.location.mode]}
        {o.location.place ? ` · ${o.location.place}` : ""}
      </p>
      {extra}
    </CardShell>
  );
}

export function EventCard({
  event: e,
  organization,
  extra,
}: {
  event: CampusEvent;
  organization?: Pick<Organization, "name">;
  extra?: ReactNode;
}) {
  return (
    <CardShell
      footer={
        <>
          <span className="text-muted text-xs">
            {e.capacity ? `${e.capacity} places` : "No capacity limit"} ·{" "}
            {e.registration === "rsvp" ? "RSVP required" : "Open entry"}
          </span>
          <SaveEventButton id={e.id} title={e.title} />
        </>
      }
    >
      <div className="flex flex-wrap items-center gap-2">
        <Badge tone="gold">{EVENT_CATEGORY_LABELS[e.category]}</Badge>
      </div>
      <h3 className="text-ink mt-3 text-base leading-snug font-bold">
        <Link href={`/events/${e.id}`} className="stretched-link hover:text-brand-700">
          {e.title}
        </Link>
      </h3>
      {organization && (
        <p className="mt-1 text-sm font-medium text-slate-600">{organization.name}</p>
      )}
      <p className="text-ink mt-3 flex items-center gap-1.5 text-sm font-medium">
        <CalendarClock className="text-brand-600 size-4" aria-hidden />
        {formatEventRange(e.start, e.end)}
      </p>
      <p className="text-muted mt-1 flex items-center gap-1.5 text-xs">
        <MapPin className="size-3.5" aria-hidden />
        {e.venue}
      </p>
      {extra}
    </CardShell>
  );
}

export function OrganizationCard({ organization: org }: { organization: Organization }) {
  return (
    <CardShell
      footer={
        <>
          <span className="text-muted flex items-center gap-1.5 text-xs">
            <Users className="size-3.5" aria-hidden />
            {org.memberCount} members (sample)
          </span>
          <FollowButton id={org.id} name={org.name} />
        </>
      }
    >
      <div className="flex items-start gap-3">
        <OrgAvatar org={org} />
        <div>
          <h3 className="text-ink text-base leading-snug font-bold">
            <Link href={`/organizations/${org.id}`} className="stretched-link hover:text-brand-700">
              {org.name}
            </Link>
          </h3>
          <p className="text-muted mt-0.5 text-xs font-medium">
            {ORGANIZATION_CATEGORY_LABELS[org.category]}
          </p>
        </div>
      </div>
      <p className="text-muted mt-3 line-clamp-2 text-sm">{org.summary}</p>
      <div className="mt-3 flex flex-wrap gap-1.5">
        {org.interests.map((i) => (
          <Badge key={i}>{INTEREST_LABELS[i]}</Badge>
        ))}
      </div>
    </CardShell>
  );
}
