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
