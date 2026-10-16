import { defineConfig } from '@playwright/test';
import { config } from './support/config.mjs';

export default defineConfig({
    testDir: './specs',
    globalSetup: './global-setup.mjs',
    // The tests share one site, one database and one client IP for the gateway's rate limits.
    workers: 1,
    fullyParallel: false,
    retries: 0,
    timeout: 90_000,
    reporter: [['list']],
    use: {
        baseURL: config.baseUrl,
        // DDEV serves a mkcert certificate that headless Chromium doesn't trust.
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
});
