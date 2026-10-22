import { expect, test } from "@playwright/test";
import { loginAsAdmin, openAdminModule } from "./helpers/auth";

const MODULES = [
  "Dashboard",
  "Usuarios",
  "Productos",
  "Pedidos",
  "Categorías",
  "Documentación API",
  "Pruebas",
] as const;

test("dashboard shows live totals and navigation", async ({ page }) => {
  await loginAsAdmin(page);

  await expect(page.getByRole("heading", { name: "Usuarios" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Productos" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Categorías" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Pedidos" })).toBeVisible();

  for (const label of ["Usuarios", "Productos", "Categorías", "Pedidos"] as const) {
    const card = page.locator("article").filter({ has: page.getByRole("heading", { name: label }) });
    await expect(card.locator("p.tabular-nums")).toHaveText(/^\d+$/);
  }

  for (const name of MODULES) {
    await expect(
      page.getByRole("navigation", { name: "Administración" }).getByRole("link", { name }),
    ).toBeVisible();
  }

  await openAdminModule(page, "Usuarios");
  await openAdminModule(page, "Productos");
  await openAdminModule(page, "Pedidos");
  await openAdminModule(page, "Categorías");
  await page.getByRole("navigation", { name: "Administración" }).getByRole("link", { name: "Dashboard" }).click();
  await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible();
});
