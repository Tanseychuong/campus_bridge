import { Compass } from "lucide-react";
import { LinkButton } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/primitives";

export default function NotFound() {
  return (
    <div className="container-page py-16">
      <EmptyState
        icon={<Compass className="size-6" aria-hidden />}
        title="We couldn’t find that page"
        description="The link may be broken, or the item may have been removed. Try searching instead."
        action={
          <div className="flex flex-wrap justify-center gap-2">
            <LinkButton href="/explore">Search CampusBridge</LinkButton>
            <LinkButton href="/" variant="secondary">
              Go home
            </LinkButton>
          </div>
        }
      />
    </div>
  );
}
