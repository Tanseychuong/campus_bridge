"use client";

import { useCallback, useMemo, useSyncExternalStore } from "react";
import type { Interest } from "@/schemas/taxonomy";
import { toggleId, userStore, type DemoRole } from "./user-data";
import { demoStore } from "./demo-data";

const noopSubscribe = () => () => {};

/**
 * false during server rendering and hydration, true afterwards.
 * Used to show a skeleton instead of a misleading empty state before
 * localStorage has been read.
 */
export function useIsClient(): boolean {
  return useSyncExternalStore(
    noopSubscribe,
    () => true,
    () => false,
  );
}

export function useUserData() {
  const data = useSyncExternalStore(
    userStore.subscribe,
    userStore.getSnapshot,
    userStore.getServerSnapshot,
  );
  const ready = useIsClient();

  const toggleSavedOpportunity = useCallback(
    (id: string) =>
      userStore.update((d) => ({ ...d, savedOpportunityIds: toggleId(d.savedOpportunityIds, id) })),
    [],
  );
  const toggleSavedEvent = useCallback(
    (id: string) =>
      userStore.update((d) => ({ ...d, savedEventIds: toggleId(d.savedEventIds, id) })),
    [],
  );
  const toggleFollow = useCallback(
    (id: string) =>
      userStore.update((d) => ({
        ...d,
        followedOrganizationIds: toggleId(d.followedOrganizationIds, id),
      })),
    [],
  );
  const setInterests = useCallback(
    (interests: Interest[]) => userStore.update((d) => ({ ...d, interests })),
    [],
  );
  const setRole = useCallback((role: DemoRole) => userStore.update((d) => ({ ...d, role })), []);

  const actions = useMemo(
    () => ({
      toggleSavedOpportunity,
      toggleSavedEvent,
      toggleFollow,
      setInterests,
      setRole,
      reset: userStore.reset,
    }),
    [toggleSavedOpportunity, toggleSavedEvent, toggleFollow, setInterests, setRole],
  );

  return { data, ready, ...actions };
}

export function useDemoData() {
  const data = useSyncExternalStore(
    demoStore.subscribe,
    demoStore.getSnapshot,
    demoStore.getServerSnapshot,
  );
  return { data, update: demoStore.update, reset: demoStore.reset };
}
