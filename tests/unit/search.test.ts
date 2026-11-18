import { describe, expect, it } from "vitest";
import { normalize, tokenize, withinOneEdit } from "@/lib/search/text";
import { buildSearchIndex, matchQuality, scoreDocument, search } from "@/lib/search/engine";
import { DEFAULT_FILTERS, parseExploreParams, serializeExploreParams } from "@/lib/search/params";
import { NOW, daysFromNow, makeEvent, makeOpportunity, makeOrganization } from "../factories";

describe("text processing", () => {
  it("normalises case, accents and punctuation", () => {
    expect(normalize("Café — Résumé!")).toBe("cafe resume");
  });
  it("drops stop words and single letters", () => {
    expect(tokenize("The Art of a Pitch")).toEqual(["art", "pitch"]);
  });
  it("detects strings within one edit", () => {
    expect(withinOneEdit("scholarship", "scholarshp")).toBe(true); // deletion
    expect(withinOneEdit("intern", "interm")).toBe(true); // substitution
    expect(withinOneEdit("intern", "internx")).toBe(true); // insertion
    expect(withinOneEdit("intern", "extern")).toBe(false); // two edits
  });
  it("grades match quality", () => {
    expect(matchQuality("internship", ["internship"])).toBe(1);
    expect(matchQuality("intern", ["internship"])).toBe(0.7);
    expect(matchQuality("scholarshp", ["scholarship"])).toBe(0.4);
    expect(matchQuality("robot", ["finance"])).toBe(0);
  });
});

const catalog = {
  organizations: [makeOrganization({ id: "org-a", name: "Robotics Team" })],
  opportunities: [
    makeOpportunity({
      id: "o1",
      title: "Data Science Internship",
      category: "internship",
      deadline: daysFromNow(5),
    }),
    makeOpportunity({
      id: "o2",
      title: "Arts Grant",
      category: "scholarship",
      interests: ["arts"],
      description: "Funding for creative projects including data visualisation.",
      deadline: daysFromNow(40),
      location: { mode: "in-person" },
    }),
    makeOpportunity({ id: "closed", title: "Closed Internship", deadline: daysFromNow(-1) }),
    makeOpportunity({ id: "pending", title: "Pending Internship", status: "pending" }),
  ],
  events: [
    makeEvent({
      id: "e1",
      title: "Robot Build Day",
      organizationId: "org-a",
      interests: ["engineering"],
    }),
    makeEvent({
      id: "past",
      title: "Past Event",
      start: daysFromNow(-3, "10:00"),
      end: daysFromNow(-3, "11:00"),
    }),
  ],
};
const index = buildSearchIndex(catalog, NOW);

describe("buildSearchIndex", () => {
  it("excludes closed, pending and past items", () => {
    expect(index.map((d) => d.id).sort()).toEqual(["e1", "o1", "o2", "org-a"]);
  });
});

describe("search", () => {
  it("ranks title matches above description matches", () => {
    const { results } = search(index, { ...DEFAULT_FILTERS, q: "data" }, NOW);
    expect(results.map((r) => r.doc.id)).toEqual(["o1", "o2"]);
    expect(results[0].score).toBeGreaterThan(results[1].score);
  });

  it("requires every query word to match (AND)", () => {
    const { results } = search(index, { ...DEFAULT_FILTERS, q: "data internship" }, NOW);
    expect(results.map((r) => r.doc.id)).toEqual(["o1"]);
  });

  it("finds items across all types and counts them per type", () => {
    const { results, counts } = search(index, { ...DEFAULT_FILTERS, q: "robot" }, NOW);
    expect(results.map((r) => r.doc.type).sort()).toEqual(["event", "organization"]);
    expect(counts).toEqual({ all: 2, opportunity: 0, event: 1, organization: 1 });
  });

  it("tolerates a one-letter typo", () => {
    const { results } = search(index, { ...DEFAULT_FILTERS, q: "internshp" }, NOW);
    expect(results.map((r) => r.doc.id)).toEqual(["o1"]);
  });

  it("filters by type while keeping counts for the other tabs", () => {
    const { results, counts } = search(index, { ...DEFAULT_FILTERS, type: "event" }, NOW);
    expect(results.map((r) => r.doc.id)).toEqual(["e1"]);
    expect(counts.all).toBe(4);
  });

  it("filters by interest, location mode and date window", () => {
    expect(
      search(index, { ...DEFAULT_FILTERS, interests: ["arts"] }, NOW).results.map((r) => r.doc.id),
    ).toEqual(["o2"]);
    expect(
      search(
        index,
        { ...DEFAULT_FILTERS, type: "opportunity", mode: "in-person" },
        NOW,
      ).results.map((r) => r.doc.id),
    ).toEqual(["o2"]);
    expect(
      search(index, { ...DEFAULT_FILTERS, within: "7" }, NOW)
        .results.map((r) => r.doc.id)
        .sort(),
    ).toEqual(["e1", "o1"]);
  });

  it("sorts by soonest date with undated items last", () => {
    const { results } = search(index, { ...DEFAULT_FILTERS, sort: "soonest" }, NOW);
    expect(results.map((r) => r.doc.id)).toEqual(["e1", "o1", "o2", "org-a"]);
  });

  it("returns an empty list (not an error) when nothing matches", () => {
    expect(search(index, { ...DEFAULT_FILTERS, q: "zzzzzz" }, NOW).results).toEqual([]);
  });

  it("scores zero for queries made only of stop words", () => {
    expect(scoreDocument(index[0], "the and of")).toBe(0);
  });
});

describe("URL parameters", () => {
  it("parses valid parameters", () => {
    const f = parseExploreParams({
      q: " robotics ",
      type: "opportunity",
      interest: ["arts", "research"],
      category: "internship",
      mode: "remote",
      within: "7",
      sort: "soonest",
    });
    expect(f).toEqual({
      q: "robotics",
      type: "opportunity",
      interests: ["arts", "research"],
      category: "internship",
      within: "7",
      mode: "remote",
      sort: "soonest",
    });
  });

  it("falls back to safe defaults for invalid or malicious values", () => {
    const f = parseExploreParams({
      type: "<script>",
      interest: ["arts", "not-real", "arts"],
      category: "internship", // ignored because no type is selected
      within: "999",
      sort: "drop table",
    });
    expect(f).toEqual({ ...DEFAULT_FILTERS, interests: ["arts"] });
  });

  it("rejects a category that does not belong to the selected type", () => {
    expect(parseExploreParams({ type: "event", category: "internship" }).category).toBeNull();
  });

  it("round-trips through serialisation", () => {
    const f = parseExploreParams({ q: "data", type: "event", interest: ["arts"], within: "30" });
    expect(
      parseExploreParams(Object.fromEntries(new URLSearchParams(serializeExploreParams(f)))),
    ).toMatchObject({
      q: "data",
      type: "event",
      within: "30",
    });
    expect(serializeExploreParams(DEFAULT_FILTERS)).toBe("");
  });
});
