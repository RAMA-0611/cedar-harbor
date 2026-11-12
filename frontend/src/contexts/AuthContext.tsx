"use client";

import {
  getCurrentUser,
  login as loginRequest,
  logout as logoutRequest,
  registerCustomer as registerCustomerRequest,
} from "@/lib/api/auth";
import { onUnauthorized } from "@/lib/api/client";
import { ApiClientError } from "@/types/api";
import type { AuthenticatedUser, RegisterCustomerPayload } from "@/types/auth";
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
  register: (payload: RegisterCustomerPayload) => Promise<AuthenticatedUser>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<AuthenticatedUser | null>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

// Marca local de "este navegador inició sesión". Solo evita preguntar a la API cuando no hay sesión posible;
// la autoridad sigue siendo la cookie de sesión del servidor.
const SESSION_HINT_KEY = "rotor.session";

function hasSessionHint(): boolean {
  try {
    return globalThis.localStorage?.getItem(SESSION_HINT_KEY) === "1";
  } catch {
    return true; // sin acceso al almacenamiento: se pregunta a la API como antes
  }
}

function setSessionHint(active: boolean): void {
  try {
    if (active) {
      globalThis.localStorage?.setItem(SESSION_HINT_KEY, "1");
    } else {
      globalThis.localStorage?.removeItem(SESSION_HINT_KEY);
    }
  } catch {
    // almacenamiento no disponible: no afecta el inicio de sesión
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<AuthenticatedUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refreshUser = useCallback(async () => {
    try {
      const nextUser = await getCurrentUser();
      setUser(nextUser);
      setSessionHint(true);
      setError(null);
      return nextUser;
    } catch (caught) {
      setUser(null);
      setSessionHint(false);
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
      // Optimización: si este navegador no ha iniciado sesión no se pregunta a la API. Así un visitante anónimo
      // no genera una petición inútil ni un 401 en la consola. (La cookie XSRF-TOKEN no sirve como señal: la API
      // la entrega a cualquier visitante desde la primera respuesta.)
      if (!hasSessionHint()) {
        if (!cancelled) {
          setUser(null);
          setLoading(false);
        }
        return;
      }
      try {
        const nextUser = await getCurrentUser();
        setSessionHint(true);
        if (!cancelled) {
          setUser(nextUser);
          setError(null);
        }
      } catch (caught) {
        setSessionHint(false);
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
      setSessionHint(false);
      const path = globalThis.location?.pathname ?? "";
      const adminSurface =
        path.startsWith("/dashboard") ||
        path.startsWith("/usuarios") ||
        path.startsWith("/categorias") ||
        path.startsWith("/productos") ||
        path.startsWith("/pedidos") ||
        path.startsWith("/documentacion") ||
        path.startsWith("/pruebas") ||
        path === "/login";
      router.replace(adminSurface ? "/login" : "/cliente/login");
    });
  }, [router]);

  const login = useCallback(async (email: string, password: string) => {
    const nextUser = await loginRequest(email, password);
    setSessionHint(true);
    setUser(nextUser);
    setError(null);
    return nextUser;
  }, []);

  const register = useCallback(async (payload: RegisterCustomerPayload) => {
    const nextUser = await registerCustomerRequest(payload);
    setSessionHint(true);
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
      setSessionHint(false);
      setUser(null);
      setError(null);
      const path = globalThis.location?.pathname ?? "";
      const adminSurface =
        path.startsWith("/dashboard") ||
        path.startsWith("/usuarios") ||
        path.startsWith("/categorias") ||
        path.startsWith("/productos") ||
        path.startsWith("/pedidos") ||
        path.startsWith("/documentacion") ||
        path.startsWith("/pruebas") ||
        path === "/login";
      router.replace(adminSurface ? "/login" : "/");
    }
  }, [router]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      authenticated: user !== null,
      error,
      login,
      register,
      logout,
      refreshUser,
    }),
    [user, loading, error, login, register, logout, refreshUser],
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
