/**
 * Unified discovery engine: one search across opportunities, events and
 * organizations, with field-weighted relevance ranking and structured filters.
 *
 * Ranking (per query word, best match across fields):
 *   field weight  x  match quality
 *   field weights:  title 3, organization/provider 2, tags 2, text 1
 *   match quality:  exact word 1.0, prefix ("intern" -> "internship") 0.7,
 *                   one-letter typo ("scholarshp") 0.4
 * Every query word must match somewhere (AND semantics). A bonus is added
 * when the whole query appears in the title as a phrase.
 */
import type { Catalog, ItemType } from "@/schemas/domain";
import {
  EVENT_CATEGORY_LABELS,
  INTEREST_LABELS,
  OPPORTUNITY_CATEGORY_LABELS,
  ORGANIZATION_CATEGORY_LABELS,
  type Interest,
  type LocationMode,
} from "@/schemas/taxonomy";
import { isClosed } from "@/lib/deadlines";
import { normalize, tokenize, withinOneEdit } from "./text";
import type { ExploreFilters } from "./params";

export interface SearchDocument {
  type: ItemType;
  id: string;
  href: string;
  title: string;
  subtitle: string;
  summary: string;
  category: string;
  categoryLabel: string;
  interests: Interest[];
  /** Deadline (opportunity), start time (event) or null (organization / rolling). */
  date: string | null;
  publishedAt: string | null;
  locationMode: LocationMode | null;
  tokens: { title: string[]; subtitle: string[]; tags: string[]; body: string[] };
  normalizedTitle: string;
}

export const FIELD_WEIGHTS = { title: 3, subtitle: 2, tags: 2, body: 1 } as const;
export const MATCH_QUALITY = { exact: 1, prefix: 0.7, typo: 0.4 } as const;
export const PHRASE_BONUS = 3;

function tagTokens(categoryLabel: string, interests: Interest[]): string[] {
  return tokenize([categoryLabel, ...interests.map((i) => INTEREST_LABELS[i])].join(" "));
}

/** Build searchable documents from the catalogue. Closed/past items are excluded. */
export function buildSearchIndex(catalog: Catalog, now: Date): SearchDocument[] {
  const orgNames = new Map(catalog.organizations.map((o) => [o.id, o.name]));
  const docs: SearchDocument[] = [];

  for (const o of catalog.opportunities) {
    if (o.status !== "published" || isClosed(o.deadline, now)) continue;
    const categoryLabel = OPPORTUNITY_CATEGORY_LABELS[o.category];
    docs.push({
      type: "opportunity",
      id: o.id,
      href: `/opportunities/${o.id}`,
      title: o.title,
      subtitle: o.provider,
      summary: o.summary,
      category: o.category,
      categoryLabel,
      interests: o.interests,
      date: o.deadline,
      publishedAt: o.publishedAt,
      locationMode: o.location.mode,
      tokens: {
        title: tokenize(o.title),
        subtitle: tokenize(o.provider),
        tags: tagTokens(categoryLabel, o.interests),
        body: tokenize(`${o.summary} ${o.description} ${o.location.place ?? ""}`),
      },
      normalizedTitle: normalize(o.title),
    });
  }

  for (const e of catalog.events) {
    if (e.status !== "published" || Date.parse(e.end) <= now.getTime()) continue;
    const categoryLabel = EVENT_CATEGORY_LABELS[e.category];
    const orgName = orgNames.get(e.organizationId) ?? "";
    docs.push({
      type: "event",
      id: e.id,
      href: `/events/${e.id}`,
      title: e.title,
      subtitle: orgName,
      summary: e.summary,
      category: e.category,
      categoryLabel,
      interests: e.interests,
      date: e.start,
      publishedAt: null,
      locationMode: null,
      tokens: {
        title: tokenize(e.title),
        subtitle: tokenize(orgName),
        tags: tagTokens(categoryLabel, e.interests),
        body: tokenize(`${e.summary} ${e.description} ${e.venue}`),
      },
      normalizedTitle: normalize(e.title),
    });
  }

  for (const org of catalog.organizations) {
    const categoryLabel = ORGANIZATION_CATEGORY_LABELS[org.category];
    docs.push({
      type: "organization",
      id: org.id,
      href: `/organizations/${org.id}`,
      title: org.name,
      subtitle: org.shortName,
      summary: org.summary,
      category: org.category,
      categoryLabel,
      interests: org.interests,
      date: null,
      publishedAt: null,
      locationMode: null,
      tokens: {
        title: tokenize(org.name),
        subtitle: tokenize(org.shortName),
        tags: tagTokens(categoryLabel, org.interests),
        body: tokenize(`${org.summary} ${org.description} ${org.activities.join(" ")}`),
      },
      normalizedTitle: normalize(org.name),
    });
  }

  return docs;
}

