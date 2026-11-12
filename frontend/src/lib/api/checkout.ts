import { apiGet, apiMutation, apiRequest } from "@/lib/api/client";
import type {
  CheckoutPayload,
  PaymentConfig,
  PaymentIntent,
  SimulatedCardInput,
  SimulatedPaymentResult,
  StoreOrder,
} from "@/types/store";

export async function validateCheckout(payload: CheckoutPayload): Promise<unknown> {
  return apiMutation("/api/checkout/validate", "POST", payload);
}

export async function createOrder(
  payload: CheckoutPayload,
  idempotencyKey: string,
): Promise<StoreOrder> {
  return apiRequest<StoreOrder>("/api/orders", {
    method: "POST",
    body: payload,
    headers: { "Idempotency-Key": idempotencyKey },
  });
}

export async function getOrder(orderId: string): Promise<StoreOrder> {
  return apiRequest<StoreOrder>(`/api/orders/${orderId}`, {
    method: "GET",
    cache: "no-store",
  });
}

export async function createPaymentIntent(orderId: string): Promise<PaymentIntent> {
  return apiMutation<PaymentIntent>(`/api/orders/${orderId}/payment-intent`, "POST");
}

export async function reconcileOrder(
  orderId: string,
  transactionId?: string,
): Promise<{ settlement: { reason: string }; order: StoreOrder }> {
  return apiMutation(`/api/orders/${orderId}/reconcile`, "POST", {
    transaction_id: transactionId ?? null,
  });
}

export async function getPaymentConfig(): Promise<PaymentConfig> {
  return apiGet<PaymentConfig>("/api/payments/config", { cache: "no-store" });
}

export async function paySimulated(
  orderId: string,
  card: SimulatedCardInput,
): Promise<SimulatedPaymentResult> {
  return apiMutation<SimulatedPaymentResult>(`/api/orders/${orderId}/simulated-payment`, "POST", card);
}

export async function completeSimulated(
  orderId: string,
  decision: "APPROVED" | "DECLINED",
): Promise<SimulatedPaymentResult> {
  return apiMutation<SimulatedPaymentResult>(
    `/api/orders/${orderId}/simulated-payment/complete`,
    "POST",
    { decision },
  );
}
