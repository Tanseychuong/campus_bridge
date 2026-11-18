"use client";

/**
 * Administrator workflow (DEMO): a content review queue with automated
 * moderation hints, plus analytics computed from the sample catalogue.
 */
import { useState } from "react";
import { AlertOctagon, AlertTriangle, Check, Inbox, Undo2, X } from "lucide-react";
import type { Catalog } from "@/schemas/domain";
import { moderationFlags, type ModerationFlag } from "@/lib/moderation";
import {
  deadlinesPerWeek,
  openOpportunitiesByCategory,
  upcomingEventsByCategory,
} from "@/lib/analytics";
import { formatDate, formatEventRange } from "@/lib/dates";
import { useDemoData } from "@/state/hooks";
import { Button } from "@/components/ui/button";
import { Field, Textarea } from "@/components/ui/form";
import { Badge, EmptyState, SampleDataBadge, SectionHeading } from "@/components/ui/primitives";
import { BarChart } from "./bar-chart";
import { RoleGate } from "./role-gate";

interface QueueItem {
  key: string;
  type: "opportunity" | "event";
  source: "sample" | "demo";
  title: string;
  by: string;
  when: string;
  summary: string;
  flags: ModerationFlag[];
  status: "pending" | "published" | "rejected";
  note?: string;
}

function buildQueue(
  all: Catalog,
  demo: ReturnType<typeof useDemoData>["data"],
  now: Date,
): QueueItem[] {
  const orgName = new Map(all.organizations.map((o) => [o.id, o.name]));
  const items: QueueItem[] = [];
  for (const o of all.opportunities.filter((x) => x.status === "pending")) {
    const key = `opportunity:${o.id}`;
    const decision = demo.decisions[key];
    items.push({
      key,
      type: "opportunity",
      source: "sample",
      title: o.title,
      by: o.provider,
      when: o.deadline ? `Deadline ${formatDate(o.deadline, "d MMM yyyy")}` : "Rolling deadline",
      summary: o.summary,
      flags: moderationFlags({ ...o }, now),
      status: decision?.status ?? "pending",
      note: decision?.note,
    });
  }
  for (const e of all.events.filter((x) => x.status === "pending")) {
    const key = `event:${e.id}`;
    const decision = demo.decisions[key];
    items.push({
      key,
      type: "event",
      source: "sample",
      title: e.title,
      by: orgName.get(e.organizationId) ?? "Unknown",
      when: formatEventRange(e.start, e.end),
      summary: e.summary,
      flags: moderationFlags({ title: e.title, description: e.description, start: e.start }, now),
      status: decision?.status ?? "pending",
      note: decision?.note,
    });
  }
  for (const s of demo.submissions) {
    items.push({
      key: `demo:${s.id}`,
      type: "event",
      source: "demo",
      title: s.title,
      by: orgName.get(s.organizationId) ?? "Unknown",
      when: formatEventRange(s.start, s.end),
      summary: s.summary,
      flags: moderationFlags({ title: s.title, description: s.description, start: s.start }, now),
      status: s.status,
    });
  }
  return items;
}