/** Quality of the best match between one query word and a list of field words. */
export function matchQuality(queryToken: string, fieldTokens: string[]): number {
  let best = 0;
  for (const token of fieldTokens) {
    if (token === queryToken) return MATCH_QUALITY.exact;
    if (queryToken.length >= 2 && token.startsWith(queryToken)) {
      best = Math.max(best, MATCH_QUALITY.prefix);
    } else if (queryToken.length >= 4 && withinOneEdit(queryToken, token)) {
      best = Math.max(best, MATCH_QUALITY.typo);
    }
  }
  return best;
}

/** Relevance score of a document for a query; 0 means "does not match". */
export function scoreDocument(doc: SearchDocument, query: string): number {
  const queryTokens = tokenize(query);
  if (queryTokens.length === 0) return 0;
  let total = 0;
  for (const qt of queryTokens) {
    let best = 0;
    for (const field of Object.keys(FIELD_WEIGHTS) as (keyof typeof FIELD_WEIGHTS)[]) {
      best = Math.max(best, FIELD_WEIGHTS[field] * matchQuality(qt, doc.tokens[field]));
    }
    if (best === 0) return 0; // AND semantics: every word must match
    total += best;
  }
  const phrase = normalize(query);
  if (queryTokens.length > 1 && doc.normalizedTitle.includes(phrase)) total += PHRASE_BONUS;
  return Math.round(total * 100) / 100;
}

export interface SearchResult {
  doc: SearchDocument;
  score: number;
}

export interface SearchResponse {
  results: SearchResult[];
  /** Result counts per type with all filters applied except `type` (for tabs). */
  counts: Record<"all" | ItemType, number>;
}

function passesFilters(doc: SearchDocument, f: ExploreFilters, now: Date): boolean {
  if (f.interests.length > 0 && !f.interests.some((i) => doc.interests.includes(i))) return false;
  if (f.category && doc.category !== f.category) return false;
  if (f.mode && doc.locationMode !== f.mode) return false;
  if (f.within !== "any") {
    if (!doc.date) return false;
    const limit = now.getTime() + Number(f.within) * 86_400_000;
    if (Date.parse(doc.date) > limit) return false;
  }
  return true;
}

function compareByDate(a: SearchResult, b: SearchResult): number {
  const ad = a.doc.date ? Date.parse(a.doc.date) : Number.MAX_SAFE_INTEGER;
  const bd = b.doc.date ? Date.parse(b.doc.date) : Number.MAX_SAFE_INTEGER;
  return ad - bd || a.doc.title.localeCompare(b.doc.title);
}

export function search(index: SearchDocument[], f: ExploreFilters, now: Date): SearchResponse {
  const hasQuery = tokenize(f.q).length > 0;

  const matching: SearchResult[] = [];
  for (const doc of index) {
    if (!passesFilters(doc, f, now)) continue;
    const score = hasQuery ? scoreDocument(doc, f.q) : 0;
    if (hasQuery && score === 0) continue;
    matching.push({ doc, score });
  }

  const counts = { all: matching.length, opportunity: 0, event: 0, organization: 0 };
  for (const r of matching) counts[r.doc.type]++;

  const results = f.type === "all" ? matching : matching.filter((r) => r.doc.type === f.type);

  results.sort((a, b) => {
    if (f.sort === "soonest") return compareByDate(a, b);
    if (f.sort === "newest") {
      const ap = a.doc.publishedAt ? Date.parse(a.doc.publishedAt) : 0;
      const bp = b.doc.publishedAt ? Date.parse(b.doc.publishedAt) : 0;
      return bp - ap || compareByDate(a, b);
    }
    return b.score - a.score || compareByDate(a, b);
  });

  return { results, counts };
}
