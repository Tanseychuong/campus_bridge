import { expect, test, type Page } from "@playwright/test";

/** On phones the nav lives in a menu; open it first when needed. */
async function navigate(page: Page, name: string) {
  const menu = page.getByRole("button", { name: "Open menu" });
  if (await menu.isVisible()) await menu.click();
  await page.getByRole("link", { name, exact: true }).first().click();
}

test("home → search → opportunity details", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByRole("heading", { level: 1 })).toContainText("Every campus opportunity");
  await page.getByRole("searchbox").first().fill("internship");
  await page.getByRole("button", { name: "Search" }).first().click();
  await expect(page).toHaveURL(/\/explore\?q=internship/);
  await expect(page.getByRole("heading", { name: /results? for/ })).toBeVisible();
  await page.getByRole("link", { name: "Software Engineering Internship — Kente Digital" }).click();
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(
    "Software Engineering Internship — Kente Digital",
  );
  await expect(page.getByRole("heading", { name: "Eligibility" })).toBeVisible();
  const apply = page.getByRole("link", { name: /Apply on official site/ });
  await expect(apply).toHaveAttribute("rel", "noopener noreferrer");
});

test("filters update the URL and results; nonsense queries show an empty state", async ({
  page,
}) => {
  await page.goto("/explore?type=opportunity");
  const filterToggle = page.getByRole("button", { name: /^Filters/ });
  if (await filterToggle.isVisible()) await filterToggle.click();
  await page.getByLabel("Location").selectOption("remote");
  await expect(page).toHaveURL(/mode=remote/);
  await expect(page.getByText("Volta STEM Undergraduate Scholarship")).toBeVisible();
  await expect(page.getByText("Software Engineering Internship — Kente Digital")).toHaveCount(0);

  await page.goto("/explore?q=zzzqqq");
  await expect(page.getByRole("heading", { name: "No matches found" })).toBeVisible();

  // Invalid parameters fall back to defaults instead of crashing.
  const response = await page.goto("/explore?type=%3Cscript%3E&sort=drop");
  expect(response?.status()).toBe(200);
});

test("save an opportunity and find it in Saved and the dashboard", async ({ page }) => {
  await page.goto("/opportunities/opp-volta-stem-scholarship");
  const save = page.getByRole("button", { name: "Save Volta STEM Undergraduate Scholarship" });
  await save.click();
  await expect(save).toHaveAttribute("aria-pressed", "true");

  await navigate(page, "Saved");
  await expect(
    page.getByRole("link", { name: "Volta STEM Undergraduate Scholarship" }),
  ).toBeVisible();
  await expect(page.getByRole("heading", { name: /Due in 8–14 days/ })).toBeVisible();

  await navigate(page, "Dashboard");
  await expect(page.getByText("Saved opportunities")).toBeVisible();
  await expect(
    page.getByRole("link", { name: "Volta STEM Undergraduate Scholarship" }),
  ).toBeVisible();

  // Remove it again.
  await page.goto("/saved");
  await page
    .getByRole("button", { name: "Remove Volta STEM Undergraduate Scholarship from saved items" })
    .click();
  await expect(page.getByRole("heading", { name: "No saved opportunities" })).toBeVisible();
});

test("choosing interests produces explained recommendations", async ({ page }) => {
  await page.goto("/dashboard");
  await expect(page.getByRole("heading", { name: "No interests selected yet" })).toBeVisible();
  await page.getByText("Research", { exact: true }).first().click();
  const cards = page.getByTestId("recommendation");
  await expect(cards.first()).toBeVisible();
  await expect(cards.first()).toContainText("Matches your interests: Research");

  // Preferences persist across reloads (localStorage).
  await page.reload();
  await expect(page.getByTestId("recommendation").first()).toBeVisible();
});

