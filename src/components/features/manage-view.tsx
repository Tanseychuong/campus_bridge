"use client";

/**
 * Organization representative workflow (DEMO). The rep manages the fictional
 * "Code & Circuits Society": they can create, edit and delete event
 * submissions. New submissions go to the admin review queue as "pending".
 */
import Link from "next/link";
import { useState } from "react";
import { CalendarPlus, Pencil, Trash2 } from "lucide-react";
import type { CampusEvent, Organization } from "@/schemas/domain";
import { formatEventRange } from "@/lib/dates";
import { useDemoData } from "@/state/hooks";
import type { DemoSubmission } from "@/state/demo-data";
import type { EventFormValues } from "@/schemas/event-form";
import { EVENT_CATEGORY_LABELS } from "@/schemas/taxonomy";
import { Button } from "@/components/ui/button";
import { Badge, EmptyState, SectionHeading } from "@/components/ui/primitives";
import { OrgAvatar } from "@/components/ui/org-avatar";
import { EventForm, type EventFormSubmit } from "./event-form";
import { RoleGate } from "./role-gate";

const STATUS_TONE = { pending: "warning", published: "success", rejected: "danger" } as const;
const STATUS_LABEL = {
  pending: "Pending review",
  published: "Approved",
  rejected: "Rejected",
} as const;

function toFormValues(s: DemoSubmission): EventFormValues {
  return {
    title: s.title,
    category: s.category,
    summary: s.summary,
    description: s.description,
    venue: s.venue,
    date: s.start.slice(0, 10),
    startTime: s.start.slice(11, 16),
    endTime: s.end.slice(11, 16),
    capacity: s.capacity === null ? "" : String(s.capacity),
    registration: s.registration,
    interests: s.interests,
  };
}

function newId() {
  return `demo-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}

export function ManageView({
  organization,
  publishedEvents,
}: {
  organization: Organization;
  publishedEvents: CampusEvent[];
}) {
  const { data, update } = useDemoData();
  const [mode, setMode] = useState<
    { kind: "idle" } | { kind: "create" } | { kind: "edit"; id: string }
  >({ kind: "idle" });
  const [message, setMessage] = useState("");
  const submissions = data.submissions.filter((s) => s.organizationId === organization.id);

  function save({ values, start, end }: EventFormSubmit, existing?: DemoSubmission) {
    const nowIso = new Date().toISOString();
    const submission: DemoSubmission = {
      id: existing?.id ?? newId(),
      organizationId: organization.id,
      title: values.title.trim(),
      category: values.category,
      summary: values.summary.trim(),
      description: values.description.trim(),
      venue: values.venue.trim(),
      start,
      end,
      capacity: values.capacity === "" ? null : Number(values.capacity),
      registration: values.registration,
      contact: organization.contactEmail,
      interests: values.interests,
      // Editing sends the event back for review.
      status: "pending",
      createdAt: existing?.createdAt ?? nowIso,
      updatedAt: nowIso,
    };
    update((d) => ({
      ...d,
      submissions: existing
        ? d.submissions.map((s) => (s.id === existing.id ? submission : s))
        : [submission, ...d.submissions],
    }));
    setMode({ kind: "idle" });
    setMessage(
      existing
        ? `“${submission.title}” updated and sent for review again.`
        : `“${submission.title}” submitted for admin review.`,
    );
  }

  function remove(s: DemoSubmission) {
    update((d) => ({ ...d, submissions: d.submissions.filter((x) => x.id !== s.id) }));
    setMessage(`“${s.title}” deleted.`);
  }

  const editing = mode.kind === "edit" ? submissions.find((s) => s.id === mode.id) : undefined;

  return (
    <RoleGate role="org-rep">
      <div className="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
        <OrgAvatar org={organization} size="lg" />
        <div className="flex-1">
          <p className="text-muted text-xs font-semibold uppercase">You are managing (demo)</p>
          <h2 className="text-xl font-bold">{organization.name}</h2>
          <Link
            href={`/organizations/${organization.id}`}
            className="text-brand-700 text-sm font-semibold hover:underline"
          >
            View public profile
          </Link>
        </div>
        {mode.kind === "idle" && (
          <Button
            onClick={() => {
              setMode({ kind: "create" });
              setMessage("");
            }}
          >
            <CalendarPlus className="size-4" aria-hidden /> Create event
          </Button>
        )}
      </div>

      <p role="status" className="text-sm font-semibold text-emerald-800">
        {message}
      </p>

      {mode.kind !== "idle" && (
        <section className="card p-6" aria-labelledby="form-heading">
          <h2 id="form-heading" className="mb-5 text-lg font-bold">
            {mode.kind === "create" ? "Create a new event" : `Edit “${editing?.title}”`}
          </h2>
          <EventForm
            key={mode.kind === "edit" ? mode.id : "new"}
            initial={editing ? toFormValues(editing) : undefined}
            submitLabel={mode.kind === "create" ? "Submit for review" : "Save changes"}
            onSubmit={(result) => save(result, editing)}
            onCancel={() => setMode({ kind: "idle" })}
          />
        </section>
      )}

      <section aria-labelledby="subs-heading">
        <SectionHeading
          id="subs-heading"
          title="Your submissions"
          description="Events you created in this browser and their review status."
        />
        {submissions.length === 0 ? (
          <EmptyState
            title="No submissions yet"
            description="Create an event to see how the review workflow works."
          />
        ) : (
          <ul className="card divide-line divide-y">
            {submissions.map((s) => (
              <li
                key={s.id}
                className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <p className="font-semibold">{s.title}</p>
                  <p className="text-muted mt-0.5 flex flex-wrap items-center gap-2 text-sm">
                    {formatEventRange(s.start, s.end)}
                    <Badge tone={STATUS_TONE[s.status]}>{STATUS_LABEL[s.status]}</Badge>
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                      setMode({ kind: "edit", id: s.id });
                      setMessage("");
                    }}
                    aria-label={`Edit ${s.title}`}
                  >
                    <Pencil className="size-4" aria-hidden /> Edit
                  </Button>
                  <Button
                    variant="danger"
                    size="sm"
                    onClick={() => remove(s)}
                    aria-label={`Delete ${s.title}`}
                  >
                    <Trash2 className="size-4" aria-hidden /> Delete
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section aria-labelledby="published-heading">
        <SectionHeading
          id="published-heading"
          title="Published events (sample data)"
          description="Read-only: these come from the sample catalogue."
        />
        <ul className="card divide-line divide-y">
          {publishedEvents.map((e) => (
            <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 p-4">
              <div>
                <Link href={`/events/${e.id}`} className="hover:text-brand-700 font-semibold">
                  {e.title}
                </Link>
                <p className="text-muted text-sm">{formatEventRange(e.start, e.end)}</p>
              </div>
              <Badge tone="gold">{EVENT_CATEGORY_LABELS[e.category]}</Badge>
            </li>
          ))}
        </ul>
      </section>
    </RoleGate>
  );
}
