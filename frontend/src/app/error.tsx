"use client";

export default function ErrorPage({ reset }: { reset: () => void }) {
  return (
    <div className="flex min-h-dvh items-center justify-center bg-background px-6">
      <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8">
        <h1 className="text-xl font-semibold text-foreground">Error inesperado</h1>
        <p className="mt-2 text-sm leading-6 text-muted">
          La interfaz no pudo continuar. Puede reintentar sin perder el trabajo ya persistido en el
          servidor.
        </p>
        <button
          type="button"
          onClick={reset}
          className="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-accent px-4 text-sm font-semibold text-background"
        >
          Reintentar
        </button>
      </div>
    </div>
  );
}
