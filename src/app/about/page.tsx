import type { Metadata } from "next";
import Link from "next/link";
import { RECOMMENDATION_WEIGHTS as W } from "@/lib/recommend/weights";
import { Notice, PageHeader } from "@/components/ui/primitives";

export const metadata: Metadata = {
  title: "About",
  description: "What CampusBridge is, how its recommendations work and how it handles data.",
};

const RULES = [
  {
    rule: `Each interest the item shares with you (up to ${W.maxInterestMatches})`,
    points: `+${W.interestMatch} each`,
  },
  { rule: "Posted by an organization you follow", points: `+${W.followedOrganization}` },
  { rule: "Same category as items you already saved", points: `+${W.similarToSaved}` },
  { rule: "Deadline 3–14 days away (best time to apply)", points: `+${W.deadlineSoon}` },
  { rule: "Deadline 0–2 or 15–30 days away", points: `+${W.deadlineNear}` },
  { rule: "Event within the next 7 days", points: `+${W.eventThisWeek}` },
  { rule: "Event in 8–14 days", points: `+${W.eventNextWeek}` },
  { rule: "Eligibility information missing", points: `${W.missingInformation}` },
];

export default function AboutPage() {
  return (
    <div className="container-page max-w-3xl">
      <PageHeader eyebrow="About" title="About CampusBridge" />

      <div className="space-y-10 text-slate-700">
        <section aria-labelledby="problem">
          <h2 id="problem" className="text-ink text-xl font-bold">
            The problem
          </h2>
          <p className="mt-3 leading-relaxed">
            University students hear about scholarships, internships, events and clubs through many
            disconnected channels — email, group chats, posters and social media. Our working
            hypotheses are that this makes it easy to miss deadlines, hard to discover activities
            outside your immediate circle, and slow to find what is relevant to you. We are
            validating these hypotheses with a short student survey and interviews; results will be
            reported honestly.
          </p>
        </section>

        <section aria-labelledby="solution">
          <h2 id="solution" className="text-ink text-xl font-bold">
            Our approach
          </h2>
          <ul className="mt-3 list-disc space-y-2 pl-5">
            <li>
              <strong>Unified discovery</strong> — one search across opportunities, events and
              organizations.
            </li>
            <li>
              <strong>Explainable recommendations</strong> — a transparent scoring system, not a
              black box.
            </li>
            <li>
              <strong>Deadline and schedule intelligence</strong> — urgency grouping, clash
              detection and calendar export.
            </li>
            <li>
              <strong>Role-specific workflows</strong> — organization and administrator areas, shown
              as labelled demonstrations.
            </li>
          </ul>
        </section>

        <section aria-labelledby="recommendations-title" id="recommendations">
          <h2 id="recommendations-title" className="text-ink text-xl font-bold">
            How recommendations work
          </h2>
          <p className="mt-3 leading-relaxed">
            CampusBridge uses a <strong>rule-based scoring system</strong>, not machine learning.
            Every published opportunity and upcoming event you have not saved yet earns points from
            the rules below. Items must match at least one of your interests or come from an
            organization you follow. The highest scores are shown first, and each card lists the
            rules that applied.
          </p>
          <div className="card mt-4 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Recommendation scoring rules</caption>
              <thead>
                <tr className="border-line border-b bg-slate-50">
                  <th scope="col" className="p-3 font-semibold">
                    Rule
                  </th>
                  <th scope="col" className="p-3 text-right font-semibold">
                    Points
                  </th>
                </tr>
              </thead>
              <tbody>
                {RULES.map((r) => (
                  <tr key={r.rule} className="border-line border-b last:border-0">
                    <td className="p-3">{r.rule}</td>
                    <td className="p-3 text-right font-semibold tabular-nums">{r.points}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="mt-3 text-sm">
            Ties are broken by the soonest date, then alphabetically, so results are always
            reproducible. You control the inputs on the{" "}
            <Link href="/preferences" className="text-brand-700 font-semibold underline">
              Preferences
            </Link>{" "}
            page.
          </p>
        </section>

        <section aria-labelledby="privacy-title" id="privacy">
          <h2 id="privacy-title" className="text-ink text-xl font-bold">
            Privacy &amp; data
          </h2>
          <ul className="mt-3 list-disc space-y-2 pl-5">
            <li>
              There are no accounts yet. Your interests, saved items and follows are stored only in
              your browser’s localStorage and never sent to a server.
            </li>
            <li>
              Because of that, your data does not sync between devices, and clearing browser data
              removes it.
            </li>
            <li>
              The organization and admin areas are <strong>demonstrations</strong>: anyone can
              switch demo roles, so they are not secure and change nothing for other users.
            </li>
            <li>
              You can delete everything from{" "}
              <Link href="/preferences#data" className="text-brand-700 font-semibold underline">
                Preferences → Your data
              </Link>
              .
            </li>
          </ul>
        </section>

        <Notice title="Independent project disclaimer">
          CampusBridge is a student project for the CS341 Web Technologies course. It is not
          affiliated with, endorsed by or operated by any university. All organizations,
          opportunities, events and figures are fictional sample data; links point to the reserved
          example.org domain.
        </Notice>
      </div>
    </div>
  );
}
