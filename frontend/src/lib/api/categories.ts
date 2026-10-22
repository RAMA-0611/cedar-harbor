import { apiGet, apiMutation } from "@/lib/api/client";
import type {
  AdminCategory,
  AdminListResponse,
  AdminResourceResponse,
  CreateCategoryPayload,
  UpdateCategoryPayload,
} from "@/types/api";

export async function listCategories(): Promise<AdminCategory[]> {
  const payload = await apiGet<AdminListResponse<AdminCategory>>("/api/admin/categories");
  return payload.data;
}

export async function getCategory(id: string): Promise<AdminCategory> {
  const payload = await apiGet<AdminResourceResponse<AdminCategory>>(
    `/api/admin/categories/${id}`,
  );
  return payload.data;
}

export async function createCategory(payload: CreateCategoryPayload): Promise<AdminCategory> {
  const response = await apiMutation<AdminResourceResponse<AdminCategory>>(
    "/api/admin/categories",
    "POST",
    payload,
  );
  return response.data;
}

export async function updateCategory(
  id: string,
  payload: UpdateCategoryPayload,
): Promise<AdminCategory> {
  const response = await apiMutation<AdminResourceResponse<AdminCategory>>(
    `/api/admin/categories/${id}`,
    "PATCH",
    payload,
  );
  return response.data;
}

export async function deleteCategory(id: string): Promise<void> {
  await apiMutation<void>(`/api/admin/categories/${id}`, "DELETE");
}
