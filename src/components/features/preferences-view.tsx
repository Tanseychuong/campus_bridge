"use client";

import Link from "next/link";
import { useState } from "react";
import { RotateCcw } from "lucide-react";
import type { Catalog } from "@/schemas/domain";
import { recommend } from "@/lib/recommend/score";
import { useUserData } from "@/state/hooks";
import { InterestPicker } from "./interest-picker";
import { FollowButton } from "./action-buttons";
import { Button } from "@/components/ui/button";
import { EmptyState, Notice, SkeletonList } from "@/components/ui/primitives";
import { OrgAvatar } from "@/components/ui/org-avatar";

export function PreferencesView({ catalog }: { catalog: Catalog }) {
  const { data, ready, setInterests, reset } = useUserData();
  const [now] = useState(() => new Date());
  const [confirmReset, setConfirmReset] = useState(false);

  if (!ready) return <SkeletonList count={2} label="Loading preferences" />;

  // Live preview: the same engine as the dashboard, so students can see the effect immediately.
  const preview = recommend({ ...catalog, profile: data, now, limit: 3 });
  const followed = catalog.organizations.filter((o) => data.followedOrganizationIds.includes(o.id));

  return (
    <div className="grid gap-8 lg:grid-cols-[1fr_22rem]">
      <div className="space-y-8">
        <section className="card p-6">
          <InterestPicker
            value={data.interests}
            onChange={setInterests}
            description="Recommendations earn points for every interest they match. Changes are saved automatically."
          />
          <p className="text-muted mt-4 text-sm" aria-live="polite">
            {data.interests.length} interest{data.interests.length === 1 ? "" : "s"} selected.
          </p>
        </section>

        <section className="card p-6" aria-labelledby="followed-heading">
          <h2 id="followed-heading" className="text-base font-bold">
            Organizations you follow
          </h2>
          <p className="text-muted mt-1 text-sm">
            Items from followed organizations get a recommendation boost.
          </p>
          {followed.length === 0 ? (
            <div className="mt-4">
              <EmptyState
                title="Not following anyone yet"
                action={
                  <Link href="/organizations" className="text-brand-700 font-semibold underline">
                    Browse organizations
                  </Link>
                }
              />
            </div>
          ) : (
            <ul className="divide-line mt-4 divide-y">
              {followed.map((o) => (
                <li key={o.id} className="flex items-center justify-between gap-3 py-3">
                  <Link
                    href={`/organizations/${o.id}`}
                    className="hover:text-brand-700 flex items-center gap-3 font-semibold"
                  >
                    <OrgAvatar org={o} size="sm" />
                    {o.name}
                  </Link>
                  <FollowButton id={o.id} name={o.name} />
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="card p-6" aria-labelledby="data-heading" id="data">
          <h2 id="data-heading" className="text-base font-bold">
            Your data
          </h2>
          <Notice className="mt-3">
            Preferences, saved items and follows are stored only in <strong>this browser</strong>{" "}
            (localStorage). They are not sent to any server and do not sync to other devices. No
            personal information is collected.
          </Notice>
          <div className="mt-4">
            {confirmReset ? (
              <div
                className="flex flex-wrap items-center gap-2"
                role="group"
                aria-label="Confirm reset"
              >
                <span className="text-sm font-semibold">
                  Delete all your CampusBridge data in this browser?
                </span>
                <Button
                  variant="danger"
                  size="sm"
                  onClick={() => {
                    reset();
                    setConfirmReset(false);
                  }}
                >
                  Yes, reset
                </Button>
                <Button variant="secondary" size="sm" onClick={() => setConfirmReset(false)}>
                  Cancel
                </Button>
              </div>
            ) : (
              <Button variant="danger" size="sm" onClick={() => setConfirmReset(true)}>
                <RotateCcw className="size-4" aria-hidden /> Reset my data
              </Button>
            )}
          </div>
        </section>
      </div>

      <aside className="h-fit lg:sticky lg:top-24">
        <section className="card p-5" aria-labelledby="preview-heading">
          <h2 id="preview-heading" className="text-base font-bold">
            Live preview
          </h2>
          <p className="text-muted mt-1 text-sm">Your top recommendations with these settings.</p>
          {preview.length === 0 ? (
            <p className="text-muted mt-4 text-sm">Select interests to see recommendations.</p>
          ) : (
            <ol className="mt-4 space-y-3" aria-live="polite">
              {preview.map((r, index) => (
                <li key={`${r.type}:${r.item.id}`} className="bg-brand-50/60 rounded-xl p-3">
                  <p className="text-brand-700 text-xs font-bold">
                    #{index + 1} · score {r.score}
                  </p>
                  <p className="mt-0.5 text-sm font-semibold">{r.item.title}</p>
                  <p className="text-muted mt-1 text-xs">{r.reasons[0]?.text}</p>
                </li>
              ))}
            </ol>
          )}
          <Link
            href="/dashboard"
            className="text-brand-700 mt-4 inline-block text-sm font-semibold hover:underline"
          >
            Open my dashboard →
          </Link>
        </section>
      </aside>
    </div>
  );
}
