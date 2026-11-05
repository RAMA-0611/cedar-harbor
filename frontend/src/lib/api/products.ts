import { apiGet, apiMutation, apiRequest } from "@/lib/api/client";
import type {
  AdminListResponse,
  AdminProduct,
  AdminResourceResponse,
  CreateProductPayload,
  UpdateProductPayload,
} from "@/types/api";

export async function listProducts(): Promise<AdminProduct[]> {
  const payload = await apiGet<AdminListResponse<AdminProduct>>("/api/admin/products");
  return payload.data;
}

export async function getProduct(id: string): Promise<AdminProduct> {
  const payload = await apiGet<AdminResourceResponse<AdminProduct>>(
    `/api/admin/products/${id}`,
  );
  return payload.data;
}

export async function createProduct(payload: CreateProductPayload): Promise<AdminProduct> {
  const response = await apiMutation<AdminResourceResponse<AdminProduct>>(
    "/api/admin/products",
    "POST",
    payload,
  );
  return response.data;
}

export async function updateProduct(
  id: string,
  payload: UpdateProductPayload,
): Promise<AdminProduct> {
  const response = await apiMutation<AdminResourceResponse<AdminProduct>>(
    `/api/admin/products/${id}`,
    "PATCH",
    payload,
  );
  return response.data;
}

export async function deleteProduct(id: string): Promise<void> {
  await apiMutation<void>(`/api/admin/products/${id}`, "DELETE");
}

export async function uploadProductImage(id: string, file: File): Promise<AdminProduct> {
  const formData = new FormData();
  formData.append("image", file);
  const response = await apiRequest<AdminResourceResponse<AdminProduct>>(
    `/api/admin/products/${id}/image`,
    {
      method: "POST",
      formData,
    },
  );
  return response.data;
}

export async function deleteProductImage(id: string): Promise<void> {
  await apiMutation<void>(`/api/admin/products/${id}/image`, "DELETE");
}
