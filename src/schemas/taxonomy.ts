/**
 * Shared vocabularies (enums) used across the domain.
 * Keeping them in one place means the UI labels, validation and
 * recommendation logic can never disagree about what values exist.
 */
import { z } from "zod";

export const INTERESTS = [
  "computer-science",
  "engineering",
  "entrepreneurship",
  "research",
  "business",
  "social-impact",
  "sustainability",
  "arts",
  "leadership",
  "health",
] as const;
export const interestSchema = z.enum(INTERESTS);
export type Interest = z.infer<typeof interestSchema>;

export const INTEREST_LABELS: Record<Interest, string> = {
  "computer-science": "Computer Science",
  engineering: "Engineering",
  entrepreneurship: "Entrepreneurship",
  research: "Research",
  business: "Business",
  "social-impact": "Social Impact",
  sustainability: "Sustainability",
  arts: "Arts & Culture",
  leadership: "Leadership",
  health: "Health & Wellbeing",
};

export const OPPORTUNITY_CATEGORIES = [
  "scholarship",
  "internship",
  "research",
  "fellowship",
  "competition",
  "hackathon",
  "leadership",
  "volunteering",
  "career",
] as const;
export const opportunityCategorySchema = z.enum(OPPORTUNITY_CATEGORIES);
export type OpportunityCategory = z.infer<typeof opportunityCategorySchema>;

export const OPPORTUNITY_CATEGORY_LABELS: Record<OpportunityCategory, string> = {
  scholarship: "Scholarship",
  internship: "Internship",
  research: "Research",
  fellowship: "Fellowship",
  competition: "Competition",
  hackathon: "Hackathon",
  leadership: "Leadership Program",
  volunteering: "Volunteering",
  career: "Career Development",
};

export const EVENT_CATEGORIES = [
  "academic",
  "career",
  "social",
  "tech",
  "arts",
  "sports",
  "wellness",
  "community",
] as const;
export const eventCategorySchema = z.enum(EVENT_CATEGORIES);
export type EventCategory = z.infer<typeof eventCategorySchema>;

export const EVENT_CATEGORY_LABELS: Record<EventCategory, string> = {
  academic: "Academic",
  career: "Career",
  social: "Social",
  tech: "Tech",
  arts: "Arts",
  sports: "Sports",
  wellness: "Wellness",
  community: "Community",
};

export const ORGANIZATION_CATEGORIES = [
  "academic",
  "professional",
  "tech",
  "entrepreneurship",
  "service",
  "arts",
  "sports",
  "cultural",
] as const;
export const organizationCategorySchema = z.enum(ORGANIZATION_CATEGORIES);
export type OrganizationCategory = z.infer<typeof organizationCategorySchema>;

export const ORGANIZATION_CATEGORY_LABELS: Record<OrganizationCategory, string> = {
  academic: "Academic",
  professional: "Professional",
  tech: "Technology",
  entrepreneurship: "Entrepreneurship",
  service: "Service & Volunteering",
  arts: "Arts & Media",
  sports: "Sports & Fitness",
  cultural: "Cultural",
};

export const LOCATION_MODES = ["on-campus", "in-person", "remote", "hybrid"] as const;
export const locationModeSchema = z.enum(LOCATION_MODES);
export type LocationMode = z.infer<typeof locationModeSchema>;

export const LOCATION_MODE_LABELS: Record<LocationMode, string> = {
  "on-campus": "On campus",
  "in-person": "In person (off campus)",
  remote: "Remote",
  hybrid: "Hybrid",
};

export const PUBLICATION_STATUSES = ["published", "pending", "rejected"] as const;
export const publicationStatusSchema = z.enum(PUBLICATION_STATUSES);
export type PublicationStatus = z.infer<typeof publicationStatusSchema>;
