import { Search } from "lucide-react";
import { buttonClasses } from "@/components/ui/button";
import { cn } from "@/lib/cn";

/**
 * A plain HTML GET form: it works even before JavaScript loads
 * (progressive enhancement) and produces a shareable /explore?q=… URL.
 */
export function SearchBox({
  defaultValue = "",
  type,
  size = "md",
  autoFocus,
}: {
  defaultValue?: string;
  type?: string;
  size?: "md" | "lg";
  autoFocus?: boolean;
}) {
  return (
    <form action="/explore" method="get" role="search" className="flex w-full gap-2">
      <label htmlFor="site-search" className="sr-only">
        Search opportunities, events and organizations
      </label>
      <div className="relative flex-1">
        <Search
          className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400"
          aria-hidden
        />
        <input
          id="site-search"
          name="q"
          type="search"
          defaultValue={defaultValue}
          maxLength={100}
          autoFocus={autoFocus}
          placeholder="Try “internship”, “hackathon” or “climate”"
          className={cn(
            "border-line text-ink focus:border-brand-500 focus:ring-brand-200 w-full rounded-xl border bg-white pr-3 pl-11 shadow-sm placeholder:text-slate-400 focus:ring-3 focus:outline-none",
            size === "lg" ? "h-14 text-base" : "h-11 text-sm",
          )}
        />
      </div>
      {type && type !== "all" && <input type="hidden" name="type" value={type} />}
      <button
        type="submit"
        className={buttonClasses(
          "primary",
          size === "lg" ? "lg" : "md",
          size === "lg" ? "h-14" : undefined,
        )}
      >
        Search
      </button>
    </form>
  );
}
