import { expect, type Page } from "@playwright/test";

export function adminEmail(): string {
  const value = process.env.E2E_ADMIN_EMAIL;
  if (!value) {
    throw new Error("E2E_ADMIN_EMAIL is required locally.");
  }
  return value;
}

export function adminPassword(): string {
  const value = process.env.E2E_ADMIN_PASSWORD;
  if (!value) {
    throw new Error("E2E_ADMIN_PASSWORD is required locally.");
  }
  return value;
}

export function customerEmail(): string {
  const value = process.env.E2E_CUSTOMER_EMAIL;
  if (!value) {
    throw new Error("E2E_CUSTOMER_EMAIL is required locally.");
  }
  return value;
}

export function customerPassword(): string {
  const value = process.env.E2E_CUSTOMER_PASSWORD;
  if (!value) {
    throw new Error("E2E_CUSTOMER_PASSWORD is required locally.");
  }
  return value;
}

export async function loginWith(page: Page, email: string, password: string): Promise<void> {
  await page.goto("/login");
  await expect(page.getByRole("heading", { name: "Proyecto Rotor" })).toBeVisible();
  await page.locator("#email").fill(email);
  await page.locator("#password").fill(password);
  await page.getByRole("button", { name: "Iniciar sesión" }).click();
}

export async function loginAsAdmin(page: Page): Promise<void> {
  await loginWith(page, adminEmail(), adminPassword());
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible();
}

export async function openAdminModule(page: Page, name: string): Promise<void> {
  await page.getByRole("navigation", { name: "Administración" }).getByRole("link", { name, exact: true }).click();
  await expect(page.getByRole("heading", { name, exact: true })).toBeVisible();
}

export async function waitForAdminTable(page: Page): Promise<void> {
  await expect(page.getByRole("status", { name: /Cargando / })).toHaveCount(0, {
    timeout: 30_000,
  });
  await expect(page.getByRole("table")).toBeVisible({ timeout: 30_000 });
}
