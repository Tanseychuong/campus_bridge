"use client";

import Link from "next/link";
import { Lightbulb } from "lucide-react";
import type { Recommendation } from "@/lib/recommend/score";
import { formatEventRange } from "@/lib/dates";
import { deadlineLabel } from "@/lib/deadlines";
import { EVENT_CATEGORY_LABELS, OPPORTUNITY_CATEGORY_LABELS } from "@/schemas/taxonomy";
import { Badge } from "@/components/ui/primitives";
import { SaveEventButton, SaveOpportunityButton } from "./action-buttons";

/** A recommended item together with the human-readable reasons for it. */
export function RecommendationCard({
  recommendation: r,
  subtitle,
  now,
}: {
  recommendation: Recommendation;
  subtitle?: string;
  now: Date;
}) {
  const href = r.type === "opportunity" ? `/opportunities/${r.item.id}` : `/events/${r.item.id}`;
  return (
    <article
      className="card card-interactive relative flex h-full flex-col p-5"
      data-testid="recommendation"
    >
      <div className="flex flex-wrap items-center gap-2">
        <Badge tone={r.type === "opportunity" ? "brand" : "gold"}>
          {r.type === "opportunity"
            ? OPPORTUNITY_CATEGORY_LABELS[r.item.category]
            : EVENT_CATEGORY_LABELS[r.item.category]}
        </Badge>
        <span className="text-muted text-xs">
          {r.type === "opportunity"
            ? deadlineLabel(r.item.deadline, now)
            : formatEventRange(r.item.start, r.item.end)}
        </span>
      </div>
      <h3 className="mt-3 leading-snug font-bold">
        <Link href={href} className="stretched-link hover:text-brand-700">
          {r.item.title}
        </Link>
      </h3>
      {subtitle && <p className="text-muted mt-0.5 text-sm">{subtitle}</p>}

      <div className="bg-brand-50/70 mt-4 flex-1 rounded-xl p-3">
        <p className="text-brand-800 flex items-center gap-1.5 text-xs font-bold">
          <Lightbulb className="size-3.5" aria-hidden /> Why this is recommended
          <span className="text-brand-700 ml-auto rounded-full bg-white px-2 py-0.5 text-[0.7rem]">
            score {r.score}
          </span>
        </p>
        <ul className="mt-2 space-y-1 text-xs text-slate-700">
          {r.reasons.map((reason) => (
            <li key={reason.text} className="flex justify-between gap-3">
              <span>{reason.text}</span>
              <span
                className={
                  reason.points < 0 ? "font-semibold text-red-700" : "text-brand-700 font-semibold"
                }
              >
                {reason.points > 0 ? `+${reason.points}` : reason.points}
              </span>
            </li>
          ))}
        </ul>
      </div>
      <div className="mt-4">
        {r.type === "opportunity" ? (
          <SaveOpportunityButton id={r.item.id} title={r.item.title} />
        ) : (
          <SaveEventButton id={r.item.id} title={r.item.title} />
        )}
      </div>
    </article>
  );
}
