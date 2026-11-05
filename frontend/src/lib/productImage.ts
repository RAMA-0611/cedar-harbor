import type { CatalogProduct } from "@/types/catalog";

const PLACEHOLDERS: Record<string, string> = {
  rodamientos: "/product-placeholders/rodamientos.svg",
  motores: "/product-placeholders/motores.svg",
  cables: "/product-placeholders/cables.svg",
  proteccion: "/product-placeholders/proteccion.svg",
  conexion: "/product-placeholders/conexion.svg",
  default: "/product-placeholders/default.svg",
};

export function resolveProductImage(product: Pick<CatalogProduct, "image_url" | "category">): string {
  if (product.image_url) {
    return product.image_url;
  }
  const slug = product.category?.slug?.toLowerCase() ?? "";
  for (const key of Object.keys(PLACEHOLDERS)) {
    if (key !== "default" && slug.includes(key)) {
      return PLACEHOLDERS[key];
    }
  }
  return PLACEHOLDERS.default;
}

export function formatCop(amount: string): string {
  const n = Number(amount);
  if (!Number.isFinite(n)) {
    return `${amount} COP`;
  }
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    maximumFractionDigits: 0,
  }).format(n);
}
