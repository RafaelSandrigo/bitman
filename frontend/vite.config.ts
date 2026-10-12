/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  build: {
    // Sem source maps em produção: o build é servido publicamente.
    sourcemap: false,
  },
  test: {
    environment: 'jsdom',
    environmentOptions: { jsdom: { url: 'http://localhost/' } },
    setupFiles: ['./src/test/setup.ts'],
    restoreMocks: true,
    env: { VITE_GAMES_ORIGIN: 'https://games.test' },
  },
})
