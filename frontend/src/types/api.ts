import type { UserRole } from "@/types/auth";

export type ApiError = {
  code: string;
  message: string;
  details: Record<string, unknown>;
  trace_id?: string;
  status: number;
};

export class ApiClientError extends Error {
  readonly error: ApiError;

  constructor(error: ApiError) {
    super(error.message);
    this.name = "ApiClientError";
    this.error = error;
  }
}

export type AdminListResponse<T> = {
  data: T[];
};

export type OrderStatus =
  | "DRAFT"
  | "PENDING_PAYMENT"
  | "PAID"
  | "FULFILLED"
  | "FAILED"
  | "EXPIRED"
  | "CANCELLED";

export type AdminOrderCustomer = {
  name: string;
  email: string;
  phone: string;
  document_type: string;
  document_number: string;
};

export type AdminOrderDelivery = {
  mode: "PICKUP" | "COORDINATED_SHIPPING";
  address: string | null;
};

export type AdminOrderItem = {
  id: string;
  product_id: string;
  sku: string;
  product_name: string;
  quantity: number;
  unit_price: string;
  tax_amount: string;
  line_total: string;
};

export type AdminOrder = {
  id: string;
  order_number: string;
  status: OrderStatus;
  currency: "COP";
  subtotal: string;
  tax_total: string;
  total: string;
  customer: AdminOrderCustomer;
  delivery: AdminOrderDelivery;
  items?: AdminOrderItem[];
  created_at: string;
  updated_at: string;
  cancelled_at: string | null;
};

export type AdminOrderUpdatePayload = {
  customer_name?: string;
  customer_email?: string;
  customer_phone?: string;
  customer_document_type?: string;
  customer_document_number?: string;
  delivery_mode?: "PICKUP" | "COORDINATED_SHIPPING";
  delivery_address?: string | null;
  status?: "CANCELLED";
};

export type AdminOrderListResponse = {
  data: AdminOrder[];
  meta: {
    page: number;
    per_page: number;
    total: number;
  };
};

export type CatalogProductsResponse = {
  data?: unknown[];
};

export type AdminUser = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  created_at: string | null;
  updated_at: string | null;
};

export type AdminCategory = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
};

export type CreateUserPayload = {
  name: string;
  email: string;
  password: string;
  role: UserRole;
};

export type UpdateUserPayload = {
  name?: string;
  email?: string;
  password?: string;
  role?: UserRole;
};

export type CreateCategoryPayload = {
  name: string;
  slug: string;
  description?: string | null;
  is_active: boolean;
};

export type UpdateCategoryPayload = {
  name?: string;
  slug?: string;
  description?: string | null;
  is_active?: boolean;
};

export type AdminProduct = {
  id: string;
  category_id: string;
  sku: string;
  name: string;
  description: string | null;
  brand: string | null;
  condition: string | null;
  unit: string | null;
  price: string;
  currency: "COP";
  tax_rate: string;
  stock_quantity: number;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
};

export type CreateProductPayload = {
  category_id: string;
  sku: string;
  name: string;
  description?: string | null;
  brand?: string | null;
  condition?: string | null;
  unit?: string | null;
  price: string;
  currency: "COP";
  tax_rate: string;
  stock_quantity: number;
  is_active: boolean;
};

export type UpdateProductPayload = {
  category_id?: string;
  sku?: string;
  name?: string;
  description?: string | null;
  brand?: string | null;
  condition?: string | null;
  unit?: string | null;
  price?: string;
  currency?: "COP";
  tax_rate?: string;
  stock_quantity?: number;
  is_active?: boolean;
};

export type AdminResourceResponse<T> = {
  data: T;
};
