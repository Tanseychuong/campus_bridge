"use client";

/**
 * Save / follow toggles. They are small "client islands": the surrounding
 * card is rendered on the server, only these buttons need JavaScript.
 */
import {
  Bookmark,
  BookmarkCheck,
  CalendarCheck,
  CalendarPlus,
  UserCheck,
  UserPlus,
} from "lucide-react";
import { buttonClasses } from "@/components/ui/button";
import { useUserData } from "@/state/hooks";

type Size = "sm" | "md";

export function SaveOpportunityButton({
  id,
  title,
  size = "sm",
}: {
  id: string;
  title: string;
  size?: Size;
}) {
  const { data, ready, toggleSavedOpportunity } = useUserData();
  const saved = data.savedOpportunityIds.includes(id);
  const Icon = saved ? BookmarkCheck : Bookmark;
  return (
    <button
      type="button"
      disabled={!ready}
      aria-pressed={saved}
      aria-label={`Save ${title}`}
      onClick={() => toggleSavedOpportunity(id)}
      className={buttonClasses(saved ? "primary" : "secondary", size, "relative z-10")}
    >
      <Icon className="size-4" aria-hidden />
      <span aria-hidden>{saved ? "Saved" : "Save"}</span>
    </button>
  );
}

export function SaveEventButton({
  id,
  title,
  size = "sm",
}: {
  id: string;
  title: string;
  size?: Size;
}) {
  const { data, ready, toggleSavedEvent } = useUserData();
  const saved = data.savedEventIds.includes(id);
  const Icon = saved ? CalendarCheck : CalendarPlus;
  return (
    <button
      type="button"
      disabled={!ready}
      aria-pressed={saved}
      aria-label={`I'm interested in ${title}`}
      title="Adds this event to your CampusBridge calendar"
      onClick={() => toggleSavedEvent(id)}
      className={buttonClasses(saved ? "primary" : "secondary", size, "relative z-10")}
    >
      <Icon className="size-4" aria-hidden />
      <span aria-hidden>{saved ? "Interested" : "I'm interested"}</span>
    </button>
  );
}

export function FollowButton({ id, name, size = "sm" }: { id: string; name: string; size?: Size }) {
  const { data, ready, toggleFollow } = useUserData();
  const following = data.followedOrganizationIds.includes(id);
  const Icon = following ? UserCheck : UserPlus;
  return (
    <button
      type="button"
      disabled={!ready}
      aria-pressed={following}
      aria-label={`Follow ${name}`}
      onClick={() => toggleFollow(id)}
      className={buttonClasses(following ? "primary" : "secondary", size, "relative z-10")}
    >
      <Icon className="size-4" aria-hidden />
      <span aria-hidden>{following ? "Following" : "Follow"}</span>
    </button>
  );
}
