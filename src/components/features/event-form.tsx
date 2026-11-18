"use client";

/**
 * Create / edit event form (organization rep demo).
 * Validation runs with the same Zod schema that is unit-tested. Errors are
 * shown next to each field, announced to screen readers, and focus moves to
 * the first invalid field.
 */
import { useRef, useState, type FormEvent } from "react";
import {
  EVENT_CATEGORIES,
  EVENT_CATEGORY_LABELS,
  INTEREST_LABELS,
  INTERESTS,
  type Interest,
} from "@/schemas/taxonomy";
import {
  validateEventForm,
  type EventFormErrors,
  type EventFormValues,
} from "@/schemas/event-form";
import { Button } from "@/components/ui/button";
import { describedBy, Field, Input, Select, Textarea } from "@/components/ui/form";

export const EMPTY_EVENT_FORM: EventFormValues = {
  title: "",
  category: "tech",
  summary: "",
  description: "",
  venue: "",
  date: "",
  startTime: "18:00",
  endTime: "19:30",
  capacity: "",
  registration: "open",
  interests: [],
};

const FIELD_ORDER: (keyof EventFormValues)[] = [
  "title",
  "category",
  "summary",
  "description",
  "venue",
  "date",
  "startTime",
  "endTime",
  "capacity",
  "registration",
  "interests",
];

export interface EventFormSubmit {
  values: EventFormValues;
  start: string;
  end: string;
}

