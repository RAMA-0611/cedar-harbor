import { expect, test, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { customerEmail, customerPassword, loginAsAdmin } from "./helpers/auth";
import { CABLE_PRODUCT_ID } from "./helpers/customer-order";

// Pantalla de un teléfono Android común (360 × 740 CSS px) con entrada táctil.
test.use({
  viewport: { width: 360, height: 740 },
  hasTouch: true,
  isMobile: true,
  userAgent:
    "Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36",
});

const shotsDir = process.env.MOBILE_SHOTS_DIR;

async function expectFitsScreen(page: Page, name: string) {
  await page.waitForLoadState("networkidle");
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return { scroll: doc.scrollWidth, client: doc.clientWidth };
  });
  if (shotsDir) {
    fs.mkdirSync(shotsDir, { recursive: true });
    await page.screenshot({ path: path.join(shotsDir, `${name}.png`), fullPage: true });
  }
  expect(overflow.scroll, `${name}: la página se desborda horizontalmente`).toBeLessThanOrEqual(
    overflow.client + 1,
  );
}

test.describe("tienda en Android", () => {
  test("páginas públicas caben en la pantalla", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("link", { name: "Catálogo" })).toBeVisible();
    await expectFitsScreen(page, "01-catalogo");

    await page.goto(`/producto/${CABLE_PRODUCT_ID}`);
    await expect(page.getByText("PR-CAB-3X10").first()).toBeVisible();
    await expectFitsScreen(page, "02-producto");

    await page.goto("/cliente/registro");
    await expect(page.getByRole("button", { name: "Crear cuenta" })).toBeVisible();
    await expectFitsScreen(page, "03-registro");

    await page.goto("/cliente/login");
    await expectFitsScreen(page, "04-login-cliente");

    // El ojito alterna entre mostrar y ocultar la contraseña.
    await page.locator("#password").fill("Secreta123");
    await expect(page.locator("#password")).toHaveAttribute("type", "password");
    await page.getByRole("button", { name: "Mostrar contraseña" }).click();
    await expect(page.locator("#password")).toHaveAttribute("type", "text");
    await page.getByRole("button", { name: "Ocultar contraseña" }).click();
    await expect(page.locator("#password")).toHaveAttribute("type", "password");

    await page.goto("/login");
    await expectFitsScreen(page, "05-login-admin");
  });

  test("compra completa desde el teléfono", async ({ page }) => {
    await page.goto("/cliente/login?next=/");
    await page.locator("#email").fill(customerEmail());
    await page.locator("#password").fill(customerPassword());
    await page.getByRole("button", { name: /iniciar sesión/i }).click();
    await expect(page.getByText(customerEmail())).toBeVisible({ timeout: 30_000 });

    await page.goto(`/producto/${CABLE_PRODUCT_ID}`);
    await page.getByRole("button", { name: "Agregar al carrito" }).click();
    await expect(page.getByText("Producto agregado al carrito.")).toBeVisible();

    await page.goto("/carrito");
    await expect(page.getByRole("link", { name: "Ir a checkout" })).toBeVisible();
    await expectFitsScreen(page, "06-carrito");

    await page.getByRole("link", { name: "Ir a checkout" }).click();
    await expect(page).toHaveURL(/\/checkout$/);
    await expectFitsScreen(page, "07-checkout");

    await page.locator("#phone").fill("3009000001");
    await page.locator("#document_type").selectOption("CC");
    await page.locator("#document_number").fill("9000000001");
    await page.getByRole("button", { name: "Crear pedido" }).click();
    await expect(page).toHaveURL(/\/pedido\//, { timeout: 30_000 });
    await expect(page.getByText("PENDING_PAYMENT")).toBeVisible();
    await expectFitsScreen(page, "08-pedido");

    await page.getByRole("button", { name: /Pagar/ }).click();
    await expect(page).toHaveURL(/\/pago\/simulado\//, { timeout: 30_000 });
    await expectFitsScreen(page, "09-pago");

    // Las tarjetas de prueba deben verse (texto oscuro, no blanco sobre blanco).
    const firstTestCard = page.locator(".store-test-card-number").first();
    await expect(firstTestCard).toBeVisible();
    await expect(firstTestCard).toContainText("4242");
    const color = await firstTestCard.evaluate((el) => getComputedStyle(el).color);
    expect(color).not.toBe("rgb(255, 255, 255)");

    await page.locator("#card_holder").fill("Cliente Demo");
    await page.locator("#card_number").fill("4242424242424242");
    await page.locator("#expiry").fill("1240");
    await page.locator("#cvc").fill("123");
    await page.getByRole("button", { name: /^Pagar/ }).click();
    await expect(page.getByRole("link", { name: "Ver mi pedido" })).toBeVisible({ timeout: 30_000 });
    await expectFitsScreen(page, "10-pago-aprobado");
  });
});

test.describe("panel en Android", () => {
  test("los módulos de administración caben en la pantalla", async ({ page }) => {
    await loginAsAdmin(page);
    await expectFitsScreen(page, "11-dashboard");

    for (const [route, name] of [
      ["/productos", "12-productos"],
      ["/categorias", "13-categorias"],
      ["/usuarios", "14-usuarios"],
      ["/pedidos", "15-pedidos"],
    ] as const) {
      await page.goto(route);
      await expect(page.getByRole("button", { name: "Menú" })).toBeVisible();
      await expectFitsScreen(page, name);
    }
  });
});
