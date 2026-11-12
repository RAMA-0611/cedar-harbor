import { apiGet, apiMutation } from "@/lib/api/client";
import type { CartView } from "@/types/store";

export async function getCart(): Promise<CartView> {
  return apiGet<CartView>("/api/cart", { cache: "no-store" });
}

export async function addCartItem(productId: string, quantity: number): Promise<CartView> {
  return apiMutation<CartView>("/api/cart/items", "POST", {
    product_id: productId,
    quantity,
  });
}

export async function updateCartItem(cartItemId: string, quantity: number): Promise<CartView> {
  return apiMutation<CartView>(`/api/cart/items/${cartItemId}`, "PATCH", { quantity });
}

export async function removeCartItem(cartItemId: string): Promise<void> {
  await apiMutation<unknown>(`/api/cart/items/${cartItemId}`, "DELETE");
}
