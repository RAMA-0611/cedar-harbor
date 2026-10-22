export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
export const SLUG_PATTERN_SOURCE = "^[a-z0-9]+(?:-[a-z0-9]+)*$";
export const SLUG_HELP = "Use minúsculas, números y guiones.";

export function slugifyName(value: string): string {
  return value
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 180);
}

export function isValidSlug(value: string): boolean {
  return value.length > 0 && value.length <= 180 && SLUG_PATTERN.test(value);
}
