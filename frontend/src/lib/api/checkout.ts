import { apiMutation, apiRequest } from "@/lib/api/client";
import type { CheckoutPayload, PaymentIntent, StoreOrder } from "@/types/store";

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
