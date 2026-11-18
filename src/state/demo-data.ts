/**
 * Demo-only data for the Organization and Admin workflows.
 *
 * IMPORTANT: this is NOT secure and NOT shared between users. Anything an
 * "organization rep" creates here exists only in this browser. Real
 * multi-user workflows need a backend with server-side authorization.
 */
import { z } from "zod";
import { campusEventBaseSchema } from "@/schemas/domain";
import { createLocalStore } from "./local-store";

export const demoSubmissionSchema = campusEventBaseSchema.extend({
  status: z.enum(["pending", "published", "rejected"]),
  createdAt: z.string(),
  updatedAt: z.string(),
});
export type DemoSubmission = z.infer<typeof demoSubmissionSchema>;

export const moderationDecisionSchema = z.object({
  status: z.enum(["published", "rejected"]),
  note: z.string().max(300),
  decidedAt: z.string(),
});
export type ModerationDecision = z.infer<typeof moderationDecisionSchema>;

export const demoDataSchema = z.object({
  version: z.literal(1),
  submissions: z.array(demoSubmissionSchema).max(100),
  /** Admin decisions on sample pending items, keyed by "type:id". */
  decisions: z.record(z.string(), moderationDecisionSchema),
});
export type DemoData = z.infer<typeof demoDataSchema>;

export const DEFAULT_DEMO_DATA: DemoData = { version: 1, submissions: [], decisions: {} };

export const demoStore = createLocalStore(
  "campusbridge:demo:v1",
  demoDataSchema,
  DEFAULT_DEMO_DATA,
);

/** In the demo, the organization rep manages this fictional organization. */
export const DEMO_ORGANIZATION_ID = "code-circuits";