test("calendar detects clashes between saved events", async ({ page }) => {
  await page.goto("/events/evt-pitch-clinic");
  await page.getByRole("button", { name: /I'm interested in Pitch Clinic/ }).click();
  await page.goto("/events/evt-ml-paper-reading");
  await page.getByRole("button", { name: /I'm interested in Paper Reading Group/ }).click();
  await navigate(page, "Calendar");
  await expect(page.getByText("1 schedule clash detected")).toBeVisible();
  await expect(page.getByRole("button", { name: /Export to calendar/ })).toBeEnabled();
});

test("organizations can be filtered and followed", async ({ page }) => {
  await page.goto("/organizations");
  await page.getByRole("link", { name: "Technology", exact: true }).click();
  await expect(page).toHaveURL(/category=tech/);
  await expect(page.getByText("2 organizations")).toBeVisible();
  const follow = page.getByRole("button", { name: "Follow Robotics & Automation Team" });
  await follow.click();
  await expect(follow).toHaveAttribute("aria-pressed", "true");
  await page.goto("/preferences");
  await expect(page.getByRole("link", { name: "Robotics & Automation Team" })).toBeVisible();
});

test("org rep submits an event and an admin approves it (demo roles)", async ({ page }) => {
  await page.goto("/manage");
  await page.getByRole("button", { name: /Switch to Organization rep/ }).click();
  await expect(page.getByText("Demonstration mode — not secure")).toBeVisible();

  await page.getByRole("button", { name: "Create event" }).click();
  await page.getByRole("button", { name: "Submit for review" }).click();
  await expect(page.getByRole("alert").filter({ hasText: "Please fix" })).toBeVisible();
  await expect(page.getByLabel("Title")).toBeFocused();

  const date = new Date(Date.now() + 5 * 86_400_000).toISOString().slice(0, 10);
  await page.getByLabel("Title").fill("Intro to Arduino Night");
  await page.getByLabel("Venue").fill("Fabrication Lab");
  await page.getByLabel("Short summary").fill("Learn the basics of Arduino in one evening.");
  await page
    .getByLabel("Description")
    .fill("A beginner-friendly session on microcontrollers, sensors and LEDs.");
  await page.getByLabel("Date").fill(date);
  await page.getByRole("checkbox", { name: "Engineering" }).check();
  await page.getByRole("button", { name: "Submit for review" }).click();
  await expect(page.getByRole("status")).toContainText("submitted for admin review");
  await expect(page.getByText("Pending review")).toBeVisible();

  await page.goto("/admin");
  await page.getByRole("button", { name: /Switch to Administrator/ }).click();
  await expect(page.getByRole("heading", { name: "Intro to Arduino Night" })).toBeVisible();
  await expect(page.getByText("Mentions payment or fees").first()).toBeVisible(); // the suspicious sample listing
  await page.getByRole("button", { name: "Approve Intro to Arduino Night" }).click();
  await expect(page.getByRole("status")).toContainText("approved");
  await expect(page.getByRole("heading", { name: "Content analytics" })).toBeVisible();
});

test("unknown routes show a helpful 404", async ({ page }) => {
  const response = await page.goto("/opportunities/does-not-exist");
  expect(response?.status()).toBe(404);
  await expect(page.getByRole("heading", { name: "We couldn’t find that page" })).toBeVisible();
});

test("mobile menu opens and navigates", async ({ page, isMobile }) => {
  test.skip(!isMobile, "mobile only");
  await page.goto("/");
  await page.getByRole("button", { name: "Open menu" }).click();
  await expect(page.getByRole("dialog", { name: "Menu" })).toBeVisible();
  await page.getByRole("link", { name: "Events", exact: true }).click();
  await expect(page).toHaveURL(/\/events$/);
  await expect(page.getByRole("dialog")).toHaveCount(0);
});

test("no horizontal scrolling on key pages", async ({ page }) => {
  // Worst case for the header: the admin demo role adds an extra nav link.
  await page.addInitScript(() =>
    localStorage.setItem(
      "campusbridge:user:v1",
      JSON.stringify({
        version: 1,
        interests: [],
        savedOpportunityIds: [],
        savedEventIds: [],
        followedOrganizationIds: [],
        role: "admin",
      }),
    ),
  );
  for (const path of [
    "/",
    "/explore",
    "/events",
    "/organizations",
    "/dashboard",
    "/calendar",
    "/about",
  ]) {
    await page.goto(path);
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(overflow, `${path} overflows horizontally`).toBeLessThanOrEqual(1);
  }
});
