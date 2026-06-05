import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const proxyTarget = env.VITE_PROXY_TARGET

  return {
    plugins: [react()],
    server: {
      port: 5175,
      strictPort: true,
      // Si VITE_PROXY_TARGET esta definido (ej. Laragon en sistema-cup.test),
      // Vite redirige /api y /sanctum hacia ese backend. Esto permite que las
      // cookies de Sanctum se compartan correctamente entre frontend y backend
      // sin problemas de CORS/SameSite cross-origin.
      // Sin VITE_PROXY_TARGET, axios usa VITE_API_URL directo (modo por defecto).
      proxy: proxyTarget
        ? {
            '/api': { target: proxyTarget, changeOrigin: true },
            '/sanctum': { target: proxyTarget, changeOrigin: true },
          }
        : undefined,
    },
  }
})
