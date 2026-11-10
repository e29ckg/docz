import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/docz/',
  build: { outDir: '../web/app', emptyOutDir: true },
  server: {
    port: 5173,
    proxy: {
      // Proxy สำหรับคุยกับ Node.js Express Backend (พอร์ต 3000)
      '/api': {
        target: 'http://127.0.0.1:3000',
        changeOrigin: true,
      },
      '/uploads': {
        target: 'http://127.0.0.1:3000',
        changeOrigin: true,
      },
      // รองรับ backward compatibility สำหรับ endpoint เดิม
      '/api.php': {
        target: 'http://127.0.0.1:3000',
        changeOrigin: true,
      },
    },
  },
})
