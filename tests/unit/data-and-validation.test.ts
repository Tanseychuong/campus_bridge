import { describe, expect, it, vi } from "vitest";
import { loadCatalog, MockCatalogRepository } from "@/services/mock/mock-catalog-repository";
import rawOrganizations from "@/data/fixtures/organizations.json";
import rawOpportunities from "@/data/fixtures/opportunities.json";
import rawEvents from "@/data/fixtures/events.json";
import { validateEventForm, type EventFormValues } from "@/schemas/event-form";
import { isSafeExternalUrl } from "@/lib/safe-url";
import { contrastRatio } from "@/lib/contrast";
import { createLocalStore } from "@/state/local-store";
import { DEFAULT_USER_DATA, toggleId, userDataSchema } from "@/state/user-data";
import { NOW } from "../factories";

describe("fixtures", () => {
  it("are all valid (no record is silently dropped)", () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const catalog = loadCatalog(
      { organizations: rawOrganizations, opportunities: rawOpportunities, events: rawEvents },
      NOW,
    );
    expect(warn).not.toHaveBeenCalled();
    expect(catalog.organizations).toHaveLength(rawOrganizations.length);
    expect(catalog.opportunities).toHaveLength(rawOpportunities.length);
    expect(catalog.events).toHaveLength(rawEvents.length);
    warn.mockRestore();
  });

  it("use organization colours with at least 4.5:1 contrast against white text", () => {
    for (const o of rawOrganizations) {
      expect(contrastRatio(o.color, "#ffffff"), `${o.id} ${o.color}`).toBeGreaterThanOrEqual(4.5);
    }
  });

  it("only use the reserved example.org domain for sample links and emails", () => {
    for (const o of rawOpportunities) expect(new URL(o.source.url).hostname).toBe("example.org");
    for (const o of rawOrganizations) expect(o.contactEmail.endsWith("@example.org")).toBe(true);
  });
});

describe("mock repository reliability", () => {
  it("skips malformed records instead of crashing", async () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const repo = new MockCatalogRepository(
      {
        organizations: [rawOrganizations[0], { id: "BAD ID", name: 42 }],
        opportunities: [{ ...rawOpportunities[0], organizationId: undefined }, { id: "broken" }],
        events: [rawEvents[0], { ...rawEvents[1], organizationId: "does-not-exist" }],
      },
      () => NOW,
    );
    const catalog = await repo.getCatalog();
    expect(catalog.organizations).toHaveLength(1);
    expect(catalog.opportunities).toHaveLength(1);
    expect(catalog.events).toHaveLength(1); // broken organization reference dropped
    expect(warn).toHaveBeenCalledTimes(2);
    warn.mockRestore();
  });

  it("hides unpublished items from the public catalogue but not from review", async () => {
    const repo = new MockCatalogRepository(undefined, () => NOW);
    const pub = await repo.getCatalog();
    const all = await repo.getAllForReview();
    expect(pub.opportunities.every((o) => o.status === "published")).toBe(true);
    expect(all.opportunities.some((o) => o.status === "pending")).toBe(true);
    expect(await repo.getOpportunity("opp-pending-cloud-scholarship")).toBeNull();
    expect(await repo.getOpportunity("does-not-exist")).toBeNull();
  });

  it("materialises relative fixture dates against the clock", async () => {
    const repo = new MockCatalogRepository(undefined, () => NOW);
    const opp = await repo.getOpportunity("opp-volta-stem-scholarship"); // deadlineInDays: 12
    expect(opp?.deadline).toBe("2026-11-30T23:59:00.000Z");
  });
});

describe("event form validation", () => {
  const valid: EventFormValues = {
    title: "Intro to Robotics",
    category: "tech",
    summary: "Build your first robot in an afternoon.",
    description: "A hands-on session for beginners covering motors and sensors.",
    venue: "Fabrication Lab",
    date: "2026-11-25",
    startTime: "14:00",
    endTime: "16:00",
    capacity: "20",
    registration: "rsvp",
    interests: ["engineering"],
  };

  it("accepts a valid event and converts times to UTC ISO strings", () => {
    const result = validateEventForm(valid, NOW);
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.start).toBe("2026-11-25T14:00:00.000Z");
      expect(result.end).toBe("2026-11-25T16:00:00.000Z");
    }
  });

  it("returns one friendly message per invalid field", () => {
    const result = validateEventForm(
      { ...valid, title: "Hi", endTime: "13:00", capacity: "-5", interests: [] },
      NOW,
    );
    expect(result.ok).toBe(false);
    if (!result.ok) {
      expect(result.errors.title).toMatch(/at least 5/);
      expect(result.errors.capacity).toMatch(/whole number/);
      expect(result.errors.interests).toMatch(/at least one/);
    }
  });

  it("rejects an end time before the start time", () => {
    const result = validateEventForm({ ...valid, endTime: "13:00" }, NOW);
    expect(result.ok).toBe(false);
    if (!result.ok) expect(result.errors.endTime).toMatch(/after the start/);
  });

  it("rejects events in the past", () => {
    const result = validateEventForm({ ...valid, date: "2026-11-01" }, NOW);
    expect(result.ok).toBe(false);
    if (!result.ok) expect(result.errors.date).toMatch(/future/);
  });
});

describe("safe external links", () => {
  it("allows only https", () => {
    expect(isSafeExternalUrl("https://example.org")).toBe(true);
    expect(isSafeExternalUrl("http://example.org")).toBe(false);
    expect(isSafeExternalUrl("javascript:alert(1)")).toBe(false);
    expect(isSafeExternalUrl("not a url")).toBe(false);
  });
});

describe("local store", () => {
  it("falls back to defaults when stored data is corrupt or tampered with", () => {
    window.localStorage.setItem("test:key", "{not json");
    const store = createLocalStore("test:key", userDataSchema, DEFAULT_USER_DATA);
    expect(store.getSnapshot()).toEqual(DEFAULT_USER_DATA);

    window.localStorage.setItem(
      "test:key2",
      JSON.stringify({ ...DEFAULT_USER_DATA, role: "superuser" }),
    );
    const store2 = createLocalStore("test:key2", userDataSchema, DEFAULT_USER_DATA);
    expect(store2.getSnapshot()).toEqual(DEFAULT_USER_DATA);
  });

  it("persists updates and notifies subscribers", () => {
    const store = createLocalStore("test:key3", userDataSchema, DEFAULT_USER_DATA);
    const listener = vi.fn();
    const unsubscribe = store.subscribe(listener);
    store.update((d) => ({ ...d, interests: ["arts"] }));
    expect(listener).toHaveBeenCalledTimes(1);
    expect(JSON.parse(window.localStorage.getItem("test:key3")!).interests).toEqual(["arts"]);
    unsubscribe();
  });

  it("refuses to persist invalid data", () => {
    const store = createLocalStore("test:key4", userDataSchema, DEFAULT_USER_DATA);
    expect(() => store.update((d) => ({ ...d, role: "hacker" as never }))).toThrow();
    expect(window.localStorage.getItem("test:key4")).toBeNull();
  });

  it("toggles ids", () => {
    expect(toggleId(["a"], "b")).toEqual(["a", "b"]);
    expect(toggleId(["a", "b"], "a")).toEqual(["b"]);
  });
});
