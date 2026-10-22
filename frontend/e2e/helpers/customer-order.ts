import { type APIRequestContext } from "@playwright/test";
import { randomUUID } from "node:crypto";
import { customerEmail, customerPassword } from "./auth";

export const CABLE_PRODUCT_ID = "7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a03";

const ORIGIN = "http://127.0.0.1:3001";

function apiBase(): string {
  const value = process.env.E2E_API_URL;
  if (!value) {
    throw new Error("E2E_API_URL is required.");
  }
  return value.replace(/\/+$/, "");
}

async function xsrfToken(request: APIRequestContext): Promise<string> {
  const state = await request.storageState();
  const raw = state.cookies.find((cookie) => cookie.name === "XSRF-TOKEN")?.value;
  if (!raw) {
    throw new Error("Missing XSRF-TOKEN cookie.");
  }
  return decodeURIComponent(raw);
}

async function jsonOrThrow(
  response: Awaited<ReturnType<APIRequestContext["get"]>>,
  label: string,
): Promise<unknown> {
  if (!response.ok()) {
    throw new Error(`${label} failed with HTTP ${response.status()}`);
  }
  return response.json();
}

export async function createCustomerOrder(
  request: APIRequestContext,
): Promise<{ id: string; orderNumber: string }> {
  const api = apiBase();
  const headers = {
    Origin: ORIGIN,
    Referer: `${ORIGIN}/`,
    Accept: "application/json",
  };

  const csrf = await request.get(`${api}/sanctum/csrf-cookie`, { headers });
  if (!csrf.ok()) {
    throw new Error(`csrf-cookie failed with HTTP ${csrf.status()}`);
  }

  const login = await request.post(`${api}/api/auth/login`, {
    headers: {
      ...headers,
      "Content-Type": "application/json",
      "X-XSRF-TOKEN": await xsrfToken(request),
    },
    data: {
      email: customerEmail(),
      password: customerPassword(),
    },
  });
  await jsonOrThrow(login, "customer login");

  const addItem = await request.post(`${api}/api/cart/items`, {
    headers: {
      ...headers,
      "Content-Type": "application/json",
      "X-XSRF-TOKEN": await xsrfToken(request),
    },
    data: {
      product_id: CABLE_PRODUCT_ID,
      quantity: 1,
    },
  });
  await jsonOrThrow(addItem, "cart item");

  const created = await request.post(`${api}/api/orders`, {
    headers: {
      ...headers,
      "Content-Type": "application/json",
      "Idempotency-Key": randomUUID(),
      "X-XSRF-TOKEN": await xsrfToken(request),
    },
    data: {
      customer: {
        name: "Customer E2E",
        email: customerEmail(),
        phone: "+579000000001",
        document_type: "CC",
        document_number: "9000000001",
      },
      delivery: {
        mode: "PICKUP",
      },
    },
  });
  const payload = (await jsonOrThrow(created, "create order")) as {
    id?: string;
    order_number?: string;
  };

  if (!payload.id || !payload.order_number) {
    throw new Error("create order returned an incomplete payload.");
  }

  return { id: payload.id, orderNumber: payload.order_number };
}
