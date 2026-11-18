/**
 * Explainable, rule-based recommendation engine.
 *
 * This is NOT machine learning. Every point an item earns comes from a
 * readable rule, and every rule produces a human-readable reason that is
 * shown to the student ("Recommended because…").
 *
 * The engine is a set of pure functions: same input -> same output. It has no
 * React or browser dependencies, so it is unit-tested in isolation.
 */
import type { CampusEvent, Opportunity, Organization } from "@/schemas/domain";
import { INTEREST_LABELS, type Interest } from "@/schemas/taxonomy";
import { calendarDaysBetween } from "@/lib/dates";
import { isClosed } from "@/lib/deadlines";
import { RECOMMENDATION_WEIGHTS as W } from "./weights";

export interface RecommendationProfile {
  interests: Interest[];
  followedOrganizationIds: string[];
  savedOpportunityIds: string[];
  savedEventIds: string[];
}

export type Candidate =
  { type: "opportunity"; item: Opportunity } | { type: "event"; item: CampusEvent };

export type ReasonKind = "interest" | "followed" | "similar" | "timing" | "missing-info";

export interface Reason {
  kind: ReasonKind;
  text: string;
  points: number;
}

export type Recommendation = Candidate & { score: number; reasons: Reason[] };

export interface ScoringContext {
  profile: RecommendationProfile;
  now: Date;
  /** Used to name organizations in reasons, e.g. "From Code & Circuits". */
  organizationsById: Map<string, Organization>;
  /** Categories of items the student has already saved, with counts. */
  savedCategoryCounts: Map<string, number>;
}

function categoryKey(c: Candidate): string {
  return `${c.type}:${c.item.category}`;
}

function listLabels(interests: Interest[]): string {
  return interests.map((i) => INTEREST_LABELS[i]).join(", ");
}

/** Score one candidate and explain the score. */
export function scoreCandidate(candidate: Candidate, ctx: ScoringContext): Recommendation {
  const reasons: Reason[] = [];

  // 1. Interest matches
  const matched = candidate.item.interests.filter((i) => ctx.profile.interests.includes(i));
  if (matched.length > 0) {
    const counted = matched.slice(0, W.maxInterestMatches);
    reasons.push({
      kind: "interest",
      text: `Matches your interests: ${listLabels(counted)}`,
      points: counted.length * W.interestMatch,
    });
  }

  // 2. Followed organization
  const orgId = candidate.item.organizationId;
  if (orgId && ctx.profile.followedOrganizationIds.includes(orgId)) {
    const name = ctx.organizationsById.get(orgId)?.name ?? "an organization you follow";
    reasons.push({
      kind: "followed",
      text: `From ${name}, which you follow`,
      points: W.followedOrganization,
    });
  }

  // 3. Similar to items already saved
  const savedSimilar = ctx.savedCategoryCounts.get(categoryKey(candidate)) ?? 0;
  if (savedSimilar > 0) {
    reasons.push({
      kind: "similar",
      text: `Similar to ${savedSimilar} item${savedSimilar === 1 ? "" : "s"} you saved`,
      points: W.similarToSaved,
    });
  }

  // 4. Timing
  if (candidate.type === "opportunity" && candidate.item.deadline) {
    const days = calendarDaysBetween(ctx.now, candidate.item.deadline);
    if (days >= 3 && days <= 14) {
      reasons.push({
        kind: "timing",
        text: `Deadline in ${days} days — a good time to apply`,
        points: W.deadlineSoon,
      });
    } else if (days >= 0 && days <= 2) {
      const when = days === 0 ? "today" : days === 1 ? "tomorrow" : "in 2 days";
      reasons.push({ kind: "timing", text: `Closes ${when} — act fast`, points: W.deadlineNear });
    } else if (days > 14 && days <= 30) {
      reasons.push({ kind: "timing", text: `Deadline in ${days} days`, points: W.deadlineNear });
    }
  }
  if (candidate.type === "event") {
    const days = calendarDaysBetween(ctx.now, candidate.item.start);
    if (days >= 0 && days <= 7) {
      const when = days === 0 ? "today" : days === 1 ? "tomorrow" : `in ${days} days`;
      reasons.push({ kind: "timing", text: `Happening ${when}`, points: W.eventThisWeek });
    } else if (days > 7 && days <= 14) {
      reasons.push({ kind: "timing", text: `Happening in ${days} days`, points: W.eventNextWeek });
    }
  }

  // 5. Data quality penalty
  if (candidate.type === "opportunity" && candidate.item.eligibility.length === 0) {
    reasons.push({
      kind: "missing-info",
      text: "Eligibility details are missing",
      points: W.missingInformation,
    });
  }

  const score = reasons.reduce((sum, r) => sum + r.points, 0);
  return { ...candidate, score, reasons };
}

/** An item can only be recommended if it is published, still open/upcoming and not already saved. */
export function isEligibleCandidate(
  c: Candidate,
  profile: RecommendationProfile,
  now: Date,
): boolean {
  if (c.item.status !== "published") return false;
  if (c.type === "opportunity") {
    return !isClosed(c.item.deadline, now) && !profile.savedOpportunityIds.includes(c.item.id);
  }
  return Date.parse(c.item.end) > now.getTime() && !profile.savedEventIds.includes(c.item.id);
}

/**
 * Relevance gate: when the student has chosen interests, only items that match
 * at least one interest or come from a followed organization are recommended.
 * Without this gate, an urgent but irrelevant item could rank highly.
 */
function isRelevant(r: Recommendation): boolean {
  return r.reasons.some((reason) => reason.kind === "interest" || reason.kind === "followed");
}

function sortDate(c: Candidate): number {
  if (c.type === "event") return Date.parse(c.item.start);
  return c.item.deadline ? Date.parse(c.item.deadline) : Number.MAX_SAFE_INTEGER;
}

/** Highest score first; ties broken by soonest date, then title (deterministic). */
export function compareRecommendations(a: Recommendation, b: Recommendation): number {
  return b.score - a.score || sortDate(a) - sortDate(b) || a.item.title.localeCompare(b.item.title);
}

export interface RecommendInput {
  opportunities: Opportunity[];
  events: CampusEvent[];
  organizations: Organization[];
  profile: RecommendationProfile;
  now: Date;
  limit?: number;
}

export function recommend(input: RecommendInput): Recommendation[] {
  const { opportunities, events, organizations, profile, now, limit = 6 } = input;

  const savedCategoryCounts = new Map<string, number>();
  for (const o of opportunities) {
    if (profile.savedOpportunityIds.includes(o.id)) {
      const key = `opportunity:${o.category}`;
      savedCategoryCounts.set(key, (savedCategoryCounts.get(key) ?? 0) + 1);
    }
  }
  for (const e of events) {
    if (profile.savedEventIds.includes(e.id)) {
      const key = `event:${e.category}`;
      savedCategoryCounts.set(key, (savedCategoryCounts.get(key) ?? 0) + 1);
    }
  }

  const ctx: ScoringContext = {
    profile,
    now,
    organizationsById: new Map(organizations.map((o) => [o.id, o])),
    savedCategoryCounts,
  };

  const candidates: Candidate[] = [
    ...opportunities.map((item) => ({ type: "opportunity" as const, item })),
    ...events.map((item) => ({ type: "event" as const, item })),
  ];

  return candidates
    .filter((c) => isEligibleCandidate(c, profile, now))
    .map((c) => scoreCandidate(c, ctx))
    .filter((r) => r.score > 0 && isRelevant(r))
    .sort(compareRecommendations)
    .slice(0, limit);
}
