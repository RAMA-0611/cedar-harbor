"use client";

import { useAuth } from "@/hooks/useAuth";
import Link from "next/link";
import type { ReactNode } from "react";
import "@/components/store/storefront.css";

export function StorefrontShell({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();

  return (
    <div className="store-root">
      <header className="store-header">
        <div className="store-header-inner">
          <Link href="/" className="store-brand" aria-label="Proyecto Rotor tienda">
            <span className="store-brand-mark">ByteBlade</span>
            <strong>Proyecto Rotor</strong>
          </Link>
          <nav className="store-nav" aria-label="Navegación tienda">
            <Link href="/">Catálogo</Link>
            <Link href="/carrito">Carrito</Link>
            {user?.role === "CUSTOMER" ? (
              <>
                <span className="store-nav-user">{user.email}</span>
                <button type="button" className="store-link-btn" onClick={() => void logout()}>
                  Salir
                </button>
              </>
            ) : (
              <Link href="/cliente/login">Iniciar sesión</Link>
            )}
            <Link href="/login" className="store-nav-admin">
              Admin
            </Link>
          </nav>
        </div>
      </header>
      <main className="store-main">{children}</main>
      <footer className="store-footer">
        <p>Proyecto Rotor · Demo académica · Sandbox · Sin dinero real</p>
      </footer>
    </div>
  );
}
