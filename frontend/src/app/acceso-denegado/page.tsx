"use client";

import { SessionLoading, SessionState } from "@/components/ui/SessionState";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

export default function AccessDeniedPage() {
  const { user, loading, logout } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) {
      return;
    }
    if (!user) {
      router.replace("/login");
      return;
    }
    if (user.role === "ADMIN") {
      router.replace("/dashboard");
    }
  }, [loading, router, user]);

  if (loading || !user || user.role === "ADMIN") {
    return <SessionLoading />;
  }

  return (
    <SessionState
      title="Acceso denegado"
      message="Esta cuenta no tiene permisos para acceder al panel administrativo."
      actionLabel="Cerrar sesión"
      onAction={() => {
        void logout();
      }}
    />
  );
}
