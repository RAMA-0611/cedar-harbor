"use client";

import { pingPublicCatalog } from "@/lib/api/catalog";
import { ApiClientError } from "@/types/api";
import { useCallback, useEffect, useState } from "react";

type PingState = "loading" | "ok" | "down";

export default function TestsPage() {
  const [ping, setPing] = useState<PingState>("loading");

  const checkApi = useCallback(async () => {
    setPing("loading");
    try {
      const ok = await pingPublicCatalog();
      setPing(ok ? "ok" : "down");
    } catch (caught) {
      if (caught instanceof ApiClientError) {
        setPing("down");
        return;
      }
      setPing("down");
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    pingPublicCatalog()
      .then((ok) => {
        if (!cancelled) {
          setPing(ok ? "ok" : "down");
        }
      })
      .catch(() => {
        if (!cancelled) {
          setPing("down");
        }
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <section className="max-w-3xl">
      <h1 className="text-2xl font-semibold tracking-tight text-foreground">Pruebas</h1>
      <p className="mt-3 text-sm leading-6 text-muted">
        La suite automatizada se ejecuta desde el backend. Consulte la evidencia de validación del
        repositorio.
      </p>
      <div className="mt-6 space-y-4">
        <article className="rounded-2xl border border-border bg-surface p-5">
          <h2 className="text-sm font-medium text-muted">Backend PHPUnit</h2>
          <p className="mt-2 text-sm leading-6 text-foreground">
            Las pruebas se ejecutan con <code className="rounded bg-surface-2 px-1.5 py-0.5">php artisan test</code>{" "}
            sobre PostgreSQL <strong>rotor_testing</strong>, nunca sobre la base de desarrollo{" "}
            <strong>rotor</strong>. Este panel no lanza PHPUnit desde el navegador.
          </p>
        </article>
        <article className="rounded-2xl border border-border bg-surface p-5">
          <h2 className="text-sm font-medium text-muted">Evidencia versionada</h2>
          <p className="mt-2 text-sm leading-6 text-foreground">
            Baseline documentada en el repositorio: 156 tests, 1403 assertions, 0 failures, más la
            evidencia de MVP-1/MVP-2 en <code className="rounded bg-surface-2 px-1.5 py-0.5">docs/evidence/</code> y{" "}
            <code className="rounded bg-surface-2 px-1.5 py-0.5">docs/operations/TEST_DATABASE.md</code>.
          </p>
        </article>
        <article className="rounded-2xl border border-border bg-surface p-5">
          <h2 className="text-sm font-medium text-muted">Comprobación no destructiva</h2>
          <p className="mt-2 text-sm leading-6 text-foreground">
            Estado de <code className="rounded bg-surface-2 px-1.5 py-0.5">GET /api/products</code>:{" "}
            {ping === "loading" ? "comprobando…" : ping === "ok" ? "API disponible" : "API no disponible"}
          </p>
          <button
            type="button"
            onClick={() => {
              void checkApi();
            }}
            className="mt-4 inline-flex min-h-10 items-center rounded-lg border border-border px-3 text-sm"
          >
            Volver a comprobar
          </button>
        </article>
      </div>
    </section>
  );
}
