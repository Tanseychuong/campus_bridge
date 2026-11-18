import { describe, expect, it } from "vitest";
import { moderationFlags } from "@/lib/moderation";
import { countBy, deadlinesPerWeek, openOpportunitiesByCategory } from "@/lib/analytics";
import { NOW, daysFromNow, makeOpportunity } from "../factories";

describe("moderation hints", () => {
  it("flags payment requests and unverifiable providers as critical", () => {
    const flags = moderationFlags(
      {
        title: "Survey jobs",
        description: "Pay a small fee to register.",
        provider: "Unknown poster",
        eligibility: [],
        deadline: daysFromNow(10),
      },
      NOW,
    );
    expect(flags.filter((f) => f.severity === "critical")).toHaveLength(2);
    expect(flags.some((f) => f.message.includes("eligibility"))).toBe(true);
  });

  it("warns when an item is less than three days away", () => {
    const flags = moderationFlags(
      { title: "Talk", description: "A talk.", start: daysFromNow(1, "18:00") },
      NOW,
    );
    expect(flags).toEqual([expect.objectContaining({ severity: "warning" })]);
  });

  it("returns no flags for a clean listing", () => {
    expect(
      moderationFlags(
        {
          title: "Workshop",
          description: "Learn to code.",
          provider: "Code Club",
          eligibility: ["All"],
          deadline: daysFromNow(20),
        },
        NOW,
      ),
    ).toEqual([]);
  });
});

describe("analytics", () => {
  it("counts by key, keeping zero categories in label order", () => {
    expect(countBy([{ k: "a" }, { k: "a" }], (x) => x.k, { a: "A", b: "B" })).toEqual([
      { label: "A", value: 2 },
      { label: "B", value: 0 },
    ]);
  });

  it("excludes closed and unpublished opportunities", () => {
    const catalog = {
      organizations: [],
      events: [],
      opportunities: [
        makeOpportunity({ id: "a", category: "internship" }),
        makeOpportunity({ id: "b", category: "internship", deadline: daysFromNow(-2) }),
        makeOpportunity({ id: "c", category: "internship", status: "pending" }),
      ],
    };
    const internships = openOpportunitiesByCategory(catalog, NOW).find(
      (d) => d.label === "Internship",
    );
    expect(internships?.value).toBe(1);
  });

  it("buckets deadlines into weeks starting today", () => {
    const catalog = {
      organizations: [],
      events: [],
      opportunities: [
        makeOpportunity({ id: "a", deadline: daysFromNow(1) }),
        makeOpportunity({ id: "b", deadline: daysFromNow(8) }),
        makeOpportunity({ id: "c", deadline: daysFromNow(100) }),
        makeOpportunity({ id: "d", deadline: null }),
      ],
    };
    const weeks = deadlinesPerWeek(catalog, NOW, 4);
    expect(weeks.map((w) => w.value)).toEqual([1, 1, 0, 0]);
    expect(weeks[0].label).toBe("w/c 18 Nov");
  });
});
