import { apiGet, apiMutation } from "@/lib/api/client";
import type { AdminOrder, AdminOrderListResponse, AdminOrderUpdatePayload, OrderStatus } from "@/types/api";

export type ListOrdersParams = {
  page?: number;
  per_page?: number;
  status?: OrderStatus | "";
  search?: string;
};

export async function listOrders(params: ListOrdersParams = {}): Promise<AdminOrderListResponse> {
  const query = new URLSearchParams();
  query.set("page", String(params.page ?? 1));
  query.set("per_page", String(params.per_page ?? 20));
  if (params.status) {
    query.set("status", params.status);
  }
  const search = params.search?.trim();
  if (search) {
    query.set("search", search);
  }
  return apiGet<AdminOrderListResponse>(`/api/admin/orders?${query.toString()}`);
}

export async function getOrder(id: string): Promise<AdminOrder> {
  return apiGet<AdminOrder>(`/api/admin/orders/${id}`);
}

export async function updateOrder(id: string, payload: AdminOrderUpdatePayload): Promise<AdminOrder> {
  return apiMutation<AdminOrder>(`/api/admin/orders/${id}`, "PATCH", payload);
}

export async function cancelOrder(id: string): Promise<AdminOrder> {
  return updateOrder(id, { status: "CANCELLED" });
}

export async function deleteOrder(id: string): Promise<void> {
  await apiMutation<void>(`/api/admin/orders/${id}`, "DELETE");
}
