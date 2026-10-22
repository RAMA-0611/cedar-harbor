"use client";

import { SessionLoading, SessionState } from "@/components/ui/SessionState";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

export default function HomePage() {
  const { user, loading, error, refreshUser } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) {
      return;
    }
    if (error && !user) {
      return;
    }
    if (!user) {
      router.replace("/login");
      return;
    }
    router.replace(user.role === "ADMIN" ? "/dashboard" : "/acceso-denegado");
  }, [error, loading, router, user]);

  if (error && !user && !loading) {
    return (
      <SessionState
        title="No se pudo comprobar la sesión"
        message="No se muestra el panel hasta confirmar el estado de autenticación."
        actionLabel="Reintentar"
        onAction={() => {
          void refreshUser().catch(() => undefined);
        }}
      />
    );
  }

  return <SessionLoading />;
}
