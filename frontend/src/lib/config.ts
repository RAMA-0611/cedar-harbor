const DEFAULT_API_BASE_URL = "http://127.0.0.1:8000";

export function getApiBaseUrl(): string {
  // Sin definir -> desarrollo local (API en :8000). Definida pero vacía -> rutas relativas al mismo
  // origen (despliegue detrás del proxy), lo que permite una única imagen para cualquier dominio.
  const configured = process.env.NEXT_PUBLIC_API_BASE_URL;
  const value = configured === undefined ? DEFAULT_API_BASE_URL : configured.trim();
  return value.replace(/\/+$/, "");
}
