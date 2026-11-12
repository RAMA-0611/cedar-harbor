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
          <Link href="/" className="store-brand">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/logo-byteblade.png" alt="ByteBlade" width={150} height={41} className="store-brand-logo" />
            <span className="store-brand-name">Proyecto Rotor</span>
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
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src="/logo-byteblade-blanco.png" alt="" width={110} height={30} className="store-footer-logo" />
        <p>Proyecto Rotor · Demo académica · Sandbox · Sin dinero real</p>
      </footer>
    </div>
  );
}
