import Link from "next/link";
import { ArrowRight, BellRing, CalendarRange, Compass, Lightbulb, Sparkles } from "lucide-react";
import { catalogRepository } from "@/services";
import { compareDeadlines, isClosed } from "@/lib/deadlines";
import { calendarDaysBetween } from "@/lib/dates";
import { SearchBox } from "@/components/features/search-box";
import { EventCard, OpportunityCard } from "@/components/features/cards";
import { LinkButton } from "@/components/ui/button";
import { Notice, SampleDataBadge, SectionHeading } from "@/components/ui/primitives";

// Sample dates are relative to "today", so regenerate this page hourly (ISR).
export const revalidate = 3600;

export default async function HomePage() {
  const now = new Date();
  const catalog = await catalogRepository.getCatalog();
  const orgs = new Map(catalog.organizations.map((o) => [o.id, o]));

  const openOpportunities = catalog.opportunities.filter((o) => !isClosed(o.deadline, now));
  const closingSoon = openOpportunities
    .filter((o) => o.deadline !== null)
    .sort(compareDeadlines)
    .slice(0, 3);
  const thisWeek = catalog.events
    .filter((e) => Date.parse(e.end) > now.getTime() && calendarDaysBetween(now, e.start) <= 7)
    .sort((a, b) => Date.parse(a.start) - Date.parse(b.start))
    .slice(0, 3);

  const stats = [
    { label: "open opportunities", value: openOpportunities.length },
    {
      label: "upcoming events",
      value: catalog.events.filter((e) => Date.parse(e.end) > now.getTime()).length,
    },
    { label: "student organizations", value: catalog.organizations.length },
  ];

  return (
    <>
      <section className="border-line relative overflow-hidden border-b bg-white">
        <div
          aria-hidden
          className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,var(--color-brand-100),transparent_55%),radial-gradient(ellipse_at_bottom_left,var(--color-gold-50),transparent_50%)]"
        />
        <div className="container-page relative grid gap-10 py-14 md:py-20 lg:grid-cols-[1.25fr_1fr] lg:items-center">
          <div>
            <p className="border-brand-100 text-brand-700 inline-flex items-center gap-2 rounded-full border bg-white px-3 py-1 text-xs font-semibold">
              <Sparkles className="size-3.5" aria-hidden /> Explainable recommendations · Deadline
              tracking
            </p>
            <h1 className="text-ink mt-5 text-4xl leading-[1.1] font-extrabold tracking-tight sm:text-5xl">
              Every campus opportunity, <span className="text-brand-600">one bridge away.</span>
            </h1>
            <p className="text-muted mt-5 max-w-xl text-lg">
              Scholarships, internships, events and student organizations — in one place, ranked for
              your interests, with the deadlines you can’t afford to miss.
            </p>
            <div className="mt-8 max-w-xl">
              <SearchBox size="lg" />
            </div>
            <div className="mt-5 flex flex-wrap gap-3">
              <LinkButton href="/preferences" variant="gold">
                Personalise my feed <ArrowRight className="size-4" aria-hidden />
              </LinkButton>
              <LinkButton href="/explore?type=opportunity" variant="secondary">
                Browse opportunities
              </LinkButton>
            </div>
          </div>

          <div className="card p-5">
            <div className="flex items-center justify-between">
              <h2 className="text-ink text-sm font-bold">Closing soon</h2>
              <SampleDataBadge />
            </div>
            <ul className="divide-line mt-3 divide-y">
              {closingSoon.map((o) => (
                <li key={o.id} className="py-3">
                  <Link href={`/opportunities/${o.id}`} className="group block rounded-lg">
                    <span className="text-ink group-hover:text-brand-700 font-semibold">
                      {o.title}
                    </span>
                    <span className="text-muted mt-0.5 block text-xs">
                      {o.provider} · {calendarDaysBetween(now, o.deadline!)} days left
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
            <dl className="border-line mt-4 grid grid-cols-3 gap-2 border-t pt-4 text-center">
              {stats.map((s) => (
                <div key={s.label} className="flex flex-col-reverse">
                  <dt className="text-muted text-[0.7rem] leading-tight">{s.label}</dt>
                  <dd className="text-brand-700 text-2xl font-extrabold">{s.value}</dd>
                </div>
              ))}
            </dl>
          </div>
        </div>
      </section>

      <section className="container-page py-14" aria-labelledby="why-heading">
        <h2 id="why-heading" className="text-2xl font-bold tracking-tight">
          Built for the way students actually find opportunities
        </h2>
        <p className="text-muted mt-2 max-w-2xl">
          Opportunities are often scattered across email, group chats and posters, so deadlines are
          easy to miss. CampusBridge brings them together and helps you act on time.
        </p>
        <div className="mt-8 grid gap-4 md:grid-cols-3">
          {[
            {
              icon: Compass,
              title: "One search for everything",
              text: "Search opportunities, events and organizations together, with filters that stay in the URL so you can share them.",
            },
            {
              icon: Lightbulb,
              title: "Recommendations you can understand",
              text: "Every suggestion explains why: “Matches your interests: Research” or “Deadline in 6 days”. Change your interests and the ranking updates instantly.",
            },
            {
              icon: BellRing,
              title: "Deadlines and clashes, handled",
              text: "Saved deadlines are grouped by urgency, your calendar warns about overlapping events, and you can export everything to your own calendar app.",
            },
          ].map(({ icon: Icon, title, text }) => (
            <div key={title} className="card p-6">
              <div className="bg-brand-50 text-brand-600 flex size-11 items-center justify-center rounded-xl">
                <Icon className="size-5" aria-hidden />
              </div>
              <h3 className="mt-4 font-bold">{title}</h3>
              <p className="text-muted mt-2 text-sm">{text}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="container-page pb-14" aria-labelledby="closing-heading">
        <SectionHeading
          id="closing-heading"
          title="Deadlines coming up"
          description="Open opportunities sorted by deadline."
          action={
            <LinkButton href="/explore?type=opportunity&sort=soonest" variant="ghost">
              See all <ArrowRight className="size-4" aria-hidden />
            </LinkButton>
          }
        />
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {openOpportunities
            .filter((o) => o.deadline)
            .sort(compareDeadlines)
            .slice(3, 6)
            .map((o) => (
              <OpportunityCard key={o.id} opportunity={o} now={now} />
            ))}
        </div>
      </section>

      <section className="container-page pb-14" aria-labelledby="week-heading">
        <SectionHeading
          id="week-heading"
          title="This week on campus"
          action={
            <LinkButton href="/events" variant="ghost">
              All events <CalendarRange className="size-4" aria-hidden />
            </LinkButton>
          }
        />
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {thisWeek.map((e) => (
            <EventCard key={e.id} event={e} organization={orgs.get(e.organizationId)} />
          ))}
        </div>
      </section>

      <section className="container-page">
        <Notice title="An independent student project">
          CampusBridge was built for the CS341 Web Technologies course. It is not affiliated with or
          endorsed by any university, and every organization, opportunity and event shown is
          fictional sample data.{" "}
          <Link href="/about" className="font-semibold underline">
            Learn more
          </Link>
          .
        </Notice>
      </section>
    </>
  );
}
