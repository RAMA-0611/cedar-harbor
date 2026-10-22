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

export type AdminOrderListResponse = {
  data: unknown[];
  meta: {
    page: number;
    per_page: number;
    total: number;
  };
};

export type CatalogProductsResponse = {
  data?: unknown[];
};
