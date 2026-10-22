"use client";

import { ConfirmDialog } from "@/components/admin/crud/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/admin/crud/DataTable";
import { EmptyState } from "@/components/admin/crud/EmptyState";
import { FeedbackMessage } from "@/components/admin/crud/FeedbackMessage";
import { LoadingTable } from "@/components/admin/crud/LoadingTable";
import { PageHeader } from "@/components/admin/crud/PageHeader";
import { StatusBadge } from "@/components/admin/crud/StatusBadge";
import { actionButtonClassName, inputClassName, labelClassName } from "@/components/admin/crud/fieldStyles";
import { OrderEditDialog } from "@/components/orders/OrderEditDialog";
import { OrderViewDialog } from "@/components/orders/OrderViewDialog";
import { mutationErrorMessage, orderCancelErrorMessage, orderDeleteErrorMessage } from "@/lib/api/errors";
import { cancelOrder, deleteOrder, listOrders } from "@/lib/api/orders";
import {
  formatCurrencyCOP,
  formatDateTime,
  formatDeliveryMode,
  formatOrderStatus,
  ORDER_STATUS_LABELS,
  orderStatusTone,
} from "@/lib/format";
import type { AdminOrder, OrderStatus } from "@/types/api";
import { useCallback, useEffect, useMemo, useState, type FormEvent } from "react";
import Link from "next/link";

const PER_PAGE = 20;

const STATUS_FILTERS: Array<{ value: "" | OrderStatus; label: string }> = [
  { value: "", label: "Todos los estados" },
  { value: "DRAFT", label: ORDER_STATUS_LABELS.DRAFT },
  { value: "PENDING_PAYMENT", label: ORDER_STATUS_LABELS.PENDING_PAYMENT },
  { value: "PAID", label: ORDER_STATUS_LABELS.PAID },
  { value: "FULFILLED", label: ORDER_STATUS_LABELS.FULFILLED },
  { value: "FAILED", label: ORDER_STATUS_LABELS.FAILED },
  { value: "EXPIRED", label: ORDER_STATUS_LABELS.EXPIRED },
  { value: "CANCELLED", label: ORDER_STATUS_LABELS.CANCELLED },
];

function canCancel(status: OrderStatus): boolean {
  return status === "DRAFT" || status === "PENDING_PAYMENT";
}

