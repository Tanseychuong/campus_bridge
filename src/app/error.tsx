"use client";

import { useEffect } from "react";
import { TriangleAlert } from "lucide-react";
import { Button, LinkButton } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/primitives";

/** Error boundary for every route: a friendly message instead of a blank page. */
export default function ErrorPage({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <div className="container-page py-16">
      <EmptyState
        icon={<TriangleAlert className="size-6" aria-hidden />}
        title="Something went wrong"
        description={
          <>
            An unexpected error stopped this page from loading. You can try again — your saved items
            are safe.
            {error.digest && <span className="mt-2 block text-xs">Reference: {error.digest}</span>}
          </>
        }
        action={
          <div className="flex flex-wrap justify-center gap-2">
            <Button onClick={reset}>Try again</Button>
            <LinkButton href="/" variant="secondary">
              Go home
            </LinkButton>
          </div>
        }
      />
    </div>
  );
}
