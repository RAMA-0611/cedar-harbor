import { AdminShell } from "@/components/admin/AdminShell";
import { RequireAdmin } from "@/components/auth/RequireAdmin";
import type { ReactNode } from "react";

export default function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <RequireAdmin>
      <AdminShell>{children}</AdminShell>
    </RequireAdmin>
  );
}
