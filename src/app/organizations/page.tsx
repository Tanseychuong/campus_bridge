import type { Metadata } from "next";
import Link from "next/link";
import { z } from "zod";
import { catalogRepository } from "@/services";
import { ORGANIZATION_CATEGORIES, ORGANIZATION_CATEGORY_LABELS } from "@/schemas/taxonomy";
import { OrganizationCard } from "@/components/features/cards";
import { PageHeader } from "@/components/ui/primitives";
import { cn } from "@/lib/cn";

export const metadata: Metadata = {
  title: "Student organizations",
  description: "Discover student clubs and societies by category and interest.",
};

const categoryParam = z.enum(ORGANIZATION_CATEGORIES).nullable().catch(null);

export default async function OrganizationsPage({ searchParams }: PageProps<"/organizations">) {
  const raw = (await searchParams).category;
  const category = categoryParam.parse(typeof raw === "string" ? raw : null);
  const { organizations } = await catalogRepository.getCatalog();
  const shown = organizations
    .filter((o) => !category || o.category === category)
    .sort((a, b) => a.name.localeCompare(b.name));

  return (
    <div className="container-page">
      <PageHeader
        eyebrow="Directory"
        title="Student organizations"
        description="Find clubs and societies that match your interests. Follow them to see their events in your recommendations."
      />
      <nav aria-label="Organization categories" className="-mx-1 flex gap-2 overflow-x-auto pb-2">
        {[null, ...ORGANIZATION_CATEGORIES].map((c) => {
          const current = c === category;
          return (
            <Link
              key={c ?? "all"}
              href={c ? `/organizations?category=${c}` : "/organizations"}
              aria-current={current ? "page" : undefined}
              className={cn(
                "shrink-0 rounded-full px-4 py-2 text-sm font-semibold",
                current
                  ? "bg-ink text-white"
                  : "ring-line bg-white text-slate-700 ring-1 hover:bg-slate-50",
              )}
            >
              {c ? ORGANIZATION_CATEGORY_LABELS[c] : "All"}
            </Link>
          );
        })}
      </nav>
      <p className="text-muted mt-4 text-sm" aria-live="polite">
        {shown.length} organization{shown.length === 1 ? "" : "s"}
      </p>
      <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {shown.map((o) => (
          <li key={o.id}>
            <OrganizationCard organization={o} />
          </li>
        ))}
      </ul>
    </div>
  );
}
