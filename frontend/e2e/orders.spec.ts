import { expect, test } from "@playwright/test";
import { loginAsAdmin, openAdminModule, waitForAdminTable } from "./helpers/auth";
import { createCustomerOrder } from "./helpers/customer-order";

test("customer create plus admin order lifecycle", async ({ page, request }) => {
  await loginAsAdmin(page);
  await openAdminModule(page, "Pedidos");

  await expect(page.getByText("La creación de pedidos se realiza mediante el flujo transaccional del cliente")).toBeVisible();
  await expect(page.getByRole("link", { name: "Ver documentación API" })).toBeVisible();
  await expect(page.getByRole("button", { name: "Nuevo pedido" })).toHaveCount(0);
  await expect(page.getByRole("button", { name: "Crear pedido" })).toHaveCount(0);

  const created = await createCustomerOrder(request);
  await page.reload();
  await expect(page.getByRole("heading", { name: "Pedidos" })).toBeVisible();
  await waitForAdminTable(page);
  await expect(page.getByRole("table")).toContainText(created.orderNumber);
  await expect(page.getByRole("table")).toContainText("Pendiente de pago");

  await page.locator("#order-search").fill(created.orderNumber);
  await page.getByRole("button", { name: "Buscar" }).click();
  await expect(page.getByRole("table")).toContainText(created.orderNumber);

  await page.locator("#order-status-filter").selectOption("PENDING_PAYMENT");
  await expect(page.getByRole("table")).toContainText(created.orderNumber);

  const row = page.locator("tbody tr", { hasText: created.orderNumber });
  await row.getByRole("button", { name: "Ver" }).click();
  await expect(page.getByRole("dialog")).toContainText(created.orderNumber);
  await page.getByRole("dialog").getByRole("button", { name: "Cerrar" }).click();

  await row.getByRole("button", { name: "Editar" }).click();
  const edit = page.getByRole("dialog", { name: "Editar pedido" });
  await edit.locator("#order-customer-phone").fill("+579000000099");
  await edit.getByRole("button", { name: "Guardar cambios" }).click();
  await expect(page.getByRole("status")).toHaveText("Pedido actualizado correctamente.");

  const afterEdit = page.locator("tbody tr", { hasText: created.orderNumber });
  await afterEdit.getByRole("button", { name: "Cancelar pedido" }).click();
  await page
    .getByRole("dialog", { name: "Cancelar pedido" })
    .getByRole("button", { name: "Cancelar pedido" })
    .click();
  await expect(page.getByRole("status")).toHaveText("Pedido cancelado correctamente.");

  await page.locator("#order-status-filter").selectOption("CANCELLED");
  await page.locator("#order-search").fill(created.orderNumber);
  await page.getByRole("button", { name: "Buscar" }).click();
  await expect(page.getByRole("table")).toContainText(created.orderNumber);
  await expect(page.getByRole("table")).toContainText("Cancelado");

  const cancelled = page.locator("tbody tr", { hasText: created.orderNumber });
  await cancelled.getByRole("button", { name: `Eliminar ${created.orderNumber}` }).click();
  await page.getByRole("dialog", { name: "Eliminar pedido" }).getByRole("button", { name: "Eliminar" }).click();
  await expect(page.getByRole("status")).toHaveText("Pedido eliminado correctamente.");
  await expect(page.getByText(created.orderNumber)).toHaveCount(0);
});
