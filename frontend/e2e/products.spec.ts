import { expect, test } from "@playwright/test";
import { loginAsAdmin, openAdminModule, waitForAdminTable } from "./helpers/auth";
import { uniqueStamp } from "./helpers/unique";

test("products CRUD", async ({ page }) => {
  await loginAsAdmin(page);
  await openAdminModule(page, "Productos");
  await waitForAdminTable(page);

  await expect(page.getByRole("table")).toContainText("PR-CAB-3X10");

  const stamp = uniqueStamp();
  const sku = `E2E-${stamp}`.slice(0, 64);
  const name = `Producto E2E ${stamp}`;
  const edited = `${name} editado`;

  await page.getByRole("button", { name: "Nuevo producto" }).click();
  const form = page.getByRole("dialog", { name: "Nuevo producto" });
  await form.locator("#product-category").selectOption({ label: "Motores" });
  await form.locator("#product-sku").fill(sku);
  await form.locator("#product-name").fill(name);
  await form.locator("#product-price").fill("12500");
  await form.locator("#product-tax").fill("19");
  await form.locator("#product-stock").fill("8");
  await form.getByRole("button", { name: "Crear producto" }).click();
  await expect(page.getByRole("status")).toHaveText("Producto creado correctamente.");
  await expect(page.getByRole("table")).toContainText(sku);

  const row = page.locator("tbody tr", { hasText: sku });
  await row.getByRole("button", { name: "Ver" }).click();
  await expect(page.getByRole("dialog")).toContainText(sku);
  await page.getByRole("dialog").getByRole("button", { name: "Cerrar" }).click();

  await row.getByRole("button", { name: "Editar" }).click();
  const edit = page.getByRole("dialog", { name: "Editar producto" });
  await edit.locator("#product-name").fill(edited);
  await edit.getByRole("button", { name: "Guardar cambios" }).click();
  await expect(page.getByRole("status")).toHaveText("Producto actualizado correctamente.");
  await expect(page.getByRole("table")).toContainText(edited);

  const editedRow = page.locator("tbody tr", { hasText: sku });
  await editedRow.getByRole("button", { name: `Eliminar ${edited}` }).click();
  await page.getByRole("dialog", { name: "Eliminar producto" }).getByRole("button", { name: "Eliminar" }).click();
  await expect(page.getByRole("status")).toHaveText("Producto eliminado correctamente.");
  await expect(page.getByRole("table")).not.toContainText(sku);
});
