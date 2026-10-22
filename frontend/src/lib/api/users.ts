import { apiGet, apiMutation } from "@/lib/api/client";
import type {
  AdminListResponse,
  AdminResourceResponse,
  AdminUser,
  CreateUserPayload,
  UpdateUserPayload,
} from "@/types/api";

export async function listUsers(): Promise<AdminUser[]> {
  const payload = await apiGet<AdminListResponse<AdminUser>>("/api/admin/users");
  return payload.data;
}

export async function getUser(id: number): Promise<AdminUser> {
  const payload = await apiGet<AdminResourceResponse<AdminUser>>(`/api/admin/users/${id}`);
  return payload.data;
}

export async function createUser(payload: CreateUserPayload): Promise<AdminUser> {
  const response = await apiMutation<AdminResourceResponse<AdminUser>>(
    "/api/admin/users",
    "POST",
    payload,
  );
  return response.data;
}

export async function updateUser(id: number, payload: UpdateUserPayload): Promise<AdminUser> {
  const response = await apiMutation<AdminResourceResponse<AdminUser>>(
    `/api/admin/users/${id}`,
    "PATCH",
    payload,
  );
  return response.data;
}

export async function deleteUser(id: number): Promise<void> {
  await apiMutation<void>(`/api/admin/users/${id}`, "DELETE");
}
