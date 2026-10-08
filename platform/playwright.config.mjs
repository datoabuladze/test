import { defineConfig, devices } from '@playwright/test';

// End-to-end tests against a running app (default http://127.0.0.1:8000, seeded database).
// Start it with: php -S 127.0.0.1:8000 -t public scripts/dev-router.php
const executablePath = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium';

export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 45_000,
  retries: 0,
  workers: 2,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    launchOptions: { executablePath },
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], launchOptions: { executablePath } } },
    { name: 'mobile', use: { ...devices['Pixel 7'], launchOptions: { executablePath } }, grep: /@mobile/ },
  ],
});
