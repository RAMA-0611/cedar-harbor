import { ApiClientError } from "@/types/api";

export function fieldErrorsFromDetails(details: Record<string, unknown>): Record<string, string> {
  const raw = details.errors;
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return {};
  }

  const mapped: Record<string, string> = {};
  for (const [field, value] of Object.entries(raw as Record<string, unknown>)) {
    if (Array.isArray(value)) {
      const first = value.find((item): item is string => typeof item === "string");
      if (first) {
        mapped[field] = friendlyFieldMessage(field, first);
      }
      continue;
    }
    if (typeof value === "string") {
      mapped[field] = friendlyFieldMessage(field, value);
    }
  }
  return mapped;
}

function friendlyFieldMessage(field: string, message: string): string {
  const taken = /taken|unique|already been taken/i.test(message);
  if (field === "email" && taken) {
    return "Este correo electrónico ya está registrado.";
  }
  if (field === "slug" && taken) {
    return "Este slug ya está en uso.";
  }
  return message;
}

export function mutationErrorMessage(caught: unknown, fallback: string): string {
  if (!(caught instanceof ApiClientError)) {
    return fallback;
  }

  const { status, code, message, details } = caught.error;

  if (status === 403) {
    return "No tiene permisos para realizar esta acción.";
  }
  if (status === 404) {
    return "El registro no fue encontrado.";
  }
  if (status === 409 || code === "RESOURCE_IN_USE") {
    return "No se puede completar la operación porque el recurso está en uso.";
  }
  if (status === 422) {
    const fields = fieldErrorsFromDetails(details);
    const first = Object.values(fields)[0];
    return first ?? "Revise los datos ingresados.";
  }
  if (status === 0 || status >= 500) {
    return message;
  }

  return message || fallback;
}

export function userDeleteErrorMessage(caught: unknown, isSelf: boolean): string {
  if (isSelf) {
    return "No puede eliminar su propia cuenta.";
  }
  if (caught instanceof ApiClientError && caught.error.status === 403) {
    return "No puede eliminar su propia cuenta.";
  }
  return mutationErrorMessage(caught, "No se pudo eliminar el usuario.");
}

export function categoryDeleteErrorMessage(caught: unknown): string {
  if (
    caught instanceof ApiClientError &&
    (caught.error.status === 409 || caught.error.code === "RESOURCE_IN_USE")
  ) {
    return "No se puede eliminar esta categoría porque está siendo utilizada por productos.";
  }
  return mutationErrorMessage(caught, "No se pudo eliminar la categoría.");
}
