import Link from "next/link";

export default function NotFound() {
  return (
    <div className="flex min-h-dvh items-center justify-center bg-background px-6">
      <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8">
        <h1 className="text-xl font-semibold text-foreground">Página no encontrada</h1>
        <p className="mt-2 text-sm leading-6 text-muted">
          La ruta solicitada no existe en el panel de Proyecto Rotor.
        </p>
        <Link
          href="/"
          className="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-accent px-4 text-sm font-semibold text-background"
        >
          Ir al inicio
        </Link>
      </div>
    </div>
  );
}
