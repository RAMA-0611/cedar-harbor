import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Imagen Docker mínima: .next/standalone contiene solo lo necesario para ejecutar.
  output: "standalone",
  poweredByHeader: false,
  // Inserta el CSS crítico en el HTML: elimina las hojas de estilo que bloquean el primer render.
  experimental: { inlineCss: true },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
