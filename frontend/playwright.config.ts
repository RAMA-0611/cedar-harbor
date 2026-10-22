import { defineConfig, devices } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

function loadEnvFile(filePath: string): Record<string, string> {
  if (!fs.existsSync(filePath)) {
    throw new Error(`Missing ${filePath} (local, gitignored).`);
  }

  const map: Record<string, string> = {};
  for (const line of fs.readFileSync(filePath, "utf8").split(/\r?\n/)) {
    const trimmed = line.trim();
    if (trimmed === "" || trimmed.startsWith("#") || !trimmed.includes("=")) {
      continue;
    }
    const separator = trimmed.indexOf("=");
    const key = trimmed.slice(0, separator).trim();
    let value = trimmed.slice(separator + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    map[key] = value;
  }
  return map;
}

const repoRoot = path.resolve(__dirname, "..");
const backendEnvPath = path.join(repoRoot, "backend", ".env.e2e");
const backendEnv = loadEnvFile(backendEnvPath);

if (backendEnv.APP_ENV !== "e2e" || backendEnv.DB_DATABASE !== "rotor_e2e") {
  throw new Error("Playwright refuses to start unless backend/.env.e2e targets rotor_e2e.");
}

if (!backendEnv.E2E_ADMIN_EMAIL || !backendEnv.E2E_CUSTOMER_EMAIL) {
  throw new Error("E2E_ADMIN_EMAIL and E2E_CUSTOMER_EMAIL are required locally.");
}

if (!backendEnv.E2E_ADMIN_PASSWORD || !backendEnv.E2E_CUSTOMER_PASSWORD) {
  throw new Error("E2E_ADMIN_PASSWORD and E2E_CUSTOMER_PASSWORD are required locally.");
}

for (const [key, value] of Object.entries(backendEnv)) {
  if (process.env[key] === undefined) {
    process.env[key] = value;
  }
}

process.env.E2E_API_URL = "http://127.0.0.1:8001";
process.env.NEXT_PUBLIC_API_BASE_URL = "http://127.0.0.1:8001";

const laravelEnv: Record<string, string> = {
  ...backendEnv,
  APP_ENV: "e2e",
  DB_DATABASE: "rotor_e2e",
  APP_URL: "http://127.0.0.1:8001",
  FRONTEND_URL: "http://127.0.0.1:3001",
};

export default defineConfig({
  testDir: "./e2e",
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 20_000 },
  reporter: [["list"], ["html", { open: "never" }]],
  use: {
    baseURL: "http://127.0.0.1:3001",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  webServer: [
    {
      command: "php artisan serve --host=127.0.0.1 --port=8001",
      cwd: path.join(repoRoot, "backend"),
      url: "http://127.0.0.1:8001/up",
      reuseExistingServer: false,
      timeout: 120_000,
      env: laravelEnv,
    },
    {
      command: "npx next dev --hostname 127.0.0.1 --port 3001",
      cwd: __dirname,
      url: "http://127.0.0.1:3001",
      reuseExistingServer: false,
      timeout: 120_000,
      env: {
        NEXT_PUBLIC_API_BASE_URL: "http://127.0.0.1:8001",
      },
    },
  ],
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
