import { apiGet } from "@/lib/api/client";
import type { CatalogProduct, CatalogResponse } from "@/types/catalog";

export async function pingPublicCatalog(): Promise<boolean> {
  const payload = await apiGet<CatalogResponse>("/api/products?page=1&per_page=1", {
    skipUnauthorizedHandler: true,
    cache: "no-store",
  });
  return Array.isArray(payload.data);
}

export async function listCatalogProducts(params?: {
  page?: number;
  perPage?: number;
  q?: string;
}): Promise<CatalogResponse> {
  const search = new URLSearchParams();
  search.set("page", String(params?.page ?? 1));
  search.set("per_page", String(params?.perPage ?? 100));
  if (params?.q && params.q.trim() !== "") {
    search.set("q", params.q.trim());
  }
  return apiGet<CatalogResponse>(`/api/products?${search.toString()}`, {
    cache: "no-store",
  });
}

export async function getCatalogProduct(id: string): Promise<CatalogProduct> {
  return apiGet<CatalogProduct>(`/api/products/${id}`, { cache: "no-store" });
}
