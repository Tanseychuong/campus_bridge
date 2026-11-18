/**
 * Validation for the "Create / edit event" form used by organization reps.
 *
 * The same schema is used to show friendly field errors in the browser. When
 * a backend exists it MUST validate again on the server — client-side
 * validation improves the experience but is never a security boundary.
 */
import { z } from "zod";
import { TZDate } from "@date-fns/tz";
import { CAMPUS_TIME_ZONE } from "@/lib/dates";
import { EVENT_CATEGORIES, INTERESTS } from "./taxonomy";

const time = z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/, "Enter a time like 18:30");

export const eventFormSchema = z
  .object({
    title: z
      .string()
      .trim()
      .min(5, "Title must be at least 5 characters")
      .max(120, "Title is too long"),
    category: z.enum(EVENT_CATEGORIES, { message: "Choose a category" }),
    summary: z
      .string()
      .trim()
      .min(10, "Summary must be at least 10 characters")
      .max(220, "Keep the summary under 220 characters"),
    description: z
      .string()
      .trim()
      .min(20, "Description must be at least 20 characters")
      .max(3000, "Description is too long"),
    venue: z.string().trim().min(2, "Enter a venue").max(120, "Venue is too long"),
    date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Choose a date"),
    startTime: time,
    endTime: time,
    capacity: z
      .string()
      .trim()
      .refine((v) => v === "" || (/^\d+$/.test(v) && Number(v) >= 1 && Number(v) <= 5000), {
        message: "Capacity must be a whole number between 1 and 5000, or empty",
      }),
    registration: z.enum(["open", "rsvp"]),
    interests: z.array(z.enum(INTERESTS)).min(1, "Choose at least one interest tag"),
  })
  .refine((v) => v.endTime > v.startTime, {
    message: "End time must be after the start time",
    path: ["endTime"],
  });

export type EventFormValues = z.input<typeof eventFormSchema>;
export type EventFormErrors = Partial<Record<keyof EventFormValues, string>>;

/** Interpret a date + time typed by the user as campus local time. */
export function campusDateTime(date: string, hhmm: string): Date {
  const [y, m, d] = date.split("-").map(Number);
  const [h, min] = hhmm.split(":").map(Number);
  const zoned = new TZDate(y, m - 1, d, h, min, 0, CAMPUS_TIME_ZONE);
  return new Date(zoned.getTime()); // plain Date -> canonical "...Z" ISO strings
}

export type EventFormResult =
  | { ok: true; data: z.output<typeof eventFormSchema>; start: string; end: string }
  | { ok: false; errors: EventFormErrors };

/** Validate the form. Also rejects events that start in the past. */
export function validateEventForm(values: EventFormValues, now: Date): EventFormResult {
  const parsed = eventFormSchema.safeParse(values);
  if (!parsed.success) {
    const errors: EventFormErrors = {};
    for (const issue of parsed.error.issues) {
      const field = issue.path[0] as keyof EventFormValues;
      errors[field] ??= issue.message;
    }
    return { ok: false, errors };
  }
  const start = campusDateTime(parsed.data.date, parsed.data.startTime);
  const end = campusDateTime(parsed.data.date, parsed.data.endTime);
  if (start.getTime() <= now.getTime()) {
    return { ok: false, errors: { date: "The event must start in the future" } };
  }
  return { ok: true, data: parsed.data, start: start.toISOString(), end: end.toISOString() };
}
