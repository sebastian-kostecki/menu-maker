import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  expect: { timeout: 5_000 },
  fullyParallel: true,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
    headless: true,
    trace: 'on-first-retry',
    locale: 'en-US'
  },
  projects: [
    {
      name: 'chromium',
      testIgnore: /visual\//,
      use: { ...devices['Desktop Chrome'] }
    },
    {
      name: 'firefox',
      testIgnore: /visual\//,
      use: { ...devices['Desktop Firefox'] }
    },
    {
      name: 'visual',
      testMatch: /visual\/.*\.spec\.ts$/,
      snapshotPathTemplate: '{testDir}/__screenshots__/{testFilePath}/{arg}{ext}',
      updateSnapshots: process.env.CI ? 'none' : 'missing',
      // threshold 0.005 absorbs ±1 RGB anti-aliasing noise between CPUs (same pinned image, different runners);
      // any visible change still fails, since no pixel may exceed it (maxDiffPixels: 0).
      expect: { toHaveScreenshot: { threshold: 0.005, maxDiffPixels: 0 } },
      use: { ...devices['Desktop Chrome'], timezoneId: 'UTC' }
    }
  ],
  webServer: process.env.E2E_SKIP_WEBSERVER
    ? undefined
    : {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
        reuseExistingServer: true,
        timeout: 120_000
      }
});


