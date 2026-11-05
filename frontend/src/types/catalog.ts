export type CatalogCategory = {
  id: string;
  name: string;
  slug: string;
};

export type CatalogProduct = {
  id: string;
  sku: string;
  name: string;
  unit_price: string;
  currency: "COP";
  available_quantity: number;
  tax_rate: string;
  description: string | null;
  brand: string | null;
  condition: string | null;
  unit: string | null;
  image_url: string | null;
  category: CatalogCategory | null;
};

export type CatalogResponse = {
  data: CatalogProduct[];
  meta: {
    page: number;
    per_page: number;
    total: number;
  };
};
