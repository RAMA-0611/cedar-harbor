"use client";

import { Modal } from "@/components/admin/crud/Modal";
import { StatusBadge } from "@/components/admin/crud/StatusBadge";
import { mutationErrorMessage } from "@/lib/api/errors";
import { getOrder } from "@/lib/api/orders";
import {
  formatCurrencyCOP,
  formatDateTime,
  formatDeliveryMode,
  formatOrderStatus,
  orderStatusTone,
} from "@/lib/format";
import type { AdminOrder } from "@/types/api";
import { useEffect, useState } from "react";

type OrderViewDialogProps = {
  orderId: string;
  onClose: () => void;
};

export function OrderViewDialog({ orderId, onClose }: OrderViewDialogProps) {
  const [order, setOrder] = useState<AdminOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    void getOrder(orderId)
      .then((record) => {
        if (!cancelled) {
          setOrder(record);
          setError(null);
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setError(mutationErrorMessage(caught, "No se pudo consultar el pedido."));
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [orderId]);

  const items = order?.items ?? [];

  return (
    <Modal
      open
      title="Detalle de pedido"
      description="Consulta individual desde GET /api/admin/orders/{id}."
      onClose={onClose}
    >
      {loading ? (
        <p className="text-sm text-muted">Cargando pedido…</p>
      ) : error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : order ? (
        <div className="space-y-6">
          <dl className="space-y-3 text-sm">
            <ViewField label="Número de pedido" value={order.order_number} />
            <div>
              <dt className="text-muted">Estado</dt>
              <dd className="mt-1">
                <StatusBadge
                  label={formatOrderStatus(order.status)}
                  tone={orderStatusTone(order.status)}
                />
              </dd>
            </div>
          </dl>

          <section>
            <h3 className="text-sm font-semibold text-foreground">Cliente</h3>
            <dl className="mt-2 space-y-3 text-sm">
              <ViewField label="Nombre" value={order.customer.name} />
              <ViewField label="Correo" value={order.customer.email} />
              <ViewField label="Teléfono" value={order.customer.phone} />
              <ViewField label="Tipo documento" value={order.customer.document_type} />
              <ViewField label="Número documento" value={order.customer.document_number} />
            </dl>
          </section>

          <section>
            <h3 className="text-sm font-semibold text-foreground">Entrega</h3>
            <dl className="mt-2 space-y-3 text-sm">
              <ViewField label="Modo" value={formatDeliveryMode(order.delivery.mode)} />
              <ViewField
                label="Dirección"
                value={order.delivery.address?.trim() ? order.delivery.address : "—"}
              />
            </dl>
          </section>

          <section>
            <h3 className="text-sm font-semibold text-foreground">Totales</h3>
            <dl className="mt-2 space-y-3 text-sm">
              <ViewField label="Subtotal" value={formatCurrencyCOP(order.subtotal)} />
              <ViewField label="Impuestos" value={formatCurrencyCOP(order.tax_total)} />
              <ViewField label="Total" value={formatCurrencyCOP(order.total)} />
              <ViewField label="Moneda" value={order.currency} />
            </dl>
          </section>

          <section>
            <h3 className="text-sm font-semibold text-foreground">Fechas</h3>
            <dl className="mt-2 space-y-3 text-sm">
              <ViewField label="Creado" value={formatDateTime(order.created_at)} />
              <ViewField label="Actualizado" value={formatDateTime(order.updated_at)} />
              {order.cancelled_at ? (
                <ViewField label="Cancelado" value={formatDateTime(order.cancelled_at)} />
              ) : null}
            </dl>
          </section>

          <section>
            <h3 className="text-sm font-semibold text-foreground">Ítems</h3>
            {items.length === 0 ? (
              <p className="mt-2 text-sm text-muted">Este pedido no tiene ítems.</p>
            ) : (
              <div className="mt-2 overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[36rem] border-collapse text-left text-sm">
                  <caption className="sr-only">Ítems del pedido {order.order_number}</caption>
                  <thead className="border-b border-border bg-surface-2">
                    <tr>
                      <th scope="col" className="px-3 py-2 font-medium text-muted">
                        SKU
                      </th>
                      <th scope="col" className="px-3 py-2 font-medium text-muted">
                        Producto
                      </th>
                      <th scope="col" className="px-3 py-2 font-medium text-muted">
                        Cantidad
                      </th>
                      <th scope="col" className="px-3 py-2 font-medium text-muted">
                        Precio unitario
                      </th>
                      <th scope="col" className="px-3 py-2 font-medium text-muted">
                        Impuesto
                      </th>
                      <th scope="col" className="px-3 py-2 font-medium text-muted">
                        Total línea
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((item) => (
                      <tr key={item.id} className="border-b border-border last:border-0">
                        <td className="px-3 py-2 font-mono text-xs">{item.sku}</td>
                        <td className="px-3 py-2">{item.product_name}</td>
                        <td className="px-3 py-2">{item.quantity}</td>
                        <td className="px-3 py-2">{formatCurrencyCOP(item.unit_price)}</td>
                        <td className="px-3 py-2">{formatCurrencyCOP(item.tax_amount)}</td>
                        <td className="px-3 py-2">{formatCurrencyCOP(item.line_total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </div>
      ) : null}
      <div className="mt-6 flex justify-end">
        <button
          type="button"
          onClick={onClose}
          className="inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 text-sm font-medium text-foreground hover:bg-surface-2"
        >
          Cerrar
        </button>
      </div>
    </Modal>
  );
}

function ViewField({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-muted">{label}</dt>
      <dd className="mt-1 break-all text-foreground">{value}</dd>
    </div>
  );
}
