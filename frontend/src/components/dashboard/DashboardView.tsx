"use client";

import {
  fetchAdminCategoriesCount,
  fetchAdminOrdersTotal,
  fetchAdminProductsCount,
  fetchAdminUsersCount,
} from "@/lib/api/admin";
import { ApiClientError } from "@/types/api";
import { useCallback, useEffect, useState } from "react";

type CardKey = "users" | "products" | "categories" | "orders";

type CardState = {
  loading: boolean;
  value: number | null;
  error: string | null;
};

const INITIAL: Record<CardKey, CardState> = {
  users: { loading: true, value: null, error: null },
  products: { loading: true, value: null, error: null },
  categories: { loading: true, value: null, error: null },
  orders: { loading: true, value: null, error: null },
};

const LOADERS: Record<CardKey, () => Promise<number>> = {
  users: fetchAdminUsersCount,
  products: fetchAdminProductsCount,
  categories: fetchAdminCategoriesCount,
  orders: fetchAdminOrdersTotal,
};

const LABELS: Record<CardKey, string> = {
  users: "Usuarios",
  products: "Productos",
  categories: "Categorías",
  orders: "Pedidos",
};

function errorMessage(caught: unknown): string {
  if (caught instanceof ApiClientError) {
    if (caught.error.status === 403) {
      return "No autorizado para consultar este recurso.";
    }
    return "No se pudo cargar";
  }
  return "No se pudo cargar";
}

export function DashboardView() {
  const [cards, setCards] = useState(INITIAL);

  const loadCard = useCallback(async (key: CardKey) => {
    setCards((current) => ({
      ...current,
      [key]: { loading: true, value: current[key].value, error: null },
    }));
    try {
      const value = await LOADERS[key]();
      setCards((current) => ({
        ...current,
        [key]: { loading: false, value, error: null },
      }));
    } catch (caught) {
      setCards((current) => ({
        ...current,
        [key]: { loading: false, value: null, error: errorMessage(caught) },
      }));
    }
  }, []);

  useEffect(() => {
    void loadCard("users");
    void loadCard("products");
    void loadCard("categories");
    void loadCard("orders");
  }, [loadCard]);

  return (
    <section>
      <h1 className="text-2xl font-semibold tracking-tight text-foreground">Dashboard</h1>
      <p className="mt-2 max-w-2xl text-sm text-muted">
        Totales leídos en tiempo real desde la API Laravel y PostgreSQL. No se usan datos
        simulados.
      </p>
      <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {(Object.keys(LABELS) as CardKey[]).map((key) => {
          const card = cards[key];
          return (
            <article key={key} className="rounded-2xl border border-border bg-surface p-5">
              <h2 className="text-sm font-medium text-muted">{LABELS[key]}</h2>
              {card.loading ? (
                <div className="mt-4 h-10 w-16 animate-pulse rounded bg-surface-2" aria-hidden="true" />
              ) : card.error ? (
                <div className="mt-4">
                  <p role="alert" className="text-sm text-danger">
                    {card.error}
                  </p>
                  <button
                    type="button"
                    onClick={() => {
                      void loadCard(key);
                    }}
                    className="mt-3 inline-flex min-h-10 items-center rounded-lg border border-border px-3 text-sm"
                  >
                    Reintentar
                  </button>
                </div>
              ) : (
                <p className="mt-3 text-3xl font-semibold tabular-nums text-foreground">{card.value}</p>
              )}
            </article>
          );
        })}
      </div>
    </section>
  );
}
