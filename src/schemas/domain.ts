/**
 * Domain model. These Zod schemas are the single source of truth:
 * TypeScript types are inferred from them, and the same schemas validate
 * data at runtime (fixtures today, API responses later).
 */
import { z } from "zod";
import {
  eventCategorySchema,
  interestSchema,
  locationModeSchema,
  opportunityCategorySchema,
  organizationCategorySchema,
  publicationStatusSchema,
} from "./taxonomy";

/** ISO-8601 timestamp that includes an offset, e.g. 2026-12-01T18:00:00.000Z */
export const isoDateTimeSchema = z.iso.datetime({ offset: true });

/** Only https links are allowed for external sources. */
export const httpsUrlSchema = z.url({ protocol: /^https$/ });

export const organizationSchema = z.object({
  id: z.string().regex(/^[a-z0-9-]+$/),
  name: z.string().min(2).max(100),
  shortName: z.string().min(1).max(12),
  category: organizationCategorySchema,
  summary: z.string().min(10).max(200),
  description: z.string().min(20).max(2000),
  activities: z.array(z.string().min(2).max(120)).max(10),
  interests: z.array(interestSchema).min(1),
  contactEmail: z.email(),
  meetingInfo: z.string().max(200),
  membership: z.string().max(500),
  foundedYear: z.number().int().min(1990).max(2100),
  memberCount: z.number().int().min(0).max(5000),
  color: z.string().regex(/^#[0-9a-fA-F]{6}$/),
});
export type Organization = z.infer<typeof organizationSchema>;

export const opportunitySchema = z.object({
  id: z.string().regex(/^[a-z0-9-]+$/),
  title: z.string().min(5).max(140),
  provider: z.string().min(2).max(100),
  /** Set when the opportunity is posted by a CampusBridge organization. */
  organizationId: z.string().optional(),
  category: opportunityCategorySchema,
  summary: z.string().min(10).max(220),
  description: z.string().min(20).max(3000),
  eligibility: z.array(z.string().min(2).max(200)).max(10),
  location: z.object({ mode: locationModeSchema, place: z.string().max(100).optional() }),
  /** null means rolling / no fixed deadline. */
  deadline: isoDateTimeSchema.nullable(),
  publishedAt: isoDateTimeSchema,
  source: z.object({ name: z.string().min(2).max(100), url: httpsUrlSchema }),
  interests: z.array(interestSchema).min(1),
  status: publicationStatusSchema,
});
export type Opportunity = z.infer<typeof opportunitySchema>;

/** Shape without cross-field rules (needed to derive fixture schemas). */
export const campusEventBaseSchema = z.object({
  id: z.string().regex(/^[a-z0-9-]+$/),
  title: z.string().min(5).max(120),
  organizationId: z.string(),
  category: eventCategorySchema,
  summary: z.string().min(10).max(220),
  description: z.string().min(20).max(3000),
  venue: z.string().min(2).max(120),
  start: isoDateTimeSchema,
  end: isoDateTimeSchema,
  capacity: z.number().int().min(1).max(5000).nullable(),
  registration: z.enum(["open", "rsvp"]),
  contact: z.string().max(200),
  interests: z.array(interestSchema).min(1),
  status: publicationStatusSchema,
});

export const campusEventSchema = campusEventBaseSchema.refine(
  (e) => Date.parse(e.end) > Date.parse(e.start),
  {
    message: "Event must end after it starts",
    path: ["end"],
  },
);
export type CampusEvent = z.infer<typeof campusEventSchema>;

/** Everything the public catalogue contains, after validation. */
export interface Catalog {
  opportunities: Opportunity[];
  events: CampusEvent[];
  organizations: Organization[];
}

export type ItemType = "opportunity" | "event" | "organization";
