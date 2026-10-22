import type { ReactNode } from "react";

type EmptyStateProps = {
  message: string;
  action?: ReactNode;
};

export function EmptyState({ message, action }: EmptyStateProps) {
  return (
    <div className="rounded-2xl border border-border bg-surface px-6 py-12 text-center">
      <p className="text-sm text-muted">{message}</p>
      {action ? <div className="mt-4">{action}</div> : null}
    </div>
  );
}
