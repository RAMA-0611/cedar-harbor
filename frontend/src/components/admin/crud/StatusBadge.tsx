type StatusBadgeProps = {
  label: string;
  tone?: "ok" | "muted" | "accent";
};

export function StatusBadge({ label, tone = "muted" }: StatusBadgeProps) {
  const toneClass =
    tone === "ok"
      ? "border-ok/40 bg-ok/10 text-ok"
      : tone === "accent"
        ? "border-accent/40 bg-accent/10 text-accent"
        : "border-border bg-surface-2 text-muted";

  return (
    <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ${toneClass}`}>
      {label}
    </span>
  );
}
