import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// El proxy hace que el navegador vea un solo origen (localhost:5173),
// requisito de Sanctum en modo SPA (ver sección 3.1 del informe).
const backend = 'http://localhost:8000'
export default defineConfig({
  plugins: [vue()],
  server: { port: 5173, proxy: { '/api': backend, '/sanctum': backend } },
})