export function OrdersModule() {
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [perPage, setPerPage] = useState(PER_PAGE);
  const [status, setStatus] = useState<"" | OrderStatus>("");
  const [search, setSearch] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<{ kind: "success" | "error"; text: string } | null>(
    null,
  );
  const [viewingId, setViewingId] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [pendingCancel, setPendingCancel] = useState<AdminOrder | null>(null);
  const [pendingDelete, setPendingDelete] = useState<AdminOrder | null>(null);
  const [cancelling, setCancelling] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const filtersActive = status !== "" || search.trim() !== "";

  const loadOrders = useCallback(
    async (withLoading = false, pageOverride?: number) => {
      const requestedPage = pageOverride ?? page;
      if (withLoading) {
        setLoading(true);
      }
      setListError(null);
      try {
        const payload = await listOrders({
          page: requestedPage,
          per_page: PER_PAGE,
          status,
          search,
        });
        if (payload.data.length === 0 && payload.meta.total > 0 && requestedPage > 1) {
          setPage(requestedPage - 1);
          return;
        }
        setOrders(payload.data);
        setTotal(payload.meta.total);
        setPerPage(payload.meta.per_page);
        setPage(payload.meta.page);
      } catch (caught) {
        setOrders([]);
        setListError(mutationErrorMessage(caught, "No se pudieron cargar los pedidos."));
      } finally {
        setLoading(false);
      }
    },
    [page, search, status],
  );

  useEffect(() => {
    let cancelled = false;
    void listOrders({ page, per_page: PER_PAGE, status, search })
      .then((payload) => {
        if (cancelled) {
          return;
        }
        if (payload.data.length === 0 && payload.meta.total > 0 && page > 1) {
          setPage(page - 1);
          return;
        }
        setOrders(payload.data);
        setTotal(payload.meta.total);
        setPerPage(payload.meta.per_page);
        setListError(null);
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setOrders([]);
          setListError(mutationErrorMessage(caught, "No se pudieron cargar los pedidos."));
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
  }, [page, search, status]);

  function onSearchSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  }

  function clearFilters() {
    setSearchInput("");
    setSearch("");
    setStatus("");
    setPage(1);
  }

  async function onSaved(message: string) {
    setEditingId(null);
    setViewingId(null);
    setFeedback({ kind: "success", text: message });
    await loadOrders();
  }

  async function confirmCancel() {
    if (!pendingCancel || cancelling) {
      return;
    }
    const target = pendingCancel;
    setCancelling(true);
    try {
      await cancelOrder(target.id);
      setPendingCancel(null);
      setViewingId(null);
      setFeedback({ kind: "success", text: "Pedido cancelado correctamente." });
      await loadOrders();
    } catch (caught) {
      setFeedback({ kind: "error", text: orderCancelErrorMessage(caught) });
    } finally {
      setCancelling(false);
    }
  }

  async function confirmDelete() {
    if (!pendingDelete || deleting) {
      return;
    }
    const target = pendingDelete;
    setDeleting(true);
    try {
      await deleteOrder(target.id);
      setPendingDelete(null);
      setViewingId(null);
      setFeedback({ kind: "success", text: "Pedido eliminado correctamente." });
      await loadOrders();
    } catch (caught) {
      setFeedback({ kind: "error", text: orderDeleteErrorMessage(caught) });
    } finally {
      setDeleting(false);
    }
  }

  const columns = useMemo<DataTableColumn<AdminOrder>[]>(
    () => [
      {
        header: "Pedido",
        render: (row) => <span className="font-medium">{row.order_number}</span>,
      },
      {
        header: "Cliente",
        render: (row) => (
          <div>
            <p className="font-medium">{row.customer.name}</p>
            <p className="text-xs text-muted">{row.customer.email}</p>
          </div>
        ),
      },
      {
        header: "Estado",
        render: (row) => (
          <StatusBadge label={formatOrderStatus(row.status)} tone={orderStatusTone(row.status)} />
        ),
      },
      {
        header: "Total",
        render: (row) => formatCurrencyCOP(row.total),
      },
      {
        header: "Entrega",
        render: (row) => formatDeliveryMode(row.delivery.mode),
      },
      {
        header: "Fecha",
        render: (row) => formatDateTime(row.created_at),
      },
      {
        header: "Acciones",
        headerClassName: "text-right",
        cellClassName: "text-right",
        render: (row) => {
          const deleteEnabled = row.status === "CANCELLED";
          return (
            <div className="flex flex-wrap justify-end gap-2">
              <button
                type="button"
                className={actionButtonClassName}
                onClick={() => setViewingId(row.id)}
              >
                Ver
              </button>
              <button
                type="button"
                className={actionButtonClassName}
                onClick={() => setEditingId(row.id)}
              >
                Editar
              </button>
              {canCancel(row.status) ? (
                <button
                  type="button"
                  className={actionButtonClassName}
                  onClick={() => setPendingCancel(row)}
                >
                  Cancelar pedido
                </button>
              ) : null}
              <button
                type="button"
                className={actionButtonClassName}
                disabled={!deleteEnabled}
                title={
                  deleteEnabled ? undefined : "Solo los pedidos cancelados pueden eliminarse."
                }
                aria-label={
                  deleteEnabled
                    ? `Eliminar ${row.order_number}`
                    : "Eliminar. Solo los pedidos cancelados pueden eliminarse."
                }
                onClick={() => {
                  if (deleteEnabled) {
                    setPendingDelete(row);
                  }
                }}
              >
                Eliminar
              </button>
            </div>
          );
        },
      },
    ],
    [],
  );

  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = Math.min(page * perPage, total);
  const hasPrevious = page > 1;
  const hasNext = page * perPage < total;

  return (
    <section className="space-y-5">
      <PageHeader
        title="Pedidos"
        description="Consulta, actualización, cancelación y eliminación controlada de pedidos existentes. La creación ocurre en el flujo transaccional del cliente."
      />

      <aside className="rounded-2xl border border-border bg-surface px-5 py-4 text-sm leading-6 text-muted">
        <p className="font-medium text-foreground">Creación de pedidos</p>
        <p className="mt-1">
          Los pedidos nuevos se generan desde el flujo de compra del cliente. El panel administrativo
          gestiona consulta, actualización, cancelación y eliminación controlada.
        </p>
        <p className="mt-2">
          La creación de pedidos se realiza mediante el flujo transaccional del cliente: carrito →
          checkout → pedido.
        </p>
        <Link
          href="/documentacion"
          className="mt-3 inline-flex min-h-10 items-center text-sm font-medium text-accent hover:underline"
        >
          Ver documentación API
        </Link>
      </aside>

      {feedback ? <FeedbackMessage kind={feedback.kind}>{feedback.text}</FeedbackMessage> : null}

      <form
        onSubmit={onSearchSubmit}
        className="flex flex-col gap-3 rounded-2xl border border-border bg-surface p-4 sm:flex-row sm:flex-wrap sm:items-end"
      >
        <div className="min-w-0 flex-1">
          <label htmlFor="order-search" className={labelClassName}>
            Buscar pedido o cliente
          </label>
          <input
            id="order-search"
            name="search"
            type="search"
            maxLength={120}
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            className={inputClassName}
          />
        </div>
        <div className="sm:w-56">
          <label htmlFor="order-status-filter" className={labelClassName}>
            Estado
          </label>
          <select
            id="order-status-filter"
            name="status"
            value={status}
            onChange={(event) => {
              setStatus(event.target.value as "" | OrderStatus);
              setPage(1);
            }}
            className={inputClassName}
          >
            {STATUS_FILTERS.map((option) => (
              <option key={option.value || "all"} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
        <button
          type="submit"
          className="inline-flex min-h-11 items-center justify-center rounded-lg bg-accent px-4 text-sm font-semibold text-background hover:opacity-90"
        >
          Buscar
        </button>
        {filtersActive ? (
          <button
            type="button"
            className={`${actionButtonClassName} min-h-11 px-3 text-sm`}
            onClick={clearFilters}
          >
            Limpiar filtros
          </button>
        ) : null}
      </form>

      {loading ? (
        <LoadingTable columns={7} label="Cargando pedidos" />
      ) : listError ? (
        <div className="rounded-2xl border border-border bg-surface px-6 py-10 text-center">
          <p role="alert" className="text-sm text-danger">
            No se pudieron cargar los pedidos.
          </p>
          <button
            type="button"
            className={`${actionButtonClassName} mt-4 min-h-10 px-3 text-sm`}
            onClick={() => {
              void loadOrders(true);
            }}
          >
            Reintentar
          </button>
        </div>
      ) : total === 0 && !filtersActive ? (
        <EmptyState
          message="No hay pedidos registrados."
          action={
            <p className="text-sm text-muted">
              Los pedidos se crean desde el flujo transaccional del cliente.
            </p>
          }
        />
      ) : orders.length === 0 ? (
        <EmptyState
          message="No se encontraron pedidos con los filtros seleccionados."
          action={
            <button type="button" className={actionButtonClassName} onClick={clearFilters}>
              Limpiar filtros
            </button>
          }
        />
      ) : (
        <>
          <DataTable
            caption="Pedidos administrativos"
            columns={columns}
            rows={orders}
            rowKey={(row) => row.id}
          />
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted">
              Mostrando {from}–{to} de {total} pedidos
            </p>
            <div className="flex gap-2">
              <button
                type="button"
                className={actionButtonClassName}
                disabled={!hasPrevious}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                Anterior
              </button>
              <button
                type="button"
                className={actionButtonClassName}
                disabled={!hasNext}
                onClick={() => setPage((current) => current + 1)}
              >
                Siguiente
              </button>
            </div>
          </div>
        </>
      )}

      {viewingId ? <OrderViewDialog orderId={viewingId} onClose={() => setViewingId(null)} /> : null}
      {editingId ? (
        <OrderEditDialog
          orderId={editingId}
          onClose={() => setEditingId(null)}
          onSaved={(message) => {
            void onSaved(message);
          }}
        />
      ) : null}

      <ConfirmDialog
        open={pendingCancel !== null}
        title="Cancelar pedido"
        description={
          pendingCancel
            ? `¿Cancelar el pedido ${pendingCancel.order_number}? Esta acción liberará las reservas activas asociadas.`
            : "¿Cancelar este pedido?"
        }
        confirmLabel="Cancelar pedido"
        cancelLabel="Volver"
        busy={cancelling}
        busyLabel="Cancelando…"
        onClose={cancelling ? () => undefined : () => setPendingCancel(null)}
        onConfirm={() => {
          void confirmCancel();
        }}
      />

      <ConfirmDialog
        open={pendingDelete !== null}
        title="Eliminar pedido"
        description={
          pendingDelete
            ? `¿Eliminar definitivamente el pedido ${pendingDelete.order_number}? Solo es posible si no existen dependencias bloqueantes.`
            : "¿Eliminar este pedido?"
        }
        busy={deleting}
        onClose={deleting ? () => undefined : () => setPendingDelete(null)}
        onConfirm={() => {
          void confirmDelete();
        }}
      />
    </section>
  );
}
