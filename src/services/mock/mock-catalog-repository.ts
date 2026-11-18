/**
 * Mock implementation of CatalogRepository using local JSON fixtures.
 *
 * Reliability: every record is validated with Zod. Malformed records are
 * skipped (and reported in the server log) instead of crashing the app.
 *
 * All content is FICTIONAL sample data for demonstration purposes.
 */
import { z } from "zod";
import {
  campusEventSchema,
  opportunitySchema,
  organizationSchema,
  type Catalog,
  type CampusEvent,
  type Opportunity,
  type Organization,
} from "@/schemas/domain";
import { startOfCampusDay } from "@/lib/dates";
import type { CatalogRepository } from "../catalog-repository";
import {
  eventFixtureSchema,
  opportunityFixtureSchema,
  type EventFixture,
  type OpportunityFixture,
} from "./fixture-schemas";
import rawOrganizations from "@/data/fixtures/organizations.json";
import rawOpportunities from "@/data/fixtures/opportunities.json";
import rawEvents from "@/data/fixtures/events.json";

/** Validate a list, keeping valid records and logging invalid ones. */
export function validateRecords<T>(schema: z.ZodType<T>, records: unknown[], label: string): T[] {
  const valid: T[] = [];
  records.forEach((record, index) => {
    const result = schema.safeParse(record);
    if (result.success) {
      valid.push(result.data);
    } else {
      const id = (record as { id?: unknown })?.id ?? `#${index}`;
      console.warn(
        `[catalog] Skipping invalid ${label} ${String(id)}: ${result.error.issues[0]?.message}`,
      );
    }
  });
  return valid;
}

function atDayOffset(anchor: Date, days: number, hours: number, minutes: number): Date {
  const d = startOfCampusDay(anchor);
  d.setDate(d.getDate() + days);
  d.setHours(hours, minutes, 0, 0);
  return new Date(d.getTime()); // plain Date -> canonical "...Z" ISO strings
}

export function materializeOpportunity(f: OpportunityFixture, now: Date): Opportunity {
  const { deadlineInDays, publishedDaysAgo, ...rest } = f;
  return {
    ...rest,
    deadline:
      deadlineInDays === null ? null : atDayOffset(now, deadlineInDays, 23, 59).toISOString(),
    publishedAt: atDayOffset(now, -publishedDaysAgo, 9, 0).toISOString(),
  };
}

export function materializeEvent(f: EventFixture, now: Date): CampusEvent {
  const { startsInDays, startTime, durationMinutes, ...rest } = f;
  const [h, m] = startTime.split(":").map(Number);
  const start = atDayOffset(now, startsInDays, h, m);
  const end = new Date(start.getTime() + durationMinutes * 60_000);
  return { ...rest, start: start.toISOString(), end: end.toISOString() };
}

export interface FixtureSource {
  organizations: unknown[];
  opportunities: unknown[];
  events: unknown[];
}

const defaultSource: FixtureSource = {
  organizations: rawOrganizations,
  opportunities: rawOpportunities,
  events: rawEvents,
};

/** Build the full catalogue (all statuses) for a given "now". */
export function loadCatalog(source: FixtureSource, now: Date): Catalog {
  const organizations = validateRecords(organizationSchema, source.organizations, "organization");
  const orgIds = new Set(organizations.map((o) => o.id));

  const opportunities = validateRecords(
    opportunityFixtureSchema,
    source.opportunities,
    "opportunity",
  )
    .map((f) => materializeOpportunity(f, now))
    .filter((o) => opportunitySchema.safeParse(o).success)
    // An opportunity may reference an organization; drop broken references.
    .filter((o) => !o.organizationId || orgIds.has(o.organizationId));

  const events = validateRecords(eventFixtureSchema, source.events, "event")
    .map((f) => materializeEvent(f, now))
    .filter((e) => campusEventSchema.safeParse(e).success)
    .filter((e) => orgIds.has(e.organizationId));

  return { organizations, opportunities, events };
}

function publishedOnly(catalog: Catalog): Catalog {
  return {
    organizations: catalog.organizations,
    opportunities: catalog.opportunities.filter((o) => o.status === "published"),
    events: catalog.events.filter((e) => e.status === "published"),
  };
}

export class MockCatalogRepository implements CatalogRepository {
  constructor(
    private readonly source: FixtureSource = defaultSource,
    private readonly clock: () => Date = () => new Date(),
  ) {}

  private load(): Catalog {
    return loadCatalog(this.source, this.clock());
  }

  async getCatalog(): Promise<Catalog> {
    return publishedOnly(this.load());
  }

  async getAllForReview(): Promise<Catalog> {
    return this.load();
  }

  async getOpportunity(id: string): Promise<Opportunity | null> {
    return (await this.getCatalog()).opportunities.find((o) => o.id === id) ?? null;
  }

  async getEvent(id: string): Promise<CampusEvent | null> {
    return (await this.getCatalog()).events.find((e) => e.id === id) ?? null;
  }

  async getOrganization(id: string): Promise<Organization | null> {
    return (await this.getCatalog()).organizations.find((o) => o.id === id) ?? null;
  }
}
