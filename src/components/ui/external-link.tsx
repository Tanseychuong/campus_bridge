import type { ReactNode } from "react";
import { ExternalLink as ExternalIcon } from "lucide-react";
import { hostnameOf, isSafeExternalUrl } from "@/lib/safe-url";
import { buttonClasses } from "./button";

/**
 * Safe external link: https only, opens in a new tab without giving the new
 * page access to this one (rel="noopener noreferrer"), and tells the user
 * where they are going.
 */
export function ExternalLink({ href, children }: { href: string; children: ReactNode }) {
  if (!isSafeExternalUrl(href)) {
    return <span className="text-muted text-sm">Link unavailable (unsafe or invalid URL)</span>;
  }
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className={buttonClasses("primary", "md")}
    >
      {children}
      <ExternalIcon className="size-4" aria-hidden />
      <span className="sr-only"> (opens {hostnameOf(href)} in a new tab)</span>
    </a>
  );
}
