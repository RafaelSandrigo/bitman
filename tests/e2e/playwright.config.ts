import { defineConfig, devices } from '@playwright/test'

/** M11: Chromium de verdade contra a stack E2E (compose.yml). */
export default defineConfig({
  testDir: './specs',
  timeout: 30_000,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.HUB_URL ?? 'http://hub.e2e',
    trace: 'off',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
