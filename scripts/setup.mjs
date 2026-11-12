// Instalación de Proyecto Rotor. Funciona igual en Windows, Linux y macOS.
// Uso (desde la raíz del proyecto):  node scripts/setup.mjs
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const backend = path.join(root, "backend");
const frontend = path.join(root, "frontend");
const isWin = process.platform === "win32";

function exec(cmd, args, cwd, stdio) {
  // En Windows composer y npm son .bat/.cmd: necesitan shell.
  const options = { cwd, stdio, encoding: "utf8", shell: isWin };
  return isWin ? spawnSync([cmd, ...args].join(" "), options) : spawnSync(cmd, args, options);
}
const run = (cmd, args, cwd) => exec(cmd, args, cwd, "inherit").status === 0;
const out = (cmd, args, cwd = root) => {
  const r = exec(cmd, args, cwd, "pipe");
  return r.status === 0 ? String(r.stdout).trim() : null;
};
function fail(message) {
  console.error(`\nERROR: ${message}`);
  process.exit(1);
}
const step = (text) => console.log(`\n== ${text} ==`);

step("1. Comprobando requisitos");
for (const [cmd, args, hint] of [
  ["php", ["-v"], "Instale PHP 8.2 o superior y agréguelo al PATH."],
  ["composer", ["--version"], "Instale Composer (https://getcomposer.org)."],
  ["node", ["-v"], "Instale Node.js 22 o superior."],
  ["npm", ["-v"], "npm viene con Node.js."],
]) {
  if (out(cmd, args) === null) fail(`No se encontró '${cmd}'. ${hint}`);
}
const phpId = Number(out("php", ["-r", "echo PHP_VERSION_ID;"]));
if (!(phpId >= 80200)) fail(`Se necesita PHP 8.2 o superior (detectado ${out("php", ["-r", "echo PHP_VERSION;"])}).`);
const nodeMajor = Number(process.versions.node.split(".")[0]);
if (nodeMajor < 20) fail(`Se necesita Node.js 20 o superior (detectado ${process.versions.node}).`);

const modules = new Set(
  (out("php", ["-m"]) ?? "").split(/\r?\n/).map((line) => line.trim().toLowerCase()).filter(Boolean),
);
const missing = ["bcmath", "pgsql", "pdo_pgsql", "mbstring", "openssl", "curl", "fileinfo"].filter((ext) => !modules.has(ext));
if (missing.length > 0) {
  fail(
    `Faltan extensiones de PHP: ${missing.join(", ")}.\n` +
      "  Windows: abra php.ini (php --ini) y quite el ';' de la línea extension=<nombre>.\n" +
      "  Linux:   sudo apt install " + missing.map((m) => `php-${m === "pdo_pgsql" ? "pgsql" : m}`).join(" "),
  );
}
console.log("OK: PHP, Composer, Node y extensiones.");

step("2. Backend");
const envFile = path.join(backend, ".env");
if (!fs.existsSync(envFile)) {
  fs.copyFileSync(path.join(backend, ".env.example"), envFile);
  console.log(
    "Se creó backend/.env.\n" +
      "Edítelo ahora: ponga DB_PASSWORD (y las llaves WOMPI_* si las tiene) y vuelva a ejecutar:  node scripts/setup.mjs",
  );
  process.exit(0);
}
if (!run("composer", ["install", "--no-interaction"], backend)) fail("composer install falló.");
if (!/^APP_KEY=base64:/m.test(fs.readFileSync(envFile, "utf8"))) {
  if (!run("php", ["artisan", "key:generate"], backend)) fail("No se pudo generar APP_KEY.");
}
for (const dir of ["storage/framework/views", "storage/framework/cache/data", "storage/framework/sessions", "storage/logs", "storage/app/public", "bootstrap/cache"]) {
  fs.mkdirSync(path.join(backend, dir), { recursive: true });
}
if (!fs.existsSync(path.join(backend, "public", "storage"))) {
  if (!run("php", ["artisan", "storage:link"], backend)) {
    console.warn("Aviso: no se pudo crear el enlace public/storage (en Windows requiere modo desarrollador o administrador).");
    console.warn("Las imágenes se sirven igual desde Laravel; no es un error.");
  }
}
if (!run("php", ["artisan", "migrate", "--force"], backend)) {
  fail("migrate falló. Revise DB_* en backend/.env, que PostgreSQL esté iniciado y que existan las bases 'rotor' y 'rotor_testing'.");
}
run("php", ["artisan", "db:seed", "--class=CatalogSeeder", "--force"], backend);
run("php", ["artisan", "rotor:create-admin", "admin@rotor.test", "Admin12345"], backend);

step("3. Frontend");
const envLocal = path.join(frontend, ".env.local");
if (!fs.existsSync(envLocal)) {
  fs.writeFileSync(envLocal, "NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000\n");
}
if (!run("npm", ["ci"], frontend)) fail("npm ci falló.");

console.log("\nListo. Administrador: admin@rotor.test / Admin12345");
console.log("Para arrancar:  node scripts/start.mjs");
