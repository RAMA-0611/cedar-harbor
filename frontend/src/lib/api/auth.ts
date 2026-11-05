import { apiGet, apiMutation } from "@/lib/api/client";
import { ApiClientError } from "@/types/api";
import type { AuthenticatedUser, AuthResponse, RegisterCustomerPayload } from "@/types/auth";

export async function login(email: string, password: string): Promise<AuthenticatedUser> {
  const payload = await apiMutation<AuthResponse>(
    "/api/auth/login",
    "POST",
    { email, password },
    { skipUnauthorizedHandler: true },
  );
  return payload.user;
}

export async function registerCustomer(
  input: RegisterCustomerPayload,
): Promise<AuthenticatedUser> {
  const payload = await apiMutation<AuthResponse>(
    "/api/auth/register",
    "POST",
    {
      name: input.name,
      email: input.email,
      phone: input.phone,
      document_type: input.document_type,
      document_number: input.document_number,
      password: input.password,
      password_confirmation: input.password_confirmation,
    },
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
