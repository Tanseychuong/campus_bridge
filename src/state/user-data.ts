/**
 * The student's personal demo data: interests, saved items, followed
 * organizations and the selected demo role. Stored in this browser only.
 */
import { z } from "zod";
import { interestSchema } from "@/schemas/taxonomy";
import { createLocalStore } from "./local-store";

export const DEMO_ROLES = ["student", "org-rep", "admin"] as const;
export type DemoRole = (typeof DEMO_ROLES)[number];
export const DEMO_ROLE_LABELS: Record<DemoRole, string> = {
  student: "Student",
  "org-rep": "Organization rep",
  admin: "Administrator",
};

const idList = z.array(z.string().min(1).max(100)).max(500);

export const userDataSchema = z.object({
  version: z.literal(1),
  interests: z.array(interestSchema).max(20),
  savedOpportunityIds: idList,
  savedEventIds: idList,
  followedOrganizationIds: idList,
  role: z.enum(DEMO_ROLES),
});
export type UserData = z.infer<typeof userDataSchema>;

export const DEFAULT_USER_DATA: UserData = {
  version: 1,
  interests: [],
  savedOpportunityIds: [],
  savedEventIds: [],
  followedOrganizationIds: [],
  role: "student",
};

export const USER_DATA_KEY = "campusbridge:user:v1";

export const userStore = createLocalStore(USER_DATA_KEY, userDataSchema, DEFAULT_USER_DATA);

/** Add the id if missing, remove it if present. Pure helper (unit-tested). */
export function toggleId(list: string[], id: string): string[] {
  return list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
}
