/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, type Plugin } from 'vite'

/**
 * M10: `version.json` com o commit do build (`VITE_BITMAN_BUILD`, do make release). A SPA compara com
 * o valor embutido no bundle e avisa a aba aberta quando há uma versão nova.
 */
function versionFile(): Plugin {
  return {
    name: 'bitman-version-file',
    apply: 'build',
    generateBundle() {
      this.emitFile({ type: 'asset', fileName: 'version.json', source: JSON.stringify({ build: process.env.VITE_BITMAN_BUILD ?? '' }) })
    },
  }
}

export default defineConfig({
  plugins: [react(), tailwindcss(), versionFile()],
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
