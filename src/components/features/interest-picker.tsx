"use client";

import { Check } from "lucide-react";
import { INTEREST_LABELS, INTERESTS, type Interest } from "@/schemas/taxonomy";
import { cn } from "@/lib/cn";

/**
 * Accessible multi-select of interests, built from real checkboxes so it
 * works with the keyboard and screen readers.
 */
export function InterestPicker({
  value,
  onChange,
  legend = "Your interests",
  description,
}: {
  value: Interest[];
  onChange: (next: Interest[]) => void;
  legend?: string;
  description?: string;
}) {
  function toggle(interest: Interest) {
    onChange(value.includes(interest) ? value.filter((i) => i !== interest) : [...value, interest]);
  }
  return (
    <fieldset>
      <legend className="text-base font-bold">{legend}</legend>
      {description && <p className="text-muted mt-1 text-sm">{description}</p>}
      <div className="mt-3 flex flex-wrap gap-2">
        {INTERESTS.map((interest) => {
          const checked = value.includes(interest);
          return (
            <label
              key={interest}
              className={cn(
                "has-focus-visible:outline-brand-500 inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition-colors has-focus-visible:outline-3 has-focus-visible:outline-offset-2",
                checked
                  ? "border-brand-600 bg-brand-600 text-white"
                  : "border-line hover:border-brand-300 bg-white text-slate-700",
              )}
            >
              <input
                type="checkbox"
                className="sr-only"
                checked={checked}
                onChange={() => toggle(interest)}
              />
              {checked && <Check className="size-4" aria-hidden />}
              {INTEREST_LABELS[interest]}
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
