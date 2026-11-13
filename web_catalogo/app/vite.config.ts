import { fileURLToPath, URL } from 'node:url'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

// El build va a ../public (docroot de cada dominio). app/public/ (htaccess + entrada de la API) se copia tal cual.
// En desarrollo, /api se manda al Apache local (XAMPP), que resuelve la empresa por el host "localhost".
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  build: { outDir: '../public', emptyOutDir: true, chunkSizeWarningLimit: 600 },
  server: {
    port: 5173,
    proxy: {
      '/api': { target: 'http://localhost', rewrite: (p) => '/sistema/web_catalogo/public' + p },
    },
  },
})
