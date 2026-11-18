/**
 * Search state lives in the URL (?q=…&type=…&interest=…) so results can be
 * bookmarked and shared, and Back/Refresh keep the search.
 *
 * URL parameters are untrusted input: every value is validated, and invalid
 * values fall back to safe defaults instead of crashing the page.
 */
import { z } from "zod";
import type { ItemType } from "@/schemas/domain";
import {
  EVENT_CATEGORIES,
  INTERESTS,
  LOCATION_MODES,
  OPPORTUNITY_CATEGORIES,
  ORGANIZATION_CATEGORIES,
  type Interest,
  type LocationMode,
} from "@/schemas/taxonomy";

export const SORT_OPTIONS = ["relevance", "soonest", "newest"] as const;
export const WITHIN_OPTIONS = ["any", "7", "30"] as const;
export type SortOption = (typeof SORT_OPTIONS)[number];
export type WithinOption = (typeof WITHIN_OPTIONS)[number];

export interface ExploreFilters {
  q: string;
  type: "all" | ItemType;
  interests: Interest[];
  category: string | null;
  within: WithinOption;
  mode: LocationMode | null;
  sort: SortOption;
}

export const DEFAULT_FILTERS: ExploreFilters = {
  q: "",
  type: "all",
  interests: [],
  category: null,
  within: "any",
  mode: null,
  sort: "relevance",
};

export const CATEGORIES_BY_TYPE: Record<ItemType, readonly string[]> = {
  opportunity: OPPORTUNITY_CATEGORIES,
  event: EVENT_CATEGORIES,
  organization: ORGANIZATION_CATEGORIES,
};

export type RawSearchParams = Record<string, string | string[] | undefined>;

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

function all(value: string | string[] | undefined): string[] {
  if (value === undefined) return [];
  return Array.isArray(value) ? value : [value];
}

const typeSchema = z.enum(["all", "opportunity", "event", "organization"]).catch("all");
const sortSchema = z.enum(SORT_OPTIONS).catch("relevance");
const withinSchema = z.enum(WITHIN_OPTIONS).catch("any");
const modeSchema = z.enum(LOCATION_MODES).nullable().catch(null);
const interestSchema = z.enum(INTERESTS);

export function parseExploreParams(params: RawSearchParams): ExploreFilters {
  const type = typeSchema.parse(first(params.type));
  const q = (first(params.q) ?? "").slice(0, 100).trim();

  const interests = [
    ...new Set(all(params.interest).filter((i) => interestSchema.safeParse(i).success)),
  ] as Interest[];

  // Category only makes sense once a type is chosen, and must belong to that type.
  const rawCategory = first(params.category) ?? null;
  const category =
    type !== "all" && rawCategory && CATEGORIES_BY_TYPE[type].includes(rawCategory)
      ? rawCategory
      : null;

  // Location mode only applies to opportunities.
  const mode = type === "opportunity" ? modeSchema.parse(first(params.mode) ?? null) : null;

  return {
    q,
    type,
    interests,
    category,
    within: withinSchema.parse(first(params.within)),
    mode,
    sort: sortSchema.parse(first(params.sort)),
  };
}

/** Turn filters back into a query string, omitting default values. */
export function serializeExploreParams(f: ExploreFilters): string {
  const params = new URLSearchParams();
  if (f.q) params.set("q", f.q);
  if (f.type !== "all") params.set("type", f.type);
  for (const i of f.interests) params.append("interest", i);
  if (f.category) params.set("category", f.category);
  if (f.within !== "any") params.set("within", f.within);
  if (f.mode) params.set("mode", f.mode);
  if (f.sort !== "relevance") params.set("sort", f.sort);
  return params.toString();
}

export function countActiveFilters(f: ExploreFilters): number {
  return (
    f.interests.length + (f.category ? 1 : 0) + (f.within !== "any" ? 1 : 0) + (f.mode ? 1 : 0)
  );
}
