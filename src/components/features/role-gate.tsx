"use client";

import type { ReactNode } from "react";
import { ShieldAlert } from "lucide-react";
import { useUserData } from "@/state/hooks";
import { DEMO_ROLE_LABELS, type DemoRole } from "@/state/user-data";
import { Button } from "@/components/ui/button";
import { EmptyState, Notice, SkeletonList } from "@/components/ui/primitives";

/**
 * Shows role-specific demo screens only when the matching DEMO role is
 * selected.
 *
 * SECURITY NOTE: this is a user-experience switch, NOT access control. Anyone
 * can pick any role in their own browser. Real authorization must be enforced
 * on a server once a backend exists.
 */
export function RoleGate({ role, children }: { role: DemoRole; children: ReactNode }) {
  const { data, ready, setRole } = useUserData();
  if (!ready) return <SkeletonList count={2} label="Loading" />;

  if (data.role !== role) {
    return (
      <EmptyState
        icon={<ShieldAlert className="size-6" aria-hidden />}
        title={`This area is for the “${DEMO_ROLE_LABELS[role]}” demo role`}
        description="CampusBridge does not have real accounts yet. To explore this workflow, switch to the demonstration role. This is not secure sign-in."
        action={
          <Button onClick={() => setRole(role)}>Switch to {DEMO_ROLE_LABELS[role]} (demo)</Button>
        }
      />
    );
  }

  return (
    <div className="space-y-6">
      <Notice tone="warning" title="Demonstration mode — not secure">
        You are viewing a role-specific workflow using a demo role chosen in this browser. Changes
        are stored only in this browser and are not visible to anyone else. A real deployment
        requires a backend with authenticated accounts and server-side authorization.
      </Notice>
      {children}
    </div>
  );
}
