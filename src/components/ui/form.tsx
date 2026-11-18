/**
 * Accessible form controls. Each field links its label, hint and error to the
 * input with ids, so screen readers announce them together.
 */
import type { ComponentProps, ReactNode } from "react";
import { cn } from "@/lib/cn";

const CONTROL =
  "w-full rounded-xl border bg-white px-3 text-sm text-ink shadow-sm transition-colors placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus-visible:outline-none focus:ring-3 focus:ring-brand-200";

export function controlClasses(invalid?: boolean, className?: string) {
  return cn(CONTROL, invalid ? "border-red-400" : "border-line", className);
}

export function Field({
  id,
  label,
  hint,
  error,
  required,
  children,
}: {
  id: string;
  label: string;
  hint?: string;
  error?: string;
  required?: boolean;
  children: ReactNode;
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-ink text-sm font-semibold">
        {label}
        {required && (
          <span className="text-red-700" aria-hidden>
            {" "}
            *
          </span>
        )}
      </label>
      {children}
      {hint && !error && (
        <p id={`${id}-hint`} className="text-muted text-xs">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className="text-xs font-medium text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}

/** aria props that connect an input to its Field hint/error. */
export function describedBy(id: string, error?: string, hint?: string) {
  return {
    "aria-invalid": error ? true : undefined,
    "aria-describedby": error ? `${id}-error` : hint ? `${id}-hint` : undefined,
  } as const;
}

export function Input({
  className,
  invalid,
  ...props
}: ComponentProps<"input"> & { invalid?: boolean }) {
  return <input className={controlClasses(invalid, cn("h-11", className))} {...props} />;
}

export function Textarea({
  className,
  invalid,
  ...props
}: ComponentProps<"textarea"> & { invalid?: boolean }) {
  return (
    <textarea className={controlClasses(invalid, cn("min-h-28 py-2.5", className))} {...props} />
  );
}

export function Select({
  className,
  invalid,
  ...props
}: ComponentProps<"select"> & { invalid?: boolean }) {
  return <select className={controlClasses(invalid, cn("h-11 pr-8", className))} {...props} />;
}
