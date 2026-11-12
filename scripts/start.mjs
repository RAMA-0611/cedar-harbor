// Arranca la API (puerto 8000) y la tienda (puerto 3000). Ctrl+C detiene ambos.
// Uso (desde la raíz del proyecto):  node scripts/start.mjs
import { spawn, spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const backend = path.join(root, "backend");
const frontend = path.join(root, "frontend");
const isWin = process.platform === "win32";

if (!fs.existsSync(path.join(backend, ".env"))) {
  console.error("Falta backend/.env. Ejecute primero:  node scripts/setup.mjs");
  process.exit(1);
}
if (!fs.existsSync(path.join(frontend, "node_modules"))) {
  console.error("Faltan las dependencias del frontend. Ejecute primero:  node scripts/setup.mjs");
  process.exit(1);
}

function start(cmd, args, cwd, env = {}) {
  const options = { cwd, stdio: "inherit", env: { ...process.env, ...env }, detached: !isWin };
  return isWin ? spawn([cmd, ...args].join(" "), { ...options, shell: true }) : spawn(cmd, args, options);
}
function stop(child) {
  if (!child || child.exitCode !== null) return;
  try {
    if (isWin) spawnSync("taskkill", ["/PID", String(child.pid), "/T", "/F"], { stdio: "ignore" });
    else process.kill(-child.pid, "SIGTERM");
  } catch {
    /* ya terminó */
  }
}

const api = start("php", ["artisan", "serve", "--host=127.0.0.1", "--port=8000"], backend);
const web = start("npm", ["run", "dev"], frontend, { NEXT_PUBLIC_API_BASE_URL: "http://127.0.0.1:8000" });
console.log("\nAPI:    http://127.0.0.1:8000\nTienda: http://127.0.0.1:3000   (Ctrl+C para detener)\n");

let closing = false;
function shutdown(code = 0) {
  if (closing) return;
  closing = true;
  stop(api);
  stop(web);
  setTimeout(() => process.exit(code), 500);
}
process.on("SIGINT", () => shutdown(0));
process.on("SIGTERM", () => shutdown(0));
api.on("exit", (code) => { if (!closing) { console.error(`La API terminó (código ${code}).`); shutdown(1); } });
web.on("exit", (code) => { if (!closing) { console.error(`El frontend terminó (código ${code}).`); shutdown(1); } });