export function EventForm({
  initial = EMPTY_EVENT_FORM,
  submitLabel,
  onSubmit,
  onCancel,
}: {
  initial?: EventFormValues;
  submitLabel: string;
  onSubmit: (result: EventFormSubmit) => void;
  onCancel?: () => void;
}) {
  const [values, setValues] = useState<EventFormValues>(initial);
  const [errors, setErrors] = useState<EventFormErrors>({});
  const formRef = useRef<HTMLFormElement>(null);

  function set<K extends keyof EventFormValues>(key: K, value: EventFormValues[K]) {
    setValues((v) => ({ ...v, [key]: value }));
    if (errors[key]) setErrors((e) => ({ ...e, [key]: undefined }));
  }

  function toggleInterest(i: Interest) {
    const current = values.interests as Interest[];
    set("interests", current.includes(i) ? current.filter((x) => x !== i) : [...current, i]);
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    const result = validateEventForm(values, new Date());
    if (!result.ok) {
      setErrors(result.errors);
      const first = FIELD_ORDER.find((f) => result.errors[f]);
      formRef.current?.querySelector<HTMLElement>(`[data-field="${first}"]`)?.focus();
      return;
    }
    setErrors({});
    onSubmit({
      values: { ...values, ...result.data, capacity: result.data.capacity },
      start: result.start,
      end: result.end,
    });
  }

  const errorCount = Object.values(errors).filter(Boolean).length;

  return (
    <form
      ref={formRef}
      onSubmit={handleSubmit}
      noValidate
      className="space-y-5"
      aria-label="Event details"
    >
      {errorCount > 0 && (
        <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-800">
          Please fix {errorCount} field{errorCount === 1 ? "" : "s"} below.
        </p>
      )}

      <Field id="ev-title" label="Title" required error={errors.title}>
        <Input
          id="ev-title"
          data-field="title"
          value={values.title}
          maxLength={120}
          onChange={(e) => set("title", e.target.value)}
          invalid={!!errors.title}
          {...describedBy("ev-title", errors.title)}
        />
      </Field>

      <div className="grid gap-5 sm:grid-cols-2">
        <Field id="ev-category" label="Category" required error={errors.category}>
          <Select
            id="ev-category"
            data-field="category"
            value={values.category}
            onChange={(e) => set("category", e.target.value as EventFormValues["category"])}
            invalid={!!errors.category}
          >
            {EVENT_CATEGORIES.map((c) => (
              <option key={c} value={c}>
                {EVENT_CATEGORY_LABELS[c]}
              </option>
            ))}
          </Select>
        </Field>
        <Field id="ev-venue" label="Venue" required error={errors.venue}>
          <Input
            id="ev-venue"
            data-field="venue"
            value={values.venue}
            maxLength={120}
            onChange={(e) => set("venue", e.target.value)}
            invalid={!!errors.venue}
            {...describedBy("ev-venue", errors.venue)}
          />
        </Field>
      </div>

      <Field
        id="ev-summary"
        label="Short summary"
        hint="One sentence shown on event cards (10–220 characters)."
        required
        error={errors.summary}
      >
        <Input
          id="ev-summary"
          data-field="summary"
          value={values.summary}
          maxLength={220}
          onChange={(e) => set("summary", e.target.value)}
          invalid={!!errors.summary}
          {...describedBy("ev-summary", errors.summary, "hint")}
        />
      </Field>

      <Field id="ev-description" label="Description" required error={errors.description}>
        <Textarea
          id="ev-description"
          data-field="description"
          value={values.description}
          maxLength={3000}
          onChange={(e) => set("description", e.target.value)}
          invalid={!!errors.description}
          {...describedBy("ev-description", errors.description)}
        />
      </Field>

      <div className="grid gap-5 sm:grid-cols-3">
        <Field id="ev-date" label="Date" required error={errors.date}>
          <Input
            id="ev-date"
            data-field="date"
            type="date"
            value={values.date}
            onChange={(e) => set("date", e.target.value)}
            invalid={!!errors.date}
            {...describedBy("ev-date", errors.date)}
          />
        </Field>
        <Field id="ev-start" label="Start time" required error={errors.startTime}>
          <Input
            id="ev-start"
            data-field="startTime"
            type="time"
            value={values.startTime}
            onChange={(e) => set("startTime", e.target.value)}
            invalid={!!errors.startTime}
            {...describedBy("ev-start", errors.startTime)}
          />
        </Field>
        <Field id="ev-end" label="End time" required error={errors.endTime}>
          <Input
            id="ev-end"
            data-field="endTime"
            type="time"
            value={values.endTime}
            onChange={(e) => set("endTime", e.target.value)}
            invalid={!!errors.endTime}
            {...describedBy("ev-end", errors.endTime)}
          />
        </Field>
      </div>

      <div className="grid gap-5 sm:grid-cols-2">
        <Field
          id="ev-capacity"
          label="Capacity"
          hint="Leave empty for no limit."
          error={errors.capacity}
        >
          <Input
            id="ev-capacity"
            data-field="capacity"
            inputMode="numeric"
            value={values.capacity}
            onChange={(e) => set("capacity", e.target.value)}
            invalid={!!errors.capacity}
            {...describedBy("ev-capacity", errors.capacity, "hint")}
          />
        </Field>
        <Field id="ev-registration" label="Registration" required>
          <Select
            id="ev-registration"
            data-field="registration"
            value={values.registration}
            onChange={(e) => set("registration", e.target.value as "open" | "rsvp")}
          >
            <option value="open">Open entry</option>
            <option value="rsvp">RSVP required</option>
          </Select>
        </Field>
      </div>

      <fieldset aria-describedby={errors.interests ? "ev-interests-error" : undefined}>
        <legend className="text-sm font-semibold">
          Interest tags{" "}
          <span className="text-red-700" aria-hidden>
            *
          </span>
        </legend>
        <div className="mt-2 flex flex-wrap gap-2">
          {INTERESTS.map((i, index) => (
            <label
              key={i}
              className="border-line has-checked:border-brand-600 has-checked:bg-brand-50 flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
            >
              <input
                type="checkbox"
                data-field={index === 0 ? "interests" : undefined}
                checked={(values.interests as Interest[]).includes(i)}
                onChange={() => toggleInterest(i)}
                className="accent-brand-600 size-4"
              />
              {INTEREST_LABELS[i]}
            </label>
          ))}
        </div>
        {errors.interests && (
          <p id="ev-interests-error" className="mt-1.5 text-xs font-medium text-red-700">
            {errors.interests}
          </p>
        )}
      </fieldset>

      <div className="flex flex-wrap gap-2">
        <Button type="submit">{submitLabel}</Button>
        {onCancel && (
          <Button variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
        )}
      </div>
    </form>
  );
}
