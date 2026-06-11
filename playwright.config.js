// @ts-check
import { defineConfig } from '@playwright/test';

// -d variables_order=EGPCS: without E, PHP never populates $_ENV and
// Symfony silently falls back to .env.local — the e2e DB isolation depends on it
const PHP_SERVER = 'php -d variables_order=EGPCS -S 127.0.0.1:8765 -t public';

export default defineConfig({
    testDir: 'tests/e2e',
    // one stateful kiosk DB — keep runs deterministic
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    globalSetup: './tests/e2e/global-setup.js',
    use: {
        baseURL: 'http://127.0.0.1:8765',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: PHP_SERVER,
        url: 'http://127.0.0.1:8765/user/active',
        reuseExistingServer: false,
        env: {
            DATABASE_URL: 'sqlite:///%kernel.project_dir%/var/e2e.db',
            APP_ENV: 'dev',
        },
    },
});
