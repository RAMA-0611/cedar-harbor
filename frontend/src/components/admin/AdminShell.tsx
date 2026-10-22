"use client";

import { ADMIN_NAV } from "@/components/admin/nav";
import { useAuth } from "@/hooks/useAuth";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useId, useState } from "react";

export function AdminShell({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth();
  const pathname = usePathname();
  const [menuOpen, setMenuOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const titleId = useId();

  useEffect(() => {
    if (!menuOpen) {
      return;
    }
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        setMenuOpen(false);
      }
    }
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [menuOpen]);

  async function onLogout() {
    setLoggingOut(true);
    try {
      await logout();
    } finally {
      setLoggingOut(false);
    }
  }

  const nav = (
    <nav aria-label="Administración" className="flex flex-1 flex-col gap-1">
      {ADMIN_NAV.map((item) => {
        const current = pathname === item.href;
        return (
          <Link
            key={item.href}
            href={item.href}
            aria-current={current ? "page" : undefined}
            onClick={() => setMenuOpen(false)}
            className={`rounded-lg px-3 py-2 text-sm font-medium transition ${
              current
                ? "bg-surface-2 text-foreground"
                : "text-muted hover:bg-surface-2 hover:text-foreground"
            }`}
          >
            {item.label}
          </Link>
        );
      })}
    </nav>
  );

  const account = user ? (
    <div className="border-t border-border pt-4">
      <p className="truncate text-sm font-medium text-foreground">{user.name}</p>
      <p className="truncate text-xs text-muted">{user.role}</p>
      <button
        type="button"
        onClick={() => {
          void onLogout();
        }}
        disabled={loggingOut}
        className="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-border px-3 text-sm font-medium text-foreground hover:bg-surface-2 disabled:opacity-60"
      >
        {loggingOut ? "Cerrando sesión…" : "Cerrar sesión"}
      </button>
    </div>
  ) : null;

  return (
    <div className="min-h-dvh bg-background">
      <a
        href="#contenido-principal"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-accent focus:px-3 focus:py-2 focus:text-background"
      >
        Saltar al contenido
      </a>

      {menuOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <button
            type="button"
            className="absolute inset-0 bg-black/60"
            aria-label="Cerrar menú"
            onClick={() => setMenuOpen(false)}
          />
          <aside
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            id="menu-movil"
            className="relative flex h-full w-72 max-w-[85vw] flex-col border-r border-border bg-surface p-5"
          >
            <div className="mb-6 flex items-center justify-between">
              <p id={titleId} className="text-sm font-semibold tracking-wide text-foreground">
                Proyecto Rotor
              </p>
              <button
                type="button"
                className="rounded-md px-2 py-1 text-sm text-muted hover:text-foreground"
                onClick={() => setMenuOpen(false)}
              >
                Cerrar
              </button>
            </div>
            {nav}
            {account}
          </aside>
        </div>
      ) : null}

      <div className="lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside className="hidden min-h-dvh flex-col border-r border-border bg-surface p-5 lg:flex">
          <p className="mb-1 text-xs font-semibold uppercase tracking-[0.2em] text-accent">ByteBlade</p>
          <p className="mb-8 text-lg font-semibold text-foreground">Proyecto Rotor</p>
          {nav}
          {account}
        </aside>

        <div className="min-w-0">
          <header className="sticky top-0 z-30 flex min-h-14 items-center justify-between border-b border-border bg-background/95 px-4 backdrop-blur lg:px-8">
            <button
              type="button"
              className="inline-flex min-h-10 items-center rounded-lg border border-border px-3 text-sm lg:hidden"
              aria-expanded={menuOpen}
              aria-controls="menu-movil"
              onClick={() => setMenuOpen(true)}
            >
              Menú
            </button>
            <p className="hidden text-sm text-muted lg:block">Panel administrativo</p>
            <p className="text-sm font-medium text-foreground lg:hidden">Proyecto Rotor</p>
          </header>
          <main id="contenido-principal" className="px-4 py-6 lg:px-8 lg:py-8">
            {children}
          </main>
        </div>
      </div>
    </div>
  );
}
