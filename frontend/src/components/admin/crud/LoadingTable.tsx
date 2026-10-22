type LoadingTableProps = {
  columns: number;
  rows?: number;
  label?: string;
};

export function LoadingTable({ columns, rows = 6, label = "Cargando" }: LoadingTableProps) {
  return (
    <div
      className="overflow-hidden rounded-2xl border border-border bg-surface"
      role="status"
      aria-live="polite"
      aria-label={label}
    >
      <div className="border-b border-border bg-surface-2 px-4 py-3">
        <div className="h-4 w-40 animate-pulse rounded bg-border" />
      </div>
      <ul className="divide-y divide-border">
        {Array.from({ length: rows }).map((_, rowIndex) => (
          <li
            key={rowIndex}
            className="grid gap-4 px-4 py-4"
            style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}
          >
            {Array.from({ length: columns }).map((__, cellIndex) => (
              <div key={cellIndex} className="h-4 animate-pulse rounded bg-surface-2" />
            ))}
          </li>
        ))}
      </ul>
    </div>
  );
}
