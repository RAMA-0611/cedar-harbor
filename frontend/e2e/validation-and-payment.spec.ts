import { expect, test, type Page } from "@playwright/test";
import { customerEmail, customerPassword } from "./helpers/auth";
import { CABLE_PRODUCT_ID } from "./helpers/customer-order";

async function customerLogin(page: Page) {
  await page.goto("/cliente/login?next=/");
  await page.locator("#email").fill(customerEmail());
  await page.locator("#password").fill(customerPassword());
  await page.getByRole("button", { name: /iniciar sesión/i }).click();
  await expect(page.getByText(customerEmail())).toBeVisible({ timeout: 30_000 });
}

async function buyUntilPayment(page: Page) {
  await customerLogin(page);
  await page.goto(`/producto/${CABLE_PRODUCT_ID}`);
  await page.getByRole("button", { name: "Agregar al carrito" }).click();
  await expect(page.getByText("Producto agregado al carrito.")).toBeVisible();
  await page.goto("/carrito");
  await page.getByRole("link", { name: "Ir a checkout" }).click();
  await expect(page).toHaveURL(/\/checkout$/);

  await page.locator("#phone").fill("3009000001");
  await page.locator("#document_type").selectOption("CC");
  await page.locator("#document_number").fill("9000000001");
  await page.getByRole("button", { name: "Crear pedido" }).click();
  await expect(page).toHaveURL(/\/pedido\//, { timeout: 30_000 });
  await expect(page.getByText("PENDING_PAYMENT")).toBeVisible();
  await page.getByRole("button", { name: /Pagar/ }).click();
  await expect(page).toHaveURL(/\/pago\/simulado\//, { timeout: 30_000 });
}

async function payWith(page: Page, number: string) {
  await page.locator("#card_holder").fill("Cliente Demo");
  await page.locator("#card_number").fill(number);
  await page.locator("#expiry").fill("1240");
  await page.locator("#cvc").fill("123");
  await page.getByRole("button", { name: /^Pagar/ }).click();
}

test.describe("validaciones de formulario", () => {
  test("teléfono solo acepta 10 dígitos y el tipo de documento es una lista fija", async ({ page }) => {
    await page.goto("/cliente/registro");

    const phone = page.locator("#phone");
    await phone.fill("abc300-123 4567xyz+");
    await expect(phone).toHaveValue("3001234567");

    await phone.fill("2001234567");
    await page.locator("#name").fill("Ana Perez");
    await page.locator("#email").fill("ana@example.test");
    await page.locator("#document_number").fill("123456789");
    await page.locator("#password").fill("password123");
    await page.locator("#password_confirmation").fill("password123");
    await page.getByRole("button", { name: "Crear cuenta" }).click();
    await expect(page.getByText("empezar por 3").first()).toBeVisible();
    await expect(page).toHaveURL(/\/cliente\/registro/);

    const select = page.locator("#document_type");
    await expect(select.locator("option")).toHaveCount(4);
    await expect(select.locator("option")).toHaveText([
      "CC — Cédula de ciudadanía",
      "CE — Cédula de extranjería",
      "NIT",
      "PP — Pasaporte",
    ]);

    const number = page.locator("#document_number");
    await number.fill("12ab34.56-78");
    await expect(number).toHaveValue("12345678");
  });

  test("contraseña débil y nombre con números se rechazan antes de enviar", async ({ page }) => {
    await page.goto("/cliente/registro");
    await page.locator("#name").fill("Ana123");
    await page.locator("#email").fill("correo-invalido");
    await page.locator("#phone").fill("3001234567");
    await page.locator("#document_number").fill("123456789");
    await page.locator("#password").fill("sololetras");
    await page.locator("#password_confirmation").fill("otra");
    await page.getByRole("button", { name: "Crear cuenta" }).click();
    await expect(page.getByText("El nombre solo puede tener letras").first()).toBeVisible();
    await expect(page.getByText("Escribe un correo válido").first()).toBeVisible();
    await expect(page.getByText("al menos una letra y un número").first()).toBeVisible();
    await expect(page.getByText("Las contraseñas no coinciden.")).toBeVisible();
  });
});

test.describe("carrito y pago simulado", () => {
  test("la cantidad del carrito se ajusta al stock disponible", async ({ page }) => {
    await customerLogin(page);
    await page.goto(`/producto/${CABLE_PRODUCT_ID}`);
    const qty = page.locator("#qty");
    await qty.fill("9x9");
    await expect(qty).toHaveValue("99");
    await qty.fill("999");
    await qty.blur();
    await expect(page.getByText(/se ajustó la cantidad/)).toBeVisible();
    const adjusted = Number(await qty.inputValue());
    expect(adjusted).toBeGreaterThanOrEqual(1);
    expect(adjusted).toBeLessThan(999);
  });

  test("compra completa con tarjeta aprobada", async ({ page }) => {
    await buyUntilPayment(page);
    await payWith(page, "4242424242424242");
    await expect(page.getByText("Pago aprobado.")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByText("PAID").first()).toBeVisible();
    await page.getByRole("link", { name: "Ver mi pedido" }).click();
    await expect(page.getByText("Pago confirmado")).toBeVisible();
  });

  test("tarjeta con fondos insuficientes deja el pedido en FAILED", async ({ page }) => {
    await buyUntilPayment(page);
    await payWith(page, "4000000000009995");
    await expect(page.getByText("Fondos insuficientes.")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByText("FAILED").first()).toBeVisible();
  });

  test("tarjeta pendiente se resuelve con la verificación del banco", async ({ page }) => {
    await buyUntilPayment(page);
    await payWith(page, "4000000000003220");
    await expect(page.getByText("Pago pendiente de verificación.")).toBeVisible({ timeout: 30_000 });
    await page.getByRole("button", { name: "Simular: el banco aprueba" }).click();
    await expect(page.getByText("Pago aprobado.")).toBeVisible({ timeout: 30_000 });
  });

  test("tarjeta inválida muestra errores sin llamar al servidor", async ({ page }) => {
    await buyUntilPayment(page);
    await payWith(page, "4242424242424241");
    await expect(page.getByText("El número de tarjeta no es válido")).toBeVisible();
  });
});
