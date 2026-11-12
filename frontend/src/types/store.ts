export type CartItemView = {
  id: string;
  product_id: string;
  sku: string;
  name: string;
  quantity: number;
  unit_price: string;
  tax_amount: string;
  line_total: string;
  available_quantity: number;
};

export type CartView = {
  id: string;
  items: CartItemView[];
  subtotal: string;
  tax_total: string;
  total: string;
  currency: "COP";
  expires_at: string | null;
};

export type CheckoutPayload = {
  customer: {
    name: string;
    email: string;
    phone: string;
    document_type: string;
    document_number: string;
  };
  delivery: {
    mode: "PICKUP" | "COORDINATED_SHIPPING";
    address: string | null;
  };
};

export type StoreOrderItem = {
  id: string;
  product_id: string | null;
  sku: string;
  product_name: string;
  quantity: number;
  unit_price: string;
  tax_rate: string;
  tax_amount: string;
  line_subtotal: string;
  line_total: string;
};

export type StoreOrder = {
  id: string;
  order_number: string;
  status: string;
  currency: "COP";
  subtotal: string;
  tax_total: string;
  total: string;
  customer: CheckoutPayload["customer"];
  delivery: {
    mode: string;
    address: string | null;
  };
  items: StoreOrderItem[];
  created_at: string | null;
  updated_at: string | null;
};

export type PaymentIntent = {
  payment_id: string;
  provider: string;
  public_key: string;
  reference: string;
  amount_in_cents: number;
  currency: string;
  integrity_signature: string;
  checkout_url: string;
  redirect_url: string | null;
};

export type SimulatedOutcome = "APPROVED" | "DECLINED" | "INSUFFICIENT_FUNDS" | "ERROR" | "PENDING";

export type PaymentConfig = {
  provider: string;
  simulated: boolean;
  test_cards: { number: string; outcome: SimulatedOutcome; label: string }[];
};

export type SimulatedPaymentResult = {
  outcome: SimulatedOutcome;
  message: string;
  order_id: string;
  order_status: string;
  payment_status: string;
  transaction_id: string | null;
  card_last4: string | null;
  settlement_reason: string | null;
  can_retry: boolean;
};

export type SimulatedCardInput = {
  card_holder: string;
  card_number: string;
  expiry: string;
  cvc: string;
};
