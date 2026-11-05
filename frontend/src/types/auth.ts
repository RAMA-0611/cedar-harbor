export type UserRole = "ADMIN" | "CUSTOMER";

export type AuthenticatedUser = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  customer_id: string | null;
};

export type AuthResponse = {
  user: AuthenticatedUser;
};

export type RegisterCustomerPayload = {
  name: string;
  email: string;
  phone: string;
  document_type: string;
  document_number: string;
  password: string;
  password_confirmation: string;
};
