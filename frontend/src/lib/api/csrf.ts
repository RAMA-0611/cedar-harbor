import { getApiBaseUrl } from "@/lib/config";
import { ApiClientError } from "@/types/api";

export function getXsrfToken(): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const cookies = document.cookie.split(";");
  for (const part of cookies) {
    const separator = part.indexOf("=");
    if (separator === -1) {
      continue;
    }
    const name = part.slice(0, separator).trim();
    if (name !== "XSRF-TOKEN") {
      continue;
    }
    return decodeURIComponent(part.slice(separator + 1));
  }

  return null;
}

export async function ensureCsrfCookie(): Promise<void> {
  const response = await fetch(`${getApiBaseUrl()}/sanctum/csrf-cookie`, {
    method: "GET",
    credentials: "include",
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new ApiClientError({
      code: "CSRF_UNAVAILABLE",
      message: "No se pudo inicializar la protección CSRF.",
      details: {},
      status: response.status,
    });
  }
}
