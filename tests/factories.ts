/**
 * Small builders for test data. Each test overrides only the fields it cares
 * about, which keeps tests short and their intent obvious.
 */
import type { CampusEvent, Opportunity, Organization } from "@/schemas/domain";

export const NOW = new Date("2026-11-18T10:00:00.000Z");

export function daysFromNow(days: number, hhmm = "23:59"): string {
  const [h, m] = hhmm.split(":").map(Number);
  const d = new Date(NOW);
  d.setUTCDate(d.getUTCDate() + days);
  d.setUTCHours(h, m, 0, 0);
  return d.toISOString();
}

export function makeOpportunity(overrides: Partial<Opportunity> = {}): Opportunity {
  return {
    id: "opp-test",
    title: "Test Opportunity",
    provider: "Test Provider",
    category: "internship",
    summary: "A short test summary.",
    description: "A longer description used only in tests.",
    eligibility: ["Any student"],
    location: { mode: "remote" },
    deadline: daysFromNow(10),
    publishedAt: daysFromNow(-5, "09:00"),
    source: { name: "Test", url: "https://example.org/test" },
    interests: ["computer-science"],
    status: "published",
    ...overrides,
  };
}

export function makeEvent(overrides: Partial<CampusEvent> = {}): CampusEvent {
  return {
    id: "evt-test",
    title: "Test Event",
    organizationId: "org-a",
    category: "tech",
    summary: "A short test summary.",
    description: "A longer description used only in tests.",
    venue: "Room 1",
    start: daysFromNow(3, "18:00"),
    end: daysFromNow(3, "19:30"),
    capacity: 30,
    registration: "open",
    contact: "test@example.org",
    interests: ["computer-science"],
    status: "published",
    ...overrides,
  };
}

export function makeOrganization(overrides: Partial<Organization> = {}): Organization {
  return {
    id: "org-a",
    name: "Org A Society",
    shortName: "OA",
    category: "tech",
    summary: "A test organization summary.",
    description: "A longer description of the test organization.",
    activities: ["Workshops"],
    interests: ["computer-science"],
    contactEmail: "orga@example.org",
    meetingInfo: "Mondays",
    membership: "Open to all",
    foundedYear: 2020,
    memberCount: 10,
    color: "#123456",
    ...overrides,
  };
}
