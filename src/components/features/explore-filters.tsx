"use client";

/**
 * Filter panel for /explore.
 *
 * - It is a normal GET form, so it still works without JavaScript ("Apply").
 * - With JavaScript, every change updates the URL immediately; the server
 *   page then renders the new results. The URL is the single source of truth.
 * - Inputs are controlled by a local `draft` that re-syncs whenever the URL
 *   changes (e.g. "Clear filters" or a tab click). We never remount the form,
 *   so keyboard focus is not lost while filtering.
 */
import { useRouter } from "next/navigation";
import { useState, useTransition, type FormEvent } from "react";
import Link from "next/link";
import { SlidersHorizontal } from "lucide-react";
import {
  CATEGORIES_BY_TYPE,
  countActiveFilters,
  serializeExploreParams,
  type ExploreFilters,
  type SortOption,
  type WithinOption,
} from "@/lib/search/params";
import {
  EVENT_CATEGORY_LABELS,
  INTEREST_LABELS,
  INTERESTS,
  LOCATION_MODE_LABELS,
  LOCATION_MODES,
  OPPORTUNITY_CATEGORY_LABELS,
  ORGANIZATION_CATEGORY_LABELS,
  type Interest,
  type LocationMode,
} from "@/schemas/taxonomy";
import { Select } from "@/components/ui/form";
import { buttonClasses } from "@/components/ui/button";
import { cn } from "@/lib/cn";

const CATEGORY_LABELS: Record<string, Record<string, string>> = {
  opportunity: OPPORTUNITY_CATEGORY_LABELS,
  event: EVENT_CATEGORY_LABELS,
  organization: ORGANIZATION_CATEGORY_LABELS,
};

const WITHIN_CHOICES: { value: WithinOption; label: string }[] = [
  { value: "any", label: "Any time" },
  { value: "7", label: "Within 7 days" },
  { value: "30", label: "Within 30 days" },
];

export function ExploreFilterPanel({ filters }: { filters: ExploreFilters }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [open, setOpen] = useState(false);

  // "Adjusting state when a prop changes" pattern (React docs): keep a local
  // draft, and reset it whenever the URL-derived filters change.
  const [draft, setDraft] = useState(filters);
  const [synced, setSynced] = useState(filters);
  if (serializeExploreParams(filters) !== serializeExploreParams(synced)) {
    setSynced(filters);
    setDraft(filters);
  }

  function apply(next: ExploreFilters) {
    setDraft(next);
    const qs = serializeExploreParams(next);
    startTransition(() => router.replace(qs ? `/explore?${qs}` : "/explore", { scroll: false }));
  }

  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    apply(draft);
  }

  function toggleInterest(interest: Interest) {
    const interests = draft.interests.includes(interest)
      ? draft.interests.filter((i) => i !== interest)
      : [...draft.interests, interest];
    apply({ ...draft, interests });
  }

  const active = countActiveFilters(draft);

  return (
    <div>
      <button
        type="button"
        className={buttonClasses("secondary", "md", "w-full lg:hidden")}
        aria-expanded={open}
        aria-controls="explore-filters"
        onClick={() => setOpen((v) => !v)}
      >
        <SlidersHorizontal className="size-4" aria-hidden />
        Filters{active > 0 ? ` (${active})` : ""}
      </button>

      <form
        id="explore-filters"
        action="/explore"
        method="get"
        onSubmit={onSubmit}
        aria-label="Filter results"
        className={cn("card mt-3 space-y-6 p-5 lg:mt-0 lg:block", open ? "block" : "hidden")}
      >
        {draft.q && <input type="hidden" name="q" value={draft.q} />}
        {draft.type !== "all" && <input type="hidden" name="type" value={draft.type} />}

        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold">Filters</h2>
          <span aria-live="polite" className="text-muted text-xs">
            {pending ? "Updating…" : ""}
          </span>
        </div>

        <div>
          <label htmlFor="sort" className="text-sm font-semibold">
            Sort by
          </label>
          <Select
            id="sort"
            name="sort"
            value={draft.sort}
            onChange={(e) => apply({ ...draft, sort: e.target.value as SortOption })}
            className="mt-1.5"
          >
            <option value="relevance">Best match</option>
            <option value="soonest">Soonest deadline / date</option>
            <option value="newest">Newest</option>
          </Select>
        </div>

        {draft.type !== "all" && (
          <div>
            <label htmlFor="category" className="text-sm font-semibold">
              Category
            </label>
            <Select
              id="category"
              name="category"
              value={draft.category ?? ""}
              onChange={(e) => apply({ ...draft, category: e.target.value || null })}
              className="mt-1.5"
            >
              <option value="">All categories</option>
              {CATEGORIES_BY_TYPE[draft.type].map((c) => (
                <option key={c} value={c}>
                  {CATEGORY_LABELS[draft.type][c]}
                </option>
              ))}
            </Select>
          </div>
        )}

        {draft.type === "opportunity" && (
          <div>
            <label htmlFor="mode" className="text-sm font-semibold">
              Location
            </label>
            <Select
              id="mode"
              name="mode"
              value={draft.mode ?? ""}
              onChange={(e) =>
                apply({ ...draft, mode: (e.target.value || null) as LocationMode | null })
              }
              className="mt-1.5"
            >
              <option value="">Any location</option>
              {LOCATION_MODES.map((m) => (
                <option key={m} value={m}>
                  {LOCATION_MODE_LABELS[m]}
                </option>
              ))}
            </Select>
          </div>
        )}

        {draft.type !== "organization" && (
          <fieldset>
            <legend className="text-sm font-semibold">Deadline or date</legend>
            <div className="mt-2 space-y-2">
              {WITHIN_CHOICES.map((o) => (
                <label key={o.value} className="flex items-center gap-2 text-sm">
                  <input
                    type="radio"
                    name="within"
                    value={o.value}
                    checked={draft.within === o.value}
                    onChange={() => apply({ ...draft, within: o.value })}
                    className="accent-brand-600 size-4"
                  />
                  {o.label}
                </label>
              ))}
            </div>
          </fieldset>
        )}

        <fieldset>
          <legend className="text-sm font-semibold">Interests</legend>
          <div className="mt-2 flex flex-wrap gap-2">
            {INTERESTS.map((i) => (
              <label
                key={i}
                className="border-line has-checked:border-brand-600 has-checked:bg-brand-600 has-focus-visible:outline-brand-500 cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium has-checked:text-white has-focus-visible:outline-3"
              >
                <input
                  type="checkbox"
                  name="interest"
                  value={i}
                  checked={draft.interests.includes(i)}
                  onChange={() => toggleInterest(i)}
                  className="sr-only"
                />
                {INTEREST_LABELS[i]}
              </label>
            ))}
          </div>
        </fieldset>

        <div className="flex items-center gap-3">
          <noscript>
            <button type="submit" className={buttonClasses("primary", "sm")}>
              Apply
            </button>
          </noscript>
          {active > 0 && (
            <Link
              href={`/explore?${serializeExploreParams({ ...draft, interests: [], category: null, within: "any", mode: null })}`}
              className="text-brand-700 text-sm font-semibold hover:underline"
            >
              Clear filters
            </Link>
          )}
        </div>
      </form>
    </div>
  );
}
