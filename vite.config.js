import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [
    laravel({
      input: "resources/js/app.tsx",
      refresh: true,
    }),
    react(),
  ],
  server: {
    host: "0.0.0.0", // すべてのインターフェースでリッスン
    port: 5173,
    cors: {
      origin: true, // すべてのオリジンを許可
      credentials: true,
    },
    hmr: {
      host: "localhost",
    },
  },
});
