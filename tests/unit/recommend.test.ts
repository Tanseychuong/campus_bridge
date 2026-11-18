import { describe, expect, it } from "vitest";
import { recommend, scoreCandidate, type RecommendationProfile } from "@/lib/recommend/score";
import { RECOMMENDATION_WEIGHTS as W } from "@/lib/recommend/weights";
import { NOW, daysFromNow, makeEvent, makeOpportunity, makeOrganization } from "../factories";

const emptyProfile: RecommendationProfile = {
  interests: [],
  followedOrganizationIds: [],
  savedOpportunityIds: [],
  savedEventIds: [],
};

function ctx(profile: Partial<RecommendationProfile> = {}) {
  return {
    profile: { ...emptyProfile, ...profile },
    now: NOW,
    organizationsById: new Map([["org-a", makeOrganization()]]),
    savedCategoryCounts: new Map<string, number>(),
  };
}

describe("scoreCandidate", () => {
  it("awards points per matching interest and explains them", () => {
    const opp = makeOpportunity({ interests: ["computer-science", "research"], deadline: null });
    const r = scoreCandidate(
      { type: "opportunity", item: opp },
      ctx({ interests: ["computer-science", "research"] }),
    );
    expect(r.score).toBe(2 * W.interestMatch);
    expect(r.reasons[0].text).toBe("Matches your interests: Computer Science, Research");
  });

  it("caps interest matches", () => {
    const opp = makeOpportunity({
      interests: ["computer-science", "research", "engineering", "business"],
      deadline: null,
    });
    const r = scoreCandidate(
      { type: "opportunity", item: opp },
      ctx({ interests: ["computer-science", "research", "engineering", "business"] }),
    );
    expect(r.score).toBe(W.maxInterestMatches * W.interestMatch);
  });

  it("rewards deadlines 3–14 days away more than very close or distant ones", () => {
    const score = (days: number) =>
      scoreCandidate(
        { type: "opportunity", item: makeOpportunity({ deadline: daysFromNow(days) }) },
        ctx(),
      ).score;
    expect(score(7)).toBe(W.deadlineSoon);
    expect(score(1)).toBe(W.deadlineNear);
    expect(score(20)).toBe(W.deadlineNear);
    expect(score(60)).toBe(0);
  });

  it("adds points for followed organizations and names them", () => {
    const evt = makeEvent({ start: daysFromNow(30, "10:00"), end: daysFromNow(30, "11:00") });
    const r = scoreCandidate(
      { type: "event", item: evt },
      ctx({ followedOrganizationIds: ["org-a"] }),
    );
    expect(r.score).toBe(W.followedOrganization);
    expect(r.reasons[0].text).toContain("Org A Society");
  });

  it("penalises opportunities with missing eligibility information", () => {
    const opp = makeOpportunity({ eligibility: [], deadline: null });
    const r = scoreCandidate({ type: "opportunity", item: opp }, ctx());
    expect(r.score).toBe(W.missingInformation);
    expect(r.reasons.some((x) => x.kind === "missing-info")).toBe(true);
  });
});

describe("recommend", () => {
  const organizations = [makeOrganization()];

  it("returns nothing relevant when the student has no interests or follows", () => {
    const result = recommend({
      opportunities: [makeOpportunity()],
      events: [makeEvent()],
      organizations,
      profile: emptyProfile,
      now: NOW,
    });
    expect(result).toEqual([]);
  });

  it("ranks by score and changes when interests change", () => {
    const cs = makeOpportunity({ id: "cs", interests: ["computer-science"], deadline: null });
    const arts = makeOpportunity({ id: "arts", interests: ["arts"], deadline: null });
    const forCs = recommend({
      opportunities: [cs, arts],
      events: [],
      organizations,
      profile: { ...emptyProfile, interests: ["computer-science"] },
      now: NOW,
    });
    expect(forCs.map((r) => r.item.id)).toEqual(["cs"]);

    const forArts = recommend({
      opportunities: [cs, arts],
      events: [],
      organizations,
      profile: { ...emptyProfile, interests: ["arts"] },
      now: NOW,
    });
    expect(forArts.map((r) => r.item.id)).toEqual(["arts"]);
  });

  it("excludes closed, past, unpublished and already-saved items", () => {
    const profile = {
      ...emptyProfile,
      interests: ["computer-science" as const],
      savedOpportunityIds: ["saved"],
    };
    const result = recommend({
      opportunities: [
        makeOpportunity({ id: "closed", deadline: daysFromNow(-1) }),
        makeOpportunity({ id: "pending", status: "pending" }),
        makeOpportunity({ id: "saved" }),
        makeOpportunity({ id: "ok" }),
      ],
      events: [
        makeEvent({ id: "past", start: daysFromNow(-2, "10:00"), end: daysFromNow(-2, "11:00") }),
      ],
      organizations,
      profile,
      now: NOW,
    });
    expect(result.map((r) => r.item.id)).toEqual(["ok"]);
  });

  it("boosts items similar to saved ones", () => {
    const saved = makeOpportunity({ id: "saved", category: "internship" });
    const sameCategory = makeOpportunity({ id: "same", category: "internship", deadline: null });
    const otherCategory = makeOpportunity({ id: "other", category: "scholarship", deadline: null });
    const result = recommend({
      opportunities: [saved, otherCategory, sameCategory],
      events: [],
      organizations,
      profile: { ...emptyProfile, interests: ["computer-science"], savedOpportunityIds: ["saved"] },
      now: NOW,
    });
    expect(result[0].item.id).toBe("same");
    expect(result[0].score - result[1].score).toBe(W.similarToSaved);
  });

  it("is deterministic: ties break by soonest date then title", () => {
    const later = makeOpportunity({ id: "b", title: "B", deadline: daysFromNow(10) });
    const sooner = makeOpportunity({ id: "a", title: "A", deadline: daysFromNow(9) });
    const result = recommend({
      opportunities: [later, sooner],
      events: [],
      organizations,
      profile: { ...emptyProfile, interests: ["computer-science"] },
      now: NOW,
    });
    expect(result.map((r) => r.item.id)).toEqual(["a", "b"]);
  });

  it("respects the limit", () => {
    const many = Array.from({ length: 10 }, (_, i) =>
      makeOpportunity({ id: `o${i}`, title: `O${i}` }),
    );
    const result = recommend({
      opportunities: many,
      events: [],
      organizations,
      profile: { ...emptyProfile, interests: ["computer-science"] },
      now: NOW,
      limit: 3,
    });
    expect(result).toHaveLength(3);
  });
});
