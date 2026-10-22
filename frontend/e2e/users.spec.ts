import { expect, test } from "@playwright/test";
import { adminEmail, loginAsAdmin, openAdminModule, waitForAdminTable } from "./helpers/auth";
import { uniqueEmail, uniqueStamp } from "./helpers/unique";

test("users CRUD and self-delete protection", async ({ page }) => {
  await loginAsAdmin(page);
  await openAdminModule(page, "Usuarios");
  await waitForAdminTable(page);

  await expect(page.getByRole("table")).toContainText(adminEmail());
  await expect(
    page.getByRole("button", { name: "Eliminar. No puede eliminar su propia cuenta." }),
  ).toBeDisabled();

  const stamp = uniqueStamp();
  const name = `Usuario E2E ${stamp}`;
  const edited = `${name} editado`;
  const email = uniqueEmail("user.e2e");
  const password = `Pw${stamp}xx`;

  await page.getByRole("button", { name: "Nuevo usuario" }).click();
  const form = page.getByRole("dialog", { name: "Nuevo usuario" });
  await form.locator("#user-name").fill(name);
  await form.locator("#user-email").fill(email);
  await form.locator('input[name="password"]').fill(password);
  await form.locator("#user-role").selectOption("CUSTOMER");
  await form.getByRole("button", { name: "Crear usuario" }).click();
  await expect(page.getByRole("status")).toHaveText("Usuario creado correctamente.");
  await expect(page.getByRole("table")).toContainText(email);

  const row = page.locator("tbody tr", { hasText: email });
  await row.getByRole("button", { name: "Ver" }).click();
  await expect(page.getByRole("dialog", { name: "Detalle de usuario" })).toContainText(email);
  await page.getByRole("dialog").getByRole("button", { name: "Cerrar" }).click();

  await row.getByRole("button", { name: "Editar" }).click();
  const edit = page.getByRole("dialog", { name: "Editar usuario" });
  await edit.locator("#user-name").fill(edited);
  await edit.getByRole("button", { name: "Guardar cambios" }).click();
  await expect(page.getByRole("status")).toHaveText("Usuario actualizado correctamente.");
  await expect(page.getByRole("table")).toContainText(edited);

  const editedRow = page.locator("tbody tr", { hasText: email });
  await editedRow.getByRole("button", { name: `Eliminar a ${edited}` }).click();
  await page.getByRole("dialog", { name: "Eliminar usuario" }).getByRole("button", { name: "Eliminar" }).click();
  await expect(page.getByRole("status")).toHaveText("Usuario eliminado correctamente.");
  await expect(page.getByRole("table")).not.toContainText(email);
});
