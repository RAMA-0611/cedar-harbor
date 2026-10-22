import { expect, test } from "@playwright/test";
import { loginAsAdmin } from "./helpers/auth";

test("admin shell adapts between desktop and mobile", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await loginAsAdmin(page);

  await expect(page.getByRole("button", { name: "Menú" })).toBeHidden();
  await expect(
    page.getByRole("navigation", { name: "Administración" }).getByRole("link", { name: "Dashboard" }),
  ).toBeVisible();

  await page.setViewportSize({ width: 375, height: 812 });
  await expect(page.getByRole("button", { name: "Menú" })).toBeVisible();
  await page.getByRole("button", { name: "Menú" }).click();
  await expect(page.getByRole("dialog", { name: "Proyecto Rotor" })).toBeVisible();
  await page.getByRole("dialog").getByRole("link", { name: "Pedidos" }).click();
  await expect(page.getByRole("heading", { name: "Pedidos" })).toBeVisible();
});