export function AdminView({
  allContent,
  publicCatalog,
}: {
  allContent: Catalog;
  publicCatalog: Catalog;
}) {
  const { data, update } = useDemoData();
  const [now] = useState(() => new Date());
  const [rejecting, setRejecting] = useState<string | null>(null);
  const [note, setNote] = useState("");
  const [noteError, setNoteError] = useState("");
  const [message, setMessage] = useState("");

  const queue = buildQueue(allContent, data, now);
  const pending = queue.filter((q) => q.status === "pending");
  const reviewed = queue.filter((q) => q.status !== "pending");

  function decide(
    item: QueueItem,
    status: "published" | "rejected" | "pending",
    decisionNote = "",
  ) {
    update((d) => {
      if (item.source === "demo") {
        const id = item.key.slice("demo:".length);
        return {
          ...d,
          submissions: d.submissions.map((s) =>
            s.id === id ? { ...s, status, updatedAt: new Date().toISOString() } : s,
          ),
        };
      }
      const decisions = { ...d.decisions };
      if (status === "pending") delete decisions[item.key];
      else
        decisions[item.key] = { status, note: decisionNote, decidedAt: new Date().toISOString() };
      return { ...d, decisions };
    });
    setMessage(
      status === "pending"
        ? `“${item.title}” returned to the queue.`
        : `“${item.title}” ${status === "published" ? "approved" : "rejected"}.`,
    );
  }

  function confirmReject(item: QueueItem) {
    if (note.trim().length < 5) {
      setNoteError("Give the organizer a reason (at least 5 characters).");
      return;
    }
    decide(item, "rejected", note.trim());
    setRejecting(null);
    setNote("");
    setNoteError("");
  }

  return (
    <RoleGate role="admin">
      <section aria-labelledby="queue-heading" className="space-y-4">
        <SectionHeading
          id="queue-heading"
          title={`Review queue (${pending.length})`}
          description="New listings wait here until an administrator approves them. Automated hints highlight common problems; the decision is always human."
        />
        <p role="status" className="text-sm font-semibold text-emerald-800">
          {message}
        </p>

        {pending.length === 0 ? (
          <EmptyState
            icon={<Inbox className="size-6" aria-hidden />}
            title="All caught up"
            description="There is nothing waiting for review. Organization reps can submit events from the Manage area."
          />
        ) : (
          <ul className="space-y-4">
            {pending.map((item) => (
              <li key={item.key} className="card p-5">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge tone={item.type === "opportunity" ? "brand" : "gold"}>
                    {item.type === "opportunity" ? "Opportunity" : "Event"}
                  </Badge>
                  {item.source === "sample" ? (
                    <SampleDataBadge />
                  ) : (
                    <Badge>Submitted in this browser</Badge>
                  )}
                </div>
                <h3 className="mt-2 text-lg font-bold">{item.title}</h3>
                <p className="text-muted text-sm">
                  {item.by} · {item.when}
                </p>
                <p className="mt-2 text-sm text-slate-700">{item.summary}</p>

                {item.flags.length > 0 ? (
                  <ul className="mt-3 space-y-1.5" aria-label="Automated review hints">
                    {item.flags.map((f) => (
                      <li
                        key={f.message}
                        className={`flex items-start gap-2 text-sm font-medium ${f.severity === "critical" ? "text-red-800" : "text-amber-900"}`}
                      >
                        {f.severity === "critical" ? (
                          <AlertOctagon className="mt-0.5 size-4 shrink-0" aria-hidden />
                        ) : (
                          <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
                        )}
                        <span>
                          <span className="sr-only">
                            {f.severity === "critical" ? "Critical: " : "Warning: "}
                          </span>
                          {f.message}
                        </span>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="mt-3 flex items-center gap-2 text-sm font-medium text-emerald-800">
                    <Check className="size-4" aria-hidden /> No automated issues found
                  </p>
                )}

                {rejecting === item.key ? (
                  <div className="mt-4 space-y-3 rounded-xl bg-slate-50 p-4">
                    <Field
                      id={`note-${item.key}`}
                      label="Reason for rejection"
                      error={noteError}
                      required
                    >
                      <Textarea
                        id={`note-${item.key}`}
                        value={note}
                        maxLength={300}
                        onChange={(e) => {
                          setNote(e.target.value);
                          setNoteError("");
                        }}
                        invalid={!!noteError}
                        aria-invalid={noteError ? true : undefined}
                        aria-describedby={noteError ? `note-${item.key}-error` : undefined}
                        className="min-h-20"
                      />
                    </Field>
                    <div className="flex gap-2">
                      <Button variant="danger" size="sm" onClick={() => confirmReject(item)}>
                        Confirm rejection
                      </Button>
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => {
                          setRejecting(null);
                          setNoteError("");
                        }}
                      >
                        Cancel
                      </Button>
                    </div>
                  </div>
                ) : (
                  <div className="mt-4 flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      onClick={() => decide(item, "published")}
                      aria-label={`Approve ${item.title}`}
                    >
                      <Check className="size-4" aria-hidden /> Approve
                    </Button>
                    <Button
                      variant="danger"
                      size="sm"
                      onClick={() => {
                        setRejecting(item.key);
                        setNote("");
                      }}
                      aria-label={`Reject ${item.title}`}
                    >
                      <X className="size-4" aria-hidden /> Reject
                    </Button>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}

        {reviewed.length > 0 && (
          <div>
            <h3 className="text-muted mt-6 mb-2 text-sm font-bold uppercase">Recently reviewed</h3>
            <ul className="card divide-line divide-y">
              {reviewed.map((item) => (
                <li
                  key={item.key}
                  className="flex flex-wrap items-center justify-between gap-3 p-4"
                >
                  <div>
                    <p className="font-semibold">{item.title}</p>
                    <p className="text-muted text-sm">
                      <Badge tone={item.status === "published" ? "success" : "danger"}>
                        {item.status === "published" ? "Approved" : "Rejected"}
                      </Badge>
                      {item.note && <span className="ml-2">“{item.note}”</span>}
                    </p>
                  </div>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => decide(item, "pending")}
                    aria-label={`Undo decision for ${item.title}`}
                  >
                    <Undo2 className="size-4" aria-hidden /> Undo
                  </Button>
                </li>
              ))}
            </ul>
            <p className="text-muted mt-2 text-xs">
              In this demo, decisions are stored in this browser and do not change the public
              catalogue.
            </p>
          </div>
        )}
      </section>

      <section aria-labelledby="analytics-heading" className="space-y-4 pt-6">
        <SectionHeading
          id="analytics-heading"
          title="Content analytics"
          description="Aggregated from the fictional sample catalogue. These figures describe the sample content — they are not real university usage statistics."
        />
        <div className="grid gap-4 lg:grid-cols-2">
          <BarChart
            title="Open opportunities by category"
            description="Published opportunities that have not closed."
            data={openOpportunitiesByCategory(publicCatalog, now)}
            unit="opportunities"
          />
          <BarChart
            title="Upcoming events by category"
            description="Published events that have not ended."
            data={upcomingEventsByCategory(publicCatalog, now)}
            unit="events"
          />
        </div>
        <BarChart
          title="Application deadlines per week"
          description="How many opportunity deadlines fall in each of the next 8 weeks — useful for timing reminders."
          data={deadlinesPerWeek(publicCatalog, now)}
          layout="columns"
          unit="deadlines"
        />
      </section>
    </RoleGate>
  );
}
