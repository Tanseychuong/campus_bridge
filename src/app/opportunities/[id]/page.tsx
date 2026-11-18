import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, CalendarClock, CheckCircle2, MapPin, Tag } from "lucide-react";
import { catalogRepository } from "@/services";
import { formatDate } from "@/lib/dates";
import { compareDeadlines, isClosed } from "@/lib/deadlines";
import { hostnameOf } from "@/lib/safe-url";
import {
  INTEREST_LABELS,
  LOCATION_MODE_LABELS,
  OPPORTUNITY_CATEGORY_LABELS,
} from "@/schemas/taxonomy";
import { DeadlineBadge, OpportunityCard } from "@/components/features/cards";
import { SaveOpportunityButton } from "@/components/features/action-buttons";
import { ExternalLink } from "@/components/ui/external-link";
import { Badge, DetailItem, Notice, SampleDataBadge } from "@/components/ui/primitives";

export const revalidate = 3600;

export async function generateMetadata({
  params,
}: PageProps<"/opportunities/[id]">): Promise<Metadata> {
  const { id } = await params;
  const o = await catalogRepository.getOpportunity(id);
  return o ? { title: o.title, description: o.summary } : { title: "Opportunity not found" };
}

export default async function OpportunityPage({ params }: PageProps<"/opportunities/[id]">) {
  const { id } = await params;
  const opportunity = await catalogRepository.getOpportunity(id);
  if (!opportunity) notFound();

  const now = new Date();
  const o = opportunity;
  const closed = isClosed(o.deadline, now);
  const catalog = await catalogRepository.getCatalog();
  const related = catalog.opportunities
    .filter((x) => x.id !== o.id && !isClosed(x.deadline, now))
    .filter((x) => x.category === o.category || x.interests.some((i) => o.interests.includes(i)))
    .sort(compareDeadlines)
    .slice(0, 3);

  return (
    <div className="container-page pt-6">
      <Link
        href="/explore?type=opportunity"
        className="text-brand-700 inline-flex items-center gap-1 text-sm font-semibold hover:underline"
      >
        <ArrowLeft className="size-4" aria-hidden /> All opportunities
      </Link>

      <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_20rem]">
        <article>
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone="brand">{OPPORTUNITY_CATEGORY_LABELS[o.category]}</Badge>
            <DeadlineBadge deadline={o.deadline} now={now} />
            <SampleDataBadge />
          </div>
          <h1 className="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">{o.title}</h1>
          <p className="mt-2 text-lg font-medium text-slate-600">
            {o.organizationId ? (
              <Link
                href={`/organizations/${o.organizationId}`}
                className="hover:text-brand-700 hover:underline"
              >
                {o.provider}
              </Link>
            ) : (
              o.provider
            )}
          </p>

          {closed && (
            <Notice tone="warning" className="mt-6" title="This opportunity has closed">
              The deadline has passed. It is kept here for reference only.
            </Notice>
          )}

          <section className="mt-8" aria-labelledby="about-heading">
            <h2 id="about-heading" className="text-lg font-bold">
              About this opportunity
            </h2>
            {/* Text is rendered as plain text by React, which escapes HTML — no XSS risk. */}
            <p className="mt-3 leading-relaxed whitespace-pre-line text-slate-700">
              {o.description}
            </p>
          </section>

          <section className="mt-8" aria-labelledby="eligibility-heading">
            <h2 id="eligibility-heading" className="text-lg font-bold">
              Eligibility
            </h2>
            {o.eligibility.length > 0 ? (
              <ul className="mt-3 space-y-2">
                {o.eligibility.map((e) => (
                  <li key={e} className="flex gap-2 text-slate-700">
                    <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" aria-hidden />
                    {e}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-muted mt-3">
                The provider has not published eligibility details. Check the official source before
                applying.
              </p>
            )}
          </section>
        </article>

        <aside className="space-y-4">
          <div className="card space-y-4 p-5">
            <dl className="space-y-3 text-sm">
              <DetailItem icon={<CalendarClock className="size-5" />} term="Deadline">
                {o.deadline
                  ? `${formatDate(o.deadline)}, ${formatDate(o.deadline, "HH:mm")} (Accra time)`
                  : "Rolling — no fixed deadline"}
              </DetailItem>
              <DetailItem icon={<MapPin className="size-5" />} term="Location">
                {LOCATION_MODE_LABELS[o.location.mode]}
                {o.location.place ? ` · ${o.location.place}` : ""}
              </DetailItem>
              <DetailItem icon={<Tag className="size-5" />} term="Interests">
                <span className="mt-1 flex flex-wrap gap-1.5">
                  {o.interests.map((i) => (
                    <Badge key={i}>{INTEREST_LABELS[i]}</Badge>
                  ))}
                </span>
              </DetailItem>
            </dl>
            <div className="flex flex-col gap-2">
              {!closed && <ExternalLink href={o.source.url}>Apply on official site</ExternalLink>}
              <SaveOpportunityButton id={o.id} title={o.title} size="md" />
            </div>
          </div>
          <p className="text-muted px-1 text-xs">
            Source: <strong>{o.source.name}</strong> ({hostnameOf(o.source.url)}). Published{" "}
            {formatDate(o.publishedAt, "d MMM yyyy")}. This is fictional sample data; the link
            points to a reserved example domain.
          </p>
        </aside>
      </div>

      {related.length > 0 && (
        <section className="mt-14" aria-labelledby="related-heading">
          <h2 id="related-heading" className="mb-4 text-xl font-bold">
            Similar opportunities
          </h2>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {related.map((r) => (
              <OpportunityCard key={r.id} opportunity={r} now={now} />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
