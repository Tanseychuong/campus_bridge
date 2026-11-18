import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, CalendarClock, Mail, MapPin, Ticket, Users } from "lucide-react";
import { catalogRepository } from "@/services";
import { formatEventRange } from "@/lib/dates";
import { EVENT_CATEGORY_LABELS, INTEREST_LABELS } from "@/schemas/taxonomy";
import { SaveEventButton } from "@/components/features/action-buttons";
import { EventCard } from "@/components/features/cards";
import { OrgAvatar } from "@/components/ui/org-avatar";
import { Badge, DetailItem, Notice, SampleDataBadge } from "@/components/ui/primitives";

export const revalidate = 3600;

export async function generateMetadata({ params }: PageProps<"/events/[id]">): Promise<Metadata> {
  const { id } = await params;
  const e = await catalogRepository.getEvent(id);
  return e ? { title: e.title, description: e.summary } : { title: "Event not found" };
}

export default async function EventPage({ params }: PageProps<"/events/[id]">) {
  const { id } = await params;
  const event = await catalogRepository.getEvent(id);
  if (!event) notFound();

  const e = event;
  const now = new Date();
  const past = Date.parse(e.end) <= now.getTime();
  const org = await catalogRepository.getOrganization(e.organizationId);
  const catalog = await catalogRepository.getCatalog();
  const moreFromOrg = catalog.events
    .filter(
      (x) =>
        x.organizationId === e.organizationId && x.id !== e.id && Date.parse(x.end) > now.getTime(),
    )
    .sort((a, b) => Date.parse(a.start) - Date.parse(b.start))
    .slice(0, 3);

  return (
    <div className="container-page pt-6">
      <Link
        href="/events"
        className="text-brand-700 inline-flex items-center gap-1 text-sm font-semibold hover:underline"
      >
        <ArrowLeft className="size-4" aria-hidden /> All events
      </Link>

      <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_20rem]">
        <article>
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone="gold">{EVENT_CATEGORY_LABELS[e.category]}</Badge>
            {e.interests.map((i) => (
              <Badge key={i}>{INTEREST_LABELS[i]}</Badge>
            ))}
            <SampleDataBadge />
          </div>
          <h1 className="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">{e.title}</h1>
          {org && (
            <Link
              href={`/organizations/${org.id}`}
              className="hover:text-brand-700 mt-4 inline-flex items-center gap-3 rounded-xl"
            >
              <OrgAvatar org={org} size="sm" />
              <span className="font-semibold">{org.name}</span>
            </Link>
          )}

          {past && (
            <Notice tone="warning" className="mt-6" title="This event has ended">
              It is kept here for reference only.
            </Notice>
          )}

          <section className="mt-8" aria-labelledby="about-heading">
            <h2 id="about-heading" className="text-lg font-bold">
              About this event
            </h2>
            <p className="mt-3 leading-relaxed whitespace-pre-line text-slate-700">
              {e.description}
            </p>
          </section>
        </article>

        <aside className="space-y-4">
          <div className="card space-y-4 p-5">
            <dl className="space-y-3 text-sm">
              <DetailItem icon={<CalendarClock className="size-5" />} term="When">
                {formatEventRange(e.start, e.end)} (Accra time)
              </DetailItem>
              <DetailItem icon={<MapPin className="size-5" />} term="Venue">
                {e.venue}
              </DetailItem>
              <DetailItem icon={<Users className="size-5" />} term="Capacity">
                {e.capacity ? `${e.capacity} people` : "No limit"}
              </DetailItem>
              <DetailItem icon={<Ticket className="size-5" />} term="Registration">
                {e.registration === "rsvp" ? "RSVP required" : "Open entry — just turn up"}
              </DetailItem>
              <DetailItem icon={<Mail className="size-5" />} term="Contact">
                <span className="break-all">{e.contact}</span>
              </DetailItem>
            </dl>
            {!past && <SaveEventButton id={e.id} title={e.title} size="md" />}
          </div>
          <Notice>
            “I’m interested” adds the event to{" "}
            <Link href="/calendar" className="font-semibold underline">
              your calendar
            </Link>{" "}
            in this browser. It is <strong>not</strong> a reservation —{" "}
            {e.registration === "rsvp"
              ? "RSVP with the organizer to secure a place."
              : "no registration is needed."}
          </Notice>
        </aside>
      </div>

      {moreFromOrg.length > 0 && org && (
        <section className="mt-14" aria-labelledby="more-heading">
          <h2 id="more-heading" className="mb-4 text-xl font-bold">
            More from {org.name}
          </h2>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {moreFromOrg.map((x) => (
              <EventCard key={x.id} event={x} organization={org} />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
