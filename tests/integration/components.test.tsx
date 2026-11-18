/**
 * Integration tests: real components + real store + real business logic,
 * rendered in jsdom. Only Next.js navigation is mocked.
 */
import { describe, expect, it, vi } from "vitest";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { DashboardView } from "@/components/features/dashboard-view";
import { SavedView } from "@/components/features/saved-view";
import { SaveOpportunityButton } from "@/components/features/action-buttons";
import { EventForm } from "@/components/features/event-form";
import { PreferencesView } from "@/components/features/preferences-view";
import { userStore } from "@/state/user-data";
import { makeEvent, makeOpportunity, makeOrganization } from "../factories";

vi.mock("next/navigation", () => ({
  usePathname: () => "/",
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));

// Dates relative to the real clock, because the components read `new Date()`.
function relative(days: number, hhmm = "23:59") {
  const d = new Date();
  d.setUTCDate(d.getUTCDate() + days);
  const [h, m] = hhmm.split(":").map(Number);
  d.setUTCHours(h, m, 0, 0);
  return d.toISOString();
}

const catalog = {
  organizations: [makeOrganization()],
  opportunities: [
    makeOpportunity({
      id: "cs-intern",
      title: "CS Internship",
      interests: ["computer-science"],
      deadline: relative(6),
    }),
    makeOpportunity({
      id: "arts-grant",
      title: "Arts Grant",
      category: "scholarship",
      interests: ["arts"],
      deadline: relative(20),
    }),
  ],
  events: [
    makeEvent({
      id: "talk-a",
      title: "Talk A",
      start: relative(3, "18:00"),
      end: relative(3, "19:30"),
    }),
    makeEvent({
      id: "talk-b",
      title: "Talk B",
      start: relative(3, "19:00"),
      end: relative(3, "20:00"),
    }),
  ],
};

function resetStore() {
  userStore.reset();
}

describe("Dashboard", () => {
  it("updating interests changes the recommendations and their explanations", async () => {
    resetStore();
    const user = userEvent.setup();
    render(<DashboardView catalog={catalog} />);

    expect(await screen.findByText("No interests selected yet")).toBeInTheDocument();
    await user.click(screen.getByRole("checkbox", { name: "Arts & Culture" }));

    const cards = await screen.findAllByTestId("recommendation");
    expect(cards).toHaveLength(1);
    expect(within(cards[0]).getByText("Arts Grant")).toBeInTheDocument();
    expect(
      within(cards[0]).getByText("Matches your interests: Arts & Culture"),
    ).toBeInTheDocument();
  });

  it("shows clashes between saved events", async () => {
    resetStore();
    userStore.update((d) => ({ ...d, savedEventIds: ["talk-a", "talk-b"] }));
    render(<DashboardView catalog={catalog} />);
    expect(await screen.findAllByText("Clashes with another event in your calendar")).toHaveLength(
      2,
    );
  });
});

describe("Saving", () => {
  it("saving an opportunity updates the saved-items view", async () => {
    resetStore();
    const user = userEvent.setup();
    render(
      <>
        <SaveOpportunityButton id="cs-intern" title="CS Internship" />
        <SavedView catalog={catalog} />
      </>,
    );
    expect(await screen.findByText("No saved opportunities")).toBeInTheDocument();
    const button = screen.getByRole("button", { name: "Save CS Internship" });
    await user.click(button);
    expect(button).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByRole("link", { name: "CS Internship" })).toBeInTheDocument();
    expect(screen.getByText(/Due in the next 7 days/)).toBeInTheDocument();
  });
});

describe("Preferences", () => {
  it("shows a live preview that follows the selected interests", async () => {
    resetStore();
    const user = userEvent.setup();
    render(<PreferencesView catalog={catalog} />);
    expect(await screen.findByText("Select interests to see recommendations.")).toBeInTheDocument();
    await user.click(screen.getByRole("checkbox", { name: "Computer Science" }));
    expect(screen.getByText("CS Internship")).toBeInTheDocument();
  });
});

describe("Event form", () => {
  it("shows field errors, focuses the first one and does not submit", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<EventForm submitLabel="Submit" onSubmit={onSubmit} />);
    await user.click(screen.getByRole("button", { name: "Submit" }));
    expect(screen.getByRole("alert")).toHaveTextContent(/Please fix \d+ fields/);
    expect(screen.getByLabelText(/^Title/)).toHaveFocus();
    expect(screen.getByLabelText(/^Title/)).toHaveAttribute("aria-invalid", "true");
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it("submits valid data with UTC times", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<EventForm submitLabel="Submit" onSubmit={onSubmit} />);
    const date = relative(4).slice(0, 10);
    await user.type(screen.getByLabelText(/^Title/), "Robotics Basics");
    await user.type(screen.getByLabelText(/^Venue/), "Lab 3");
    await user.type(screen.getByLabelText(/^Short summary/), "An introduction to robots.");
    await user.type(
      screen.getByLabelText(/^Description/),
      "Learn about motors, sensors and control.",
    );
    await user.type(screen.getByLabelText(/^Date/), date);
    await user.click(screen.getByRole("checkbox", { name: "Engineering" }));
    await user.click(screen.getByRole("button", { name: "Submit" }));
    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({ start: `${date}T18:00:00.000Z`, end: `${date}T19:30:00.000Z` }),
    );
  });
});
