import { expect, test } from "@playwright/test";

test("guest can self-register as CUSTOMER and open cart", async ({ page }) => {
  const suffix = Date.now().toString(36);
  const email = `cliente.reg.${suffix}@example.test`;

  await page.goto("/cliente/registro?next=/carrito");
  await expect(page.getByRole("heading", { name: "Crear cuenta" })).toBeVisible();

  await page.locator("#name").fill(`Cliente Reg ${suffix}`);
  await page.locator("#email").fill(email);
  await page.locator("#phone").fill("3009876543");
  await page.locator("#document_type").selectOption("CC");
  await page.locator("#document_number").fill(`10${suffix}`.slice(0, 30));
  await page.locator("#password").fill("password123");
  await page.locator("#password_confirmation").fill("password123");
  await page.getByRole("button", { name: "Crear cuenta" }).click();

  await expect(page).toHaveURL(/\/carrito$/);
  await expect(page.getByRole("heading", { name: /carrito/i })).toBeVisible({
    timeout: 30_000,
  });
});

test("login page links to register preserving next", async ({ page }) => {
  await page.goto("/cliente/login?next=/producto/demo");
  await expect(page.getByText("¿No tienes cuenta?")).toBeVisible();
  await page.getByRole("link", { name: "Crear cuenta" }).click();
  await expect(page).toHaveURL(/\/cliente\/registro\?next=%2Fproducto%2Fdemo/);
});
