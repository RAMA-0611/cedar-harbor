import { expect, test } from "@playwright/test";
import {
  customerEmail,
  customerPassword,
  loginAsAdmin,
  loginWith,
} from "./helpers/auth";

test("unauthenticated admin routes redirect to login", async ({ page }) => {
  await page.goto("/dashboard");
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.locator("#email")).toBeVisible();
});

test("invalid credentials show the login error", async ({ page }) => {
  await loginWith(page, "nobody.e2e@example.test", "wrong-password");
  await expect(page.locator("p[role='alert']")).toHaveText(
    "Correo electrónico o contraseña incorrectos.",
  );
  await expect(page).toHaveURL(/\/login$/);
});

test("admin login reaches the dashboard", async ({ page }) => {
  await loginAsAdmin(page);
  await expect(page.getByRole("heading", { name: "Usuarios" })).toBeVisible();
  await expect(page.getByText("Admin E2E")).toBeVisible();
});

test("customer login is denied administrative access", async ({ page }) => {
  await loginWith(page, customerEmail(), customerPassword());
  await expect(page).toHaveURL(/\/acceso-denegado$/);
  await expect(page.getByRole("heading", { name: "Acceso denegado" })).toBeVisible();
});

test("admin can log out", async ({ page }) => {
  await loginAsAdmin(page);
  await page.getByRole("button", { name: "Cerrar sesión" }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.goto("/dashboard");
  await expect(page).toHaveURL(/\/login$/);
});
