/**
 * Small presentational building blocks used across the app.
 */
import type { ReactNode } from "react";
import { Info, TriangleAlert } from "lucide-react";
import { cn } from "@/lib/cn";

type Tone = "neutral" | "brand" | "gold" | "success" | "warning" | "danger";

const TONES: Record<Tone, string> = {
  neutral: "bg-slate-100 text-slate-700",
  brand: "bg-brand-50 text-brand-700",
  gold: "bg-gold-100 text-gold-800",
  success: "bg-emerald-50 text-emerald-800",
  warning: "bg-amber-50 text-amber-900",
  danger: "bg-red-50 text-red-800",
};

export function Badge({
  tone = "neutral",
  children,
  className,
}: {
  tone?: Tone;
  children: ReactNode;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap",
        TONES[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}

export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
}: {
  eyebrow?: string;
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <div className="flex flex-col gap-4 pt-8 pb-6 sm:flex-row sm:items-end sm:justify-between sm:pt-10">
      <div className="max-w-3xl">
        {eyebrow && (
          <p className="text-brand-600 mb-2 text-sm font-semibold tracking-wide uppercase">
            {eyebrow}
          </p>
        )}
        <h1 className="text-ink text-3xl font-bold tracking-tight sm:text-4xl">{title}</h1>
        {description && <div className="text-muted mt-3 text-base">{description}</div>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  );
}

export function SectionHeading({
  title,
  description,
  action,
  id,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
  id?: string;
}) {
  return (
    <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
      <div>
        <h2 id={id} className="text-ink text-xl font-bold tracking-tight">
          {title}
        </h2>
        {description && <p className="text-muted mt-1 text-sm">{description}</p>}
      </div>
      {action}
    </div>
  );
}

export function EmptyState({
  icon,
  title,
  description,
  action,
}: {
  icon?: ReactNode;
  title: string;
  description?: ReactNode;
  action?: ReactNode;
}) {
  return (
    <div className="card flex flex-col items-center px-6 py-12 text-center">
      {icon && (
        <div className="bg-brand-50 text-brand-600 mb-4 flex size-12 items-center justify-center rounded-full">
          {icon}
        </div>
      )}
      <h3 className="text-ink text-lg font-semibold">{title}</h3>
      {description && <div className="text-muted mt-2 max-w-md text-sm">{description}</div>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}

export function Notice({
  tone = "info",
  title,
  children,
  className,
}: {
  tone?: "info" | "warning";
  title?: string;
  children: ReactNode;
  className?: string;
}) {
  const Icon = tone === "warning" ? TriangleAlert : Info;
  return (
    <div
      className={cn(
        "flex gap-3 rounded-xl border p-4 text-sm",
        tone === "warning"
          ? "border-amber-200 bg-amber-50 text-amber-950"
          : "border-brand-100 bg-brand-50 text-brand-900",
        className,
      )}
    >
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
      <div>
        {title && <p className="font-semibold">{title}</p>}
        <div className={title ? "mt-1" : undefined}>{children}</div>
      </div>
    </div>
  );
}

/** One term/value pair inside a <dl>, with an icon. Keeps the dl > div > dt + dd structure valid. */
export function DetailItem({
  icon,
  term,
  children,
}: {
  icon: ReactNode;
  term: string;
  children: ReactNode;
}) {
  return (
    <div>
      <dt className="text-ink flex items-center gap-2 font-semibold">
        <span className="text-brand-600" aria-hidden>
          {icon}
        </span>
        {term}
      </dt>
      <dd className="text-muted mt-0.5 pl-7">{children}</dd>
    </div>
  );
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn("animate-pulse rounded-xl bg-slate-200/70", className)} aria-hidden />;
}

export function SkeletonList({ count = 3, label = "Loading" }: { count?: number; label?: string }) {
  return (
    <div role="status" aria-label={label} className="grid gap-4">
      {Array.from({ length: count }, (_, i) => (
        <Skeleton key={i} className="h-28" />
      ))}
      <span className="sr-only">{label}…</span>
    </div>
  );
}

export function SampleDataBadge() {
  return (
    <Badge tone="gold" className="uppercase">
      Sample data
    </Badge>
  );
}
