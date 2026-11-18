/**
 * Rule-based moderation hints shown to administrators in the review queue.
 * They do not decide anything; they point a human reviewer at common
 * problems (missing details, unverifiable providers, requests for payment).
 */
export interface ReviewableItem {
  title: string;
  description: string;
  provider?: string;
  eligibility?: string[];
  deadline?: string | null;
  start?: string;
}

export interface ModerationFlag {
  severity: "warning" | "critical";
  message: string;
}

const PAYMENT_PATTERN = /\b(pay|payment|fee|deposit|registration charge|send money)\b/i;
const UNVERIFIED_PATTERN = /\b(unknown|unverified|anonymous)\b/i;

export function moderationFlags(item: ReviewableItem, now: Date): ModerationFlag[] {
  const flags: ModerationFlag[] = [];
  if (PAYMENT_PATTERN.test(`${item.title} ${item.description}`)) {
    flags.push({
      severity: "critical",
      message: "Mentions payment or fees — a common sign of scams targeting students",
    });
  }
  if (item.provider !== undefined && UNVERIFIED_PATTERN.test(item.provider)) {
    flags.push({ severity: "critical", message: "Provider cannot be verified" });
  }
  if (item.eligibility !== undefined && item.eligibility.length === 0) {
    flags.push({ severity: "warning", message: "No eligibility information" });
  }
  const when = item.deadline ?? item.start;
  if (when && Date.parse(when) - now.getTime() < 3 * 86_400_000) {
    flags.push({
      severity: "warning",
      message: "Less than 3 days away — students may not have time to act",
    });
  }
  return flags;
}
