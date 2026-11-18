import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

/**
 * Automated WCAG checks with axe-core. Automated tools catch roughly a third
 * of accessibility issues; manual keyboard and screen-reader checks are still
 * needed (see README).
 */
const PAGES = [
  "/",
  "/explore?q=data",
  "/explore?type=opportunity",
  "/events",
  "/events/evt-intro-web-workshop",
  "/organizations",
  "/organizations/code-circuits",
  "/opportunities/opp-volta-stem-scholarship",
  "/dashboard",
  "/saved",
  "/calendar",
  "/preferences",
  "/about",
  "/does-not-exist",
];

for (const path of PAGES) {
  test(`no WCAG A/AA violations on ${path}`, async ({ page }) => {
    await page.goto(path);
    await page.waitForLoadState("networkidle");
    const results = await new AxeBuilder({ page })
      .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
      .analyze();
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`);
    expect(summary).toEqual([]);
  });
}

test("admin and manage demo areas are accessible", async ({ page }) => {
  await page.goto("/admin");
  await page.getByRole("button", { name: /Switch to Administrator/ }).click();
  await expect(page.getByRole("heading", { name: "Content analytics" })).toBeVisible();
  let results = await new AxeBuilder({ page })
    .withTags(["wcag2a", "wcag2aa", "wcag22aa"])
    .analyze();
  expect(results.violations.map((v) => `${v.id}: ${v.help}`)).toEqual([]);

  await page.goto("/manage");
  await page.getByRole("button", { name: /Switch to Organization rep/ }).click();
  await page.getByRole("button", { name: "Create event" }).click();
  results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag22aa"]).analyze();
  expect(results.violations.map((v) => `${v.id}: ${v.help}`)).toEqual([]);
});
