import { apiGet, apiMutation } from "@/lib/api/client";
import { ApiClientError } from "@/types/api";
import type { AuthenticatedUser, AuthResponse } from "@/types/auth";

export async function login(email: string, password: string): Promise<AuthenticatedUser> {
  const payload = await apiMutation<AuthResponse>(
    "/api/auth/login",
    "POST",
    { email, password },
    { skipUnauthorizedHandler: true },
  );
  return payload.user;
}

export async function logout(): Promise<void> {
  await apiMutation<void>("/api/auth/logout", "POST", undefined, {
    skipUnauthorizedHandler: true,
  });
}

export async function getCurrentUser(): Promise<AuthenticatedUser | null> {
  try {
    const payload = await apiGet<AuthResponse>("/api/auth/me", {
      skipUnauthorizedHandler: true,
    });
    return payload.user;
  } catch (caught) {
    if (caught instanceof ApiClientError && caught.error.status === 401) {
      return null;
    }
    throw caught;
  }
}
