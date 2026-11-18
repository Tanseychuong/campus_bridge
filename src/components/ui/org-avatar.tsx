import { cn } from "@/lib/cn";
import type { Organization } from "@/schemas/domain";

/**
 * Organizations are shown with a coloured monogram instead of a logo, so the
 * app never displays a real club's logo without permission.
 */
export function OrgAvatar({
  org,
  size = "md",
}: {
  org: Pick<Organization, "shortName" | "color">;
  size?: "sm" | "md" | "lg";
}) {
  const sizes = { sm: "size-9 text-[0.65rem]", md: "size-12 text-xs", lg: "size-16 text-sm" };
  return (
    <span
      aria-hidden
      className={cn(
        "inline-flex shrink-0 items-center justify-center rounded-xl font-bold tracking-tight text-white",
        sizes[size],
      )}
      style={{ backgroundColor: org.color }}
    >
      {org.shortName}
    </span>
  );
}
