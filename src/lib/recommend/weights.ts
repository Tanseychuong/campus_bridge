/**
 * Recommendation weights, kept in one object so the team can explain,
 * defend and tune them in a single place. Changing a number here changes
 * how the dashboard ranks items — the unit tests document the effect.
 */
export const RECOMMENDATION_WEIGHTS = {
  /** Points per interest the item shares with the student (capped below). */
  interestMatch: 3,
  /** Maximum number of interest matches that earn points. */
  maxInterestMatches: 3,
  /** The item comes from an organization the student follows. */
  followedOrganization: 2,
  /** The student has already saved other items in the same category. */
  similarToSaved: 1,
  /** Opportunity deadline is 3–14 days away: the ideal time to act. */
  deadlineSoon: 2,
  /** Opportunity deadline is 0–2 days away or 15–30 days away. */
  deadlineNear: 1,
  /** Event starts within the next 7 days. */
  eventThisWeek: 2,
  /** Event starts in 8–14 days. */
  eventNextWeek: 1,
  /** Penalty: opportunity is missing eligibility information. */
  missingInformation: -1,
} as const;
