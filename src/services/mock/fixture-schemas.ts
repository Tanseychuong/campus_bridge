/**
 * Fixture files store dates RELATIVE to today (e.g. "deadlineInDays": 12)
 * so the sample data never goes stale. These schemas describe that raw
 * fixture shape; `materialize*` functions turn it into real domain objects.
 */
import { z } from "zod";
import { campusEventBaseSchema, opportunitySchema } from "@/schemas/domain";

export const opportunityFixtureSchema = opportunitySchema
  .omit({ deadline: true, publishedAt: true })
  .extend({
    deadlineInDays: z.number().int().min(-365).max(365).nullable(),
    publishedDaysAgo: z.number().int().min(0).max(365),
  });
export type OpportunityFixture = z.infer<typeof opportunityFixtureSchema>;

export const eventFixtureSchema = campusEventBaseSchema.omit({ start: true, end: true }).extend({
  startsInDays: z.number().int().min(-365).max(365),
  startTime: z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/),
  durationMinutes: z
    .number()
    .int()
    .min(15)
    .max(24 * 60),
});
export type EventFixture = z.infer<typeof eventFixtureSchema>;
