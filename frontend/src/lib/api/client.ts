import { ensureCsrfCookie, getXsrfToken } from "@/lib/api/csrf";
import { getApiBaseUrl } from "@/lib/config";
import { ApiClientError, type ApiError } from "@/types/api";

const DEFAULT_TIMEOUT_MS = 15000;

type UnauthorizedHandler = () => void;

let unauthorizedHandler: UnauthorizedHandler | null = null;

export function onUnauthorized(handler: UnauthorizedHandler): () => void {
  unauthorizedHandler = handler;
  return () => {
    if (unauthorizedHandler === handler) {
      unauthorizedHandler = null;
    }
  };
}

export type ApiRequestOptions = {
  method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE";
  body?: unknown;
  skipUnauthorizedHandler?: boolean;
  timeoutMs?: number;
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function toApiError(status: number, payload: unknown): ApiError {
  if (isRecord(payload) && typeof payload.code === "string" && typeof payload.message === "string") {
    const details = isRecord(payload.details) ? payload.details : {};
    const traceId = typeof payload.trace_id === "string" ? payload.trace_id : undefined;
    return {
      code: payload.code,
      message: payload.message,
      details,
      trace_id: traceId,
      status,
    };
  }

  return {
    code: "HTTP_ERROR",
    message: status === 0 ? "No se pudo conectar con el servidor." : "No se pudo completar la solicitud.",
    details: {},
    status,
  };
}

async function parseBody(response: Response): Promise<unknown> {
  if (response.status === 204) {
    return undefined;
  }

  const text = await response.text();
  if (text.trim() === "") {
    return undefined;
  }

  try {
    return JSON.parse(text) as unknown;
  } catch {
    throw new ApiClientError({
      code: "INVALID_RESPONSE",
      message: "La respuesta del servidor no es válida.",
      details: {},
      status: response.status,
    });
  }
}

export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const method = options.method ?? "GET";
  const isMutation = method !== "GET";
  const controller = new AbortController();
  const timeout = globalThis.setTimeout(
    () => controller.abort(),
    options.timeoutMs ?? DEFAULT_TIMEOUT_MS,
  );

  try {
    if (isMutation) {
      await ensureCsrfCookie();
    }

    const headers: Record<string, string> = {
      Accept: "application/json",
    };

    if (isMutation) {
      const xsrf = getXsrfToken();
      if (xsrf) {
        headers["X-XSRF-TOKEN"] = xsrf;
      }
    }

    if (options.body !== undefined) {
      headers["Content-Type"] = "application/json";
    }

    const response = await fetch(`${getApiBaseUrl()}${path}`, {
      method,
      credentials: "include",
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: controller.signal,
    });

    const payload = await parseBody(response);

    if (!response.ok) {
      const error = toApiError(response.status, payload);
      if (response.status === 401 && !options.skipUnauthorizedHandler) {
        unauthorizedHandler?.();
      }
      throw new ApiClientError(error);
    }

    return payload as T;
  } catch (caught) {
    if (caught instanceof ApiClientError) {
      throw caught;
    }

    const aborted = caught instanceof DOMException && caught.name === "AbortError";
    throw new ApiClientError({
      code: aborted ? "TIMEOUT" : "NETWORK_ERROR",
      message: aborted
        ? "La solicitud tardó demasiado. Inténtelo de nuevo."
        : "No se pudo conectar con el servidor. Verifique que la API esté en ejecución.",
      details: {},
      status: 0,
    });
  } finally {
    globalThis.clearTimeout(timeout);
  }
}

export function apiGet<T>(path: string, options: Omit<ApiRequestOptions, "method" | "body"> = {}): Promise<T> {
  return apiRequest<T>(path, { ...options, method: "GET" });
}

export function apiMutation<T>(
  path: string,
  method: Exclude<ApiRequestOptions["method"], undefined | "GET">,
  body?: unknown,
  options: Omit<ApiRequestOptions, "method" | "body"> = {},
): Promise<T> {
  return apiRequest<T>(path, { ...options, method, body });
}
