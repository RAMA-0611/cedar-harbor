import { apiGet } from "@/lib/api/client";
import type { CatalogProductsResponse } from "@/types/api";

export async function pingPublicCatalog(): Promise<boolean> {
  const payload = await apiGet<CatalogProductsResponse>("/api/products?page=1&per_page=1", {
    skipUnauthorizedHandler: true,
  });
  return Array.isArray(payload.data);
}
