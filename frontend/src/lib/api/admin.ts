import { apiGet } from "@/lib/api/client";
import type { AdminListResponse, AdminOrderListResponse } from "@/types/api";

function collectionLength(payload: AdminListResponse<unknown> | null | undefined): number {
  return Array.isArray(payload?.data) ? payload.data.length : 0;
}

export async function fetchAdminUsersCount(): Promise<number> {
  const payload = await apiGet<AdminListResponse<unknown>>("/api/admin/users");
  return collectionLength(payload);
}

export async function fetchAdminProductsCount(): Promise<number> {
  const payload = await apiGet<AdminListResponse<unknown>>("/api/admin/products");
  return collectionLength(payload);
}

export async function fetchAdminCategoriesCount(): Promise<number> {
  const payload = await apiGet<AdminListResponse<unknown>>("/api/admin/categories");
  return collectionLength(payload);
}

export async function fetchAdminOrdersTotal(): Promise<number> {
  const payload = await apiGet<AdminOrderListResponse>("/api/admin/orders?page=1&per_page=1");
  if (typeof payload.meta?.total === "number") {
    return payload.meta.total;
  }
  return Array.isArray(payload.data) ? payload.data.length : 0;
}
