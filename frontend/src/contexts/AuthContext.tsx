"use client";

import { getCurrentUser, login as loginRequest, logout as logoutRequest } from "@/lib/api/auth";
import { onUnauthorized } from "@/lib/api/client";
import { ApiClientError } from "@/types/api";
import type { AuthenticatedUser } from "@/types/auth";
import { useRouter } from "next/navigation";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

type AuthContextValue = {
  user: AuthenticatedUser | null;
  loading: boolean;
  authenticated: boolean;
  error: string | null;
  login: (email: string, password: string) => Promise<AuthenticatedUser>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<AuthenticatedUser | null>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<AuthenticatedUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refreshUser = useCallback(async () => {
    try {
      const nextUser = await getCurrentUser();
      setUser(nextUser);
      setError(null);
      return nextUser;
    } catch (caught) {
      setUser(null);
      setError(
        caught instanceof ApiClientError
          ? caught.error.message
          : "No se pudo comprobar la sesión.",
      );
      throw caught;
    }
  }, []);

  useEffect(() => {
    let cancelled = false;

    async function bootstrap() {
      try {
        const nextUser = await getCurrentUser();
        if (!cancelled) {
          setUser(nextUser);
          setError(null);
        }
      } catch (caught) {
        if (!cancelled) {
          setUser(null);
          setError(
            caught instanceof ApiClientError
              ? caught.error.message
              : "No se pudo comprobar la sesión.",
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void bootstrap();
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    return onUnauthorized(() => {
      setUser(null);
      router.replace("/login");
    });
  }, [router]);

  const login = useCallback(async (email: string, password: string) => {
    const nextUser = await loginRequest(email, password);
    setUser(nextUser);
    setError(null);
    return nextUser;
  }, []);

  const logout = useCallback(async () => {
    try {
      await logoutRequest();
    } catch {
      // Local session is cleared even if the network call already expired.
    } finally {
      setUser(null);
      setError(null);
      router.replace("/login");
    }
  }, [router]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      authenticated: user !== null,
      error,
      login,
      logout,
      refreshUser,
    }),
    [user, loading, error, login, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within AuthProvider.");
  }
  return context;
}
