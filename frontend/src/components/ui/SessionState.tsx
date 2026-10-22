type SessionStateProps = {
  title: string;
  message: string;
  actionLabel?: string;
  onAction?: () => void;
};

export function SessionState({ title, message, actionLabel, onAction }: SessionStateProps) {
  return (
    <div className="flex min-h-dvh items-center justify-center bg-background px-6">
      <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8 shadow-xl">
        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Proyecto Rotor</p>
        <h1 className="mt-3 text-xl font-semibold text-foreground">{title}</h1>
        <p className="mt-2 text-sm leading-6 text-muted">{message}</p>
        {actionLabel && onAction ? (
          <button
            type="button"
            onClick={onAction}
            className="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-accent px-4 text-sm font-semibold text-background transition hover:opacity-90"
          >
            {actionLabel}
          </button>
        ) : null}
      </div>
    </div>
  );
}

export function SessionLoading() {
  return (
    <div className="flex min-h-dvh items-center justify-center bg-background px-6" role="status" aria-live="polite">
      <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8">
        <div className="h-3 w-28 animate-pulse rounded bg-surface-2" />
        <div className="mt-4 h-6 w-48 animate-pulse rounded bg-surface-2" />
        <div className="mt-3 h-4 w-full animate-pulse rounded bg-surface-2" />
        <p className="mt-6 text-sm text-muted">Comprobando sesión…</p>
      </div>
    </div>
  );
}
