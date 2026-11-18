/**
 * A tiny, validated localStorage store that React components subscribe to
 * with `useSyncExternalStore`.
 *
 * Why not just useState + useEffect?
 *  - Every component reading the store sees the same value instantly.
 *  - Changes in another browser tab are picked up via the "storage" event.
 *  - React knows the server has no localStorage (getServerSnapshot), so
 *    hydration never mismatches.
 *
 * Reliability: stored data is untrusted (users can edit it in DevTools, and
 * old versions may linger), so it is validated with Zod on every read. If it
 * is corrupt, we fall back to defaults instead of crashing.
 *
 * Privacy: only non-sensitive demo preferences are stored. No credentials,
 * no personal information. Data stays in this browser and does not sync
 * across devices.
 */
import type { z } from "zod";

export interface LocalStore<T> {
  getSnapshot: () => T;
  getServerSnapshot: () => T;
  subscribe: (listener: () => void) => () => void;
  update: (updater: (current: T) => T) => void;
  reset: () => void;
}

export function createLocalStore<T>(key: string, schema: z.ZodType<T>, defaults: T): LocalStore<T> {
  let cache: T | undefined; // same object reference between reads (required by useSyncExternalStore)
  const listeners = new Set<() => void>();

  function read(): T {
    try {
      const raw = window.localStorage.getItem(key);
      if (raw === null) return defaults;
      const parsed = schema.safeParse(JSON.parse(raw));
      return parsed.success ? parsed.data : defaults;
    } catch {
      return defaults; // JSON syntax error, or storage blocked (private mode)
    }
  }

  function write(value: T) {
    try {
      window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
      // Storage full or blocked: keep the in-memory value so the UI still works.
    }
  }

  function emit() {
    for (const listener of listeners) listener();
  }

  function onStorage(event: StorageEvent) {
    if (event.key === key || event.key === null) {
      cache = undefined;
      emit();
    }
  }

  return {
    getSnapshot() {
      if (cache === undefined) cache = read();
      return cache;
    },
    getServerSnapshot() {
      return defaults;
    },
    subscribe(listener) {
      listeners.add(listener);
      if (listeners.size === 1) window.addEventListener("storage", onStorage);
      return () => {
        listeners.delete(listener);
        if (listeners.size === 0) window.removeEventListener("storage", onStorage);
      };
    },
    update(updater) {
      const current = cache ?? read();
      const next = schema.parse(updater(current)); // never persist invalid data
      cache = next;
      write(next);
      emit();
    },
    reset() {
      cache = defaults;
      try {
        window.localStorage.removeItem(key);
      } catch {
        /* ignore */
      }
      emit();
    },
  };
}
