export function uniqueStamp(): string {
  return `${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
}

export function uniqueEmail(prefix: string): string {
  return `${prefix}.${uniqueStamp()}@example.test`;
}
