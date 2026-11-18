import type { Metadata } from "next";
import Link from "next/link";
import { SearchX } from "lucide-react";
import { catalogRepository } from "@/services";
import { buildSearchIndex, search } from "@/lib/search/engine";
import {
  parseExploreParams,
  serializeExploreParams,
  type ExploreFilters,
} from "@/lib/search/params";
import type { ItemType } from "@/schemas/domain";
import { SearchBox } from "@/components/features/search-box";
import { ExploreFilterPanel } from "@/components/features/explore-filters";
import { EventCard, OpportunityCard, OrganizationCard } from "@/components/features/cards";
import { EmptyState, PageHeader } from "@/components/ui/primitives";
import { LinkButton } from "@/components/ui/button";
import { cn } from "@/lib/cn";

export const metadata: Metadata = {
  title: "Explore",
  description: "Search opportunities, campus events and student organizations in one place.",
};

const TABS: { type: "all" | ItemType; label: string }[] = [
  { type: "all", label: "All" },
  { type: "opportunity", label: "Opportunities" },
  { type: "event", label: "Events" },
  { type: "organization", label: "Organizations" },
];

function tabHref(filters: ExploreFilters, type: "all" | ItemType) {
  const qs = serializeExploreParams({ ...filters, type, category: null, mode: null });
  return qs ? `/explore?${qs}` : "/explore";
}

export default async function ExplorePage({ searchParams }: PageProps<"/explore">) {
  const filters = parseExploreParams(await searchParams);
  const now = new Date();
  const catalog = await catalogRepository.getCatalog();
  const { results, counts } = search(buildSearchIndex(catalog, now), filters, now);

  const opportunities = new Map(catalog.opportunities.map((o) => [o.id, o]));
  const events = new Map(catalog.events.map((e) => [e.id, e]));
  const organizations = new Map(catalog.organizations.map((o) => [o.id, o]));

  return (
    <div className="container-page">
      <PageHeader
        eyebrow="Unified discovery"
        title="Explore"
        description="One search across opportunities, campus events and student organizations."
      />
      <SearchBox defaultValue={filters.q} type={filters.type} />

      <nav aria-label="Result types" className="-mx-1 mt-6 flex gap-1 overflow-x-auto pb-1">
        {TABS.map((tab) => {
          const current = filters.type === tab.type;
          return (
            <Link
              key={tab.type}
              href={tabHref(filters, tab.type)}
              aria-current={current ? "page" : undefined}
              className={cn(
                "flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition-colors",
                current
                  ? "bg-ink text-white"
                  : "ring-line bg-white text-slate-700 ring-1 hover:bg-slate-50",
              )}
            >
              {tab.label}
              <span
                className={cn(
                  "rounded-full px-2 text-xs",
                  current ? "bg-white/20" : "bg-slate-100 text-slate-600",
                )}
              >
                {counts[tab.type]}
              </span>
            </Link>
          );
        })}
      </nav>

      <div className="mt-6 grid gap-6 lg:grid-cols-[17rem_1fr]">
        <aside>
          <ExploreFilterPanel filters={filters} />
        </aside>

        <section aria-labelledby="results-heading">
          <h2
            id="results-heading"
            className="text-muted mb-4 text-sm font-semibold"
            aria-live="polite"
          >
            {results.length} result{results.length === 1 ? "" : "s"}
            {filters.q && (
              <>
                {" "}
                for “<span className="text-ink">{filters.q}</span>”
              </>
            )}
          </h2>

          {results.length === 0 ? (
            <EmptyState
              icon={<SearchX className="size-6" aria-hidden />}
              title="No matches found"
              description="Try a shorter search, check the spelling or remove some filters. Closed opportunities and past events are hidden."
              action={
                <LinkButton href="/explore" variant="secondary">
                  Reset search
                </LinkButton>
              }
            />
          ) : (
            <ul className="grid gap-4 md:grid-cols-2">
              {results.map(({ doc }) => (
                <li key={`${doc.type}:${doc.id}`}>
                  {doc.type === "opportunity" && (
                    <OpportunityCard opportunity={opportunities.get(doc.id)!} now={now} />
                  )}
                  {doc.type === "event" && (
                    <EventCard
                      event={events.get(doc.id)!}
                      organization={organizations.get(events.get(doc.id)!.organizationId)}
                    />
                  )}
                  {doc.type === "organization" && (
                    <OrganizationCard organization={organizations.get(doc.id)!} />
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}
