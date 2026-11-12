/**
 * Reglas de validación del lado cliente. Deben mantenerse idénticas a las del backend
 * (backend/app/Rules/*). El backend siempre revalida: estas reglas solo mejoran la experiencia.
 */

export const DOCUMENT_TYPES = [
  { value: "CC", label: "CC — Cédula de ciudadanía" },
  { value: "CE", label: "CE — Cédula de extranjería" },
  { value: "NIT", label: "NIT" },
  { value: "PP", label: "PP — Pasaporte" },
] as const;

export type DocumentType = (typeof DOCUMENT_TYPES)[number]["value"];

export const PHONE_PATTERN = /^3\d{9}$/;
const NAME_PATTERN = /^\p{L}[\p{L}\p{M} '.-]{1,99}$/u;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

const DOCUMENT_RULES: Record<DocumentType, { pattern: RegExp; message: string }> = {
  CC: { pattern: /^\d{6,10}$/, message: "La cédula de ciudadanía debe tener de 6 a 10 dígitos, solo números." },
  CE: { pattern: /^\d{6,10}$/, message: "La cédula de extranjería debe tener de 6 a 10 dígitos, solo números." },
  NIT: { pattern: /^\d{9,10}$/, message: "El NIT debe tener 9 o 10 dígitos, solo números y sin guion." },
  PP: { pattern: /^[A-Za-z0-9]{6,12}$/, message: "El pasaporte debe tener de 6 a 12 caracteres, solo letras y números." },
};

/** Deja solo dígitos y corta a 10 (teléfono). Evita letras y símbolos mientras se escribe o se pega. */
export function sanitizePhone(raw: string): string {
  return raw.replace(/\D/g, "").slice(0, 10);
}

/** Número de documento: solo dígitos (CC, CE, NIT) o alfanumérico (PP). */
export function sanitizeDocumentNumber(raw: string, type: string): string {
  const max = type === "PP" ? 12 : 10;
  const cleaned = type === "PP" ? raw.replace(/[^A-Za-z0-9]/g, "") : raw.replace(/\D/g, "");
  return cleaned.slice(0, max);
}

export function validatePhone(value: string): string | null {
  if (value === "") return "El teléfono es obligatorio.";
  if (!PHONE_PATTERN.test(value)) {
    return "El teléfono debe tener exactamente 10 dígitos, solo números y empezar por 3 (ejemplo: 3001234567).";
  }
  return null;
}

export function validateDocumentNumber(type: string, value: string): string | null {
  if (value === "") return "El número de documento es obligatorio.";
  const rule = DOCUMENT_RULES[type as DocumentType];
  if (!rule) return "Selecciona un tipo de documento de la lista.";
  return rule.pattern.test(value) ? null : rule.message;
}

export function validateName(value: string): string | null {
  const v = value.trim();
  if (v === "") return "El nombre es obligatorio.";
  if (!NAME_PATTERN.test(v)) {
    return "El nombre solo puede tener letras, espacios, apóstrofo, punto y guion (de 2 a 100 caracteres).";
  }
  return null;
}

export function validateEmail(value: string): string | null {
  const v = value.trim();
  if (v === "") return "El correo es obligatorio.";
  if (v.length > 254 || !EMAIL_PATTERN.test(v)) return "Escribe un correo válido (ejemplo: nombre@dominio.com).";
  return null;
}

export function validatePassword(value: string): string | null {
  if (value === "") return "La contraseña es obligatoria.";
  if (value.length < 8 || value.length > 72 || !/\p{L}/u.test(value) || !/\d/.test(value)) {
    return "La contraseña debe tener de 8 a 72 caracteres, con al menos una letra y un número.";
  }
  return null;
}

export function validateQuantity(value: number, max: number): string | null {
  if (!Number.isInteger(value) || value < 1) return "La cantidad debe ser un número entero de 1 o más.";
  if (value > max) return `Solo hay ${max} unidad${max === 1 ? "" : "es"} disponible${max === 1 ? "" : "s"}.`;
  return null;
}

/** Convierte lo escrito en un input de cantidad a un entero dentro de [1, max]. */
export function clampQuantity(raw: string | number, max: number): number {
  const n = typeof raw === "number" ? raw : Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n < 1) return 1;
  return Math.min(Math.floor(n), Math.max(1, max));
}

export type ContactValues = {
  name: string;
  phone: string;
  document_type: string;
  document_number: string;
};

export type FieldErrors = Partial<Record<string, string>>;

export function validateContact(values: ContactValues): FieldErrors {
  const errors: FieldErrors = {};
  const set = (key: string, message: string | null) => {
    if (message) errors[key] = message;
  };
  set("name", validateName(values.name));
  set("phone", validatePhone(values.phone));
  set("document_type", DOCUMENT_TYPES.some((t) => t.value === values.document_type) ? null : "Selecciona un tipo de documento de la lista.");
  set("document_number", validateDocumentNumber(values.document_type, values.document_number));
  return errors;
}

/** Algoritmo de Luhn para números de tarjeta. */
export function luhnValid(number: string): boolean {
  if (!/^\d{13,19}$/.test(number)) return false;
  let sum = 0;
  let alt = false;
  for (let i = number.length - 1; i >= 0; i -= 1) {
    let d = Number(number[i]);
    if (alt) {
      d *= 2;
      if (d > 9) d -= 9;
    }
    sum += d;
    alt = !alt;
  }
  return sum % 10 === 0;
}

export function sanitizeCardNumber(raw: string): string {
  return raw.replace(/\D/g, "").slice(0, 19);
}

export function formatCardNumber(digits: string): string {
  return digits.replace(/(.{4})/g, "$1 ").trim();
}

/** Escribe MM/AA automáticamente y solo acepta dígitos. */
export function formatExpiry(raw: string): string {
  const digits = raw.replace(/\D/g, "").slice(0, 4);
  return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
}

export function validateCardNumber(digits: string): string | null {
  if (digits === "") return "El número de tarjeta es obligatorio.";
  return luhnValid(digits) ? null : "El número de tarjeta no es válido (13 a 19 dígitos).";
}

export function validateExpiry(value: string, now: Date = new Date()): string | null {
  const m = /^(0[1-9]|1[0-2])\/(\d{2})$/.exec(value);
  if (!m) return "La fecha debe tener el formato MM/AA (ejemplo: 12/30).";
  const end = new Date(2000 + Number(m[2]), Number(m[1]), 0, 23, 59, 59);
  return end.getTime() < now.getTime() ? "La tarjeta está vencida." : null;
}

export function validateCvc(value: string): string | null {
  return /^\d{3,4}$/.test(value) ? null : "El CVC debe tener 3 o 4 dígitos.";
}
