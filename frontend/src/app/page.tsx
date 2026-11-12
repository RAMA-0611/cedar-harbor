"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { listCatalogProducts } from "@/lib/api/catalog";
import { formatCop, handleImageError, resolveProductImage } from "@/lib/productImage";
import type { CatalogProduct } from "@/types/catalog";
import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";

type SortKey = "name-asc" | "name-desc" | "price-asc" | "price-desc";

export default function PublicCatalogPage() {
  const [products, setProducts] = useState<CatalogProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [query, setQuery] = useState("");
  const [category, setCategory] = useState("all");
  const [availability, setAvailability] = useState<"all" | "in" | "out">("all");
  const [sort, setSort] = useState<SortKey>("name-asc");

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const perPage = 100;
      const first = await listCatalogProducts({ page: 1, perPage });
      const all = [...first.data];
      const pages = Math.min(Math.ceil((first.meta?.total ?? all.length) / perPage), 20);
      for (let page = 2; page <= pages; page += 1) {
        const next = await listCatalogProducts({ page, perPage });
        all.push(...next.data);
      }
      setProducts(all);
    } catch {
      setError("No se pudo cargar el catálogo. Verifique que la API esté en ejecución.");
      setProducts([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const handle = globalThis.setTimeout(() => {
      void load();
    }, 0);
    return () => globalThis.clearTimeout(handle);
  }, [load]);

  useEffect(() => {
    const onFocus = () => {
      void load();
    };
    window.addEventListener("focus", onFocus);
    return () => window.removeEventListener("focus", onFocus);
  }, [load]);

  const categories = useMemo(() => {
    const map = new Map<string, string>();
    for (const product of products) {
      if (product.category) {
        map.set(product.category.id, product.category.name);
      }
    }
    return [...map.entries()].sort((a, b) => a[1].localeCompare(b[1], "es"));
  }, [products]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let rows = products.filter((product) => {
      if (q !== "") {
        const hay = `${product.name} ${product.sku}`.toLowerCase();
        if (!hay.includes(q)) {
          return false;
        }
      }
      if (category !== "all" && product.category?.id !== category) {
        return false;
      }
      if (availability === "in" && product.available_quantity < 1) {
        return false;
      }
      if (availability === "out" && product.available_quantity > 0) {
        return false;
      }
      return true;
    });

    rows = [...rows].sort((a, b) => {
      switch (sort) {
        case "name-desc":
          return b.name.localeCompare(a.name, "es");
        case "price-asc":
          return Number(a.unit_price) - Number(b.unit_price);
        case "price-desc":
          return Number(b.unit_price) - Number(a.unit_price);
        default:
          return a.name.localeCompare(b.name, "es");
      }
    });

    return rows;
  }, [availability, category, products, query, sort]);

  return (
    <StorefrontShell>
      <section className="store-hero">
        <h1>Catálogo industrial</h1>
        <p>Inventario real desde PostgreSQL · sincronizado con el panel ADMIN.</p>
      </section>

      <div className="store-toolbar" aria-label="Filtros del catálogo">
        <input
          aria-label="Buscar por nombre o SKU"
          placeholder="Buscar nombre o SKU"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
        />
        <select
          aria-label="Filtrar por categoría"
          value={category}
          onChange={(event) => setCategory(event.target.value)}
        >
          <option value="all">Todas las categorías</option>
          {categories.map(([id, name]) => (
            <option key={id} value={id}>
              {name}
            </option>
          ))}
        </select>
        <select
          aria-label="Filtrar por disponibilidad"
          value={availability}
          onChange={(event) => setAvailability(event.target.value as "all" | "in" | "out")}
        >
          <option value="all">Toda disponibilidad</option>
          <option value="in">Con stock</option>
          <option value="out">Sin stock</option>
        </select>
        <select
          aria-label="Ordenar"
          value={sort}
          onChange={(event) => setSort(event.target.value as SortKey)}
        >
          <option value="name-asc">Nombre A-Z</option>
          <option value="name-desc">Nombre Z-A</option>
          <option value="price-asc">Precio menor</option>
          <option value="price-desc">Precio mayor</option>
        </select>
        <button type="button" className="store-btn store-btn-secondary" onClick={() => void load()}>
          Actualizar
        </button>
      </div>

      {error ? <div className="store-alert store-alert-error">{error}</div> : null}
      {loading ? <p className="store-meta">Cargando catálogo…</p> : null}

      {!loading && filtered.length === 0 ? (
        <div className="store-panel">
          <p>No hay productos que coincidan con los filtros.</p>
        </div>
      ) : null}

      <div className="store-grid">
        {filtered.map((product) => (
          <article key={product.id} className="store-card">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={resolveProductImage(product)} alt={product.name} onError={handleImageError} />
            <div className="store-card-body">
              <p className="store-meta">{product.category?.name ?? "Sin categoría"}</p>
              <h2>{product.name}</h2>
              <p className="store-meta">SKU {product.sku}</p>
              <p className="store-price">{formatCop(product.unit_price)}</p>
              <p className="store-meta">
                Disponible: {product.available_quantity}
                {product.unit ? ` ${product.unit}` : ""}
              </p>
              <Link className="store-btn" href={`/producto/${product.id}`}>
                Ver detalle
              </Link>
            </div>
          </article>
        ))}
      </div>
    </StorefrontShell>
  );
}
