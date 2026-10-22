import type { AdminOrderDelivery, OrderStatus } from "@/types/api";

const copFormatter = new Intl.NumberFormat("es-CO", {
  style: "currency",
  currency: "COP",
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

export function formatCurrencyCOP(value: string): string {
  const amount = Number(value);
  if (!Number.isFinite(amount)) {
    return "—";
  }
  return copFormatter.format(amount);
}

export function taxRateToPercentInput(rate: string): string {
  const n = Number(rate);
  if (!Number.isFinite(n)) {
    return "";
  }
  return String(Math.round(n * 10000) / 100);
}

export function formatTaxPercent(rate: string): string {
  const percent = taxRateToPercentInput(rate);
  return percent === "" ? "—" : `${percent} %`;
}

export function percentInputToTaxRate(percent: string): string {
  const n = Number(percent);
  if (!Number.isFinite(n)) {
    return "";
  }
  return (Math.round(n * 100) / 10000).toFixed(4);
}

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
  DRAFT: "Borrador",
  PENDING_PAYMENT: "Pendiente de pago",
  PAID: "Pagado",
  FULFILLED: "Completado",
  FAILED: "Fallido",
  EXPIRED: "Expirado",
  CANCELLED: "Cancelado",
};

export function formatOrderStatus(status: OrderStatus): string {
  return ORDER_STATUS_LABELS[status] ?? status;
}

export function orderStatusTone(status: OrderStatus): "ok" | "muted" | "accent" {
  if (status === "PAID" || status === "FULFILLED") {
    return "ok";
  }
  if (status === "PENDING_PAYMENT" || status === "DRAFT") {
    return "accent";
  }
  return "muted";
}

export function formatDeliveryMode(mode: AdminOrderDelivery["mode"]): string {
  return mode === "PICKUP" ? "Recogida" : "Envío coordinado";
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return new Intl.DateTimeFormat("es-CO", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}
