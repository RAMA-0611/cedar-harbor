import { expect, test } from "@playwright/test";
import { loginAsAdmin, openAdminModule, waitForAdminTable } from "./helpers/auth";
import { uniqueStamp } from "./helpers/unique";

test("categories CRUD and in-use delete protection", async ({ page }) => {
  await loginAsAdmin(page);
  await openAdminModule(page, "Categorías");
  await waitForAdminTable(page);

  await expect(page.getByRole("table")).toContainText("Rodamientos");

  const stamp = uniqueStamp();
  const name = `Cat E2E ${stamp}`;
  const slug = `cat-e2e-${stamp}`;
  const edited = `${name} editada`;

  await page.getByRole("button", { name: "Nueva categoría" }).click();
  const form = page.getByRole("dialog", { name: "Nueva categoría" });
  await form.locator("#category-name").fill(name);
  await form.locator("#category-slug").fill(slug);
  await form.locator("#category-description").fill("Categoría aislada de Playwright.");
  await form.getByRole("button", { name: "Crear categoría" }).click();
  await expect(page.getByRole("status")).toHaveText("Categoría creada correctamente.");
  await expect(page.getByRole("table")).toContainText(slug);

  const created = page.locator("tbody tr", { hasText: slug });
  await created.getByRole("button", { name: "Ver" }).click();
  await expect(page.getByRole("dialog")).toContainText(slug);
  await page.getByRole("dialog").getByRole("button", { name: "Cerrar" }).click();

  await created.getByRole("button", { name: "Editar" }).click();
  const edit = page.getByRole("dialog", { name: "Editar categoría" });
  await edit.locator("#category-name").fill(edited);
  await edit.getByRole("button", { name: "Guardar cambios" }).click();
  await expect(page.getByRole("status")).toHaveText("Categoría actualizada correctamente.");
  await expect(page.getByRole("table")).toContainText(edited);

  const seeded = page.locator("tbody tr", { hasText: "Rodamientos" });
  await seeded.getByRole("button", { name: "Eliminar" }).click();
  await page.getByRole("dialog", { name: "Eliminar categoría" }).getByRole("button", { name: "Eliminar" }).click();
  await expect(page.locator("p[role='alert']")).toHaveText(
    "No se puede eliminar esta categoría porque está siendo utilizada por productos.",
  );
  await expect(page.getByRole("table")).toContainText("Rodamientos");
  await page.getByRole("dialog", { name: "Eliminar categoría" }).getByRole("button", { name: "Cancelar" }).click();

  const disposable = page.locator("tbody tr", { hasText: slug });
  await disposable.getByRole("button", { name: "Eliminar" }).click();
  await page.getByRole("dialog", { name: "Eliminar categoría" }).getByRole("button", { name: "Eliminar" }).click();
  await expect(page.getByRole("status")).toHaveText("Categoría eliminada correctamente.");
  await expect(page.getByRole("table")).not.toContainText(slug);
});
