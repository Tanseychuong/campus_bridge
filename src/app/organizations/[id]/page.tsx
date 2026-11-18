import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, CalendarClock, Mail, UserPlus, Users } from "lucide-react";
import { catalogRepository } from "@/services";
import { isClosed } from "@/lib/deadlines";
import { INTEREST_LABELS, ORGANIZATION_CATEGORY_LABELS } from "@/schemas/taxonomy";
import { FollowButton } from "@/components/features/action-buttons";
import { EventCard, OpportunityCard } from "@/components/features/cards";
import { OrgAvatar } from "@/components/ui/org-avatar";
import { Badge, EmptyState, SampleDataBadge } from "@/components/ui/primitives";

export const revalidate = 3600;

export async function generateMetadata({
  params,
}: PageProps<"/organizations/[id]">): Promise<Metadata> {
  const { id } = await params;
  const org = await catalogRepository.getOrganization(id);
  return org ? { title: org.name, description: org.summary } : { title: "Organization not found" };
}

export default async function OrganizationPage({ params }: PageProps<"/organizations/[id]">) {
  const { id } = await params;
  const org = await catalogRepository.getOrganization(id);
  if (!org) notFound();

  const now = new Date();
  const catalog = await catalogRepository.getCatalog();
  const events = catalog.events
    .filter((e) => e.organizationId === org.id && Date.parse(e.end) > now.getTime())
    .sort((a, b) => Date.parse(a.start) - Date.parse(b.start));
  const opportunities = catalog.opportunities.filter(
    (o) => o.organizationId === org.id && !isClosed(o.deadline, now),
  );

  return (
    <div className="container-page pt-6">
      <Link
        href="/organizations"
        className="text-brand-700 inline-flex items-center gap-1 text-sm font-semibold hover:underline"
      >
        <ArrowLeft className="size-4" aria-hidden /> All organizations
      </Link>

      <header className="card mt-6 flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
        <OrgAvatar org={org} size="lg" />
        <div className="flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone="brand">{ORGANIZATION_CATEGORY_LABELS[org.category]}</Badge>
            <SampleDataBadge />
          </div>
          <h1 className="mt-2 text-3xl font-bold tracking-tight">{org.name}</h1>
          <p className="text-muted mt-1">{org.summary}</p>
        </div>
        <FollowButton id={org.id} name={org.name} size="md" />
      </header>

      <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_20rem]">
        <div className="space-y-10">
          <section aria-labelledby="about-heading">
            <h2 id="about-heading" className="text-lg font-bold">
              About
            </h2>
            <p className="mt-3 leading-relaxed text-slate-700">{org.description}</p>
            <h3 className="mt-6 font-semibold">What we do</h3>
            <ul className="mt-2 grid gap-2 sm:grid-cols-2">
              {org.activities.map((a) => (
                <li key={a} className="ring-line rounded-lg bg-white px-3 py-2 text-sm ring-1">
                  {a}
                </li>
              ))}
            </ul>
          </section>

          <section aria-labelledby="events-heading">
            <h2 id="events-heading" className="mb-4 text-lg font-bold">
              Upcoming events
            </h2>
            {events.length === 0 ? (
              <EmptyState
                title="No upcoming events"
                description="Follow this organization so its future events appear in your recommendations."
              />
            ) : (
              <div className="grid gap-4 md:grid-cols-2">
                {events.map((e) => (
                  <EventCard key={e.id} event={e} />
                ))}
              </div>
            )}
          </section>

          {opportunities.length > 0 && (
            <section aria-labelledby="opps-heading">
              <h2 id="opps-heading" className="mb-4 text-lg font-bold">
                Opportunities from {org.shortName}
              </h2>
              <div className="grid gap-4 md:grid-cols-2">
                {opportunities.map((o) => (
                  <OpportunityCard key={o.id} opportunity={o} now={now} />
                ))}
              </div>
            </section>
          )}
        </div>

        <aside className="card h-fit space-y-4 p-5 text-sm">
          <div className="flex gap-3">
            <UserPlus className="text-brand-600 size-5 shrink-0" aria-hidden />
            <div>
              <p className="font-semibold">How to join</p>
              <p className="text-muted">{org.membership}</p>
            </div>
          </div>
          <div className="flex gap-3">
            <CalendarClock className="text-brand-600 size-5 shrink-0" aria-hidden />
            <div>
              <p className="font-semibold">Meetings</p>
              <p className="text-muted">{org.meetingInfo}</p>
            </div>
          </div>
          <div className="flex gap-3">
            <Users className="text-brand-600 size-5 shrink-0" aria-hidden />
            <div>
              <p className="font-semibold">Community</p>
              <p className="text-muted">
                {org.memberCount} members (sample figure) · founded {org.foundedYear}
              </p>
            </div>
          </div>
          <div className="flex gap-3">
            <Mail className="text-brand-600 size-5 shrink-0" aria-hidden />
            <div>
              <p className="font-semibold">Contact</p>
              <p className="text-muted break-all">{org.contactEmail}</p>
            </div>
          </div>
          <div className="border-line flex flex-wrap gap-1.5 border-t pt-4">
            {org.interests.map((i) => (
              <Badge key={i}>{INTEREST_LABELS[i]}</Badge>
            ))}
          </div>
        </aside>
      </div>
    </div>
  );
}
