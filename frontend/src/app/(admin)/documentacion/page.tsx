"use client";

import { getApiBaseUrl } from "@/lib/config";

export default function DocumentationPage() {
  const apiBaseUrl = getApiBaseUrl();
  const swaggerUrl = `${apiBaseUrl}/docs`;
  const yamlUrl = `${apiBaseUrl}/docs/openapi.yaml`;

  return (
    <section className="max-w-3xl">
      <h1 className="text-2xl font-semibold tracking-tight text-foreground">Documentación API</h1>
      <p className="mt-3 text-sm leading-6 text-muted">
        Documentación OpenAPI / Swagger servida por Laravel. El contrato no se duplica dentro de
        Next.js.
      </p>
      <div className="mt-6 flex flex-col gap-3 sm:flex-row">
        <a
          href={swaggerUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex min-h-11 items-center justify-center rounded-lg bg-accent px-4 text-sm font-semibold text-background"
        >
          Abrir Swagger
        </a>
        <a
          href={yamlUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 text-sm font-medium text-foreground"
        >
          Abrir OpenAPI YAML
        </a>
      </div>
    </section>
  );
}
