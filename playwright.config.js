// @ts-check
import { defineConfig } from '@playwright/test';

// -d variables_order=EGPCS: without E, PHP never populates $_ENV and
// Symfony silently falls back to .env.local — the e2e DB isolation depends on it
const PHP = 'php -d variables_order=EGPCS';

// Which HTTP server serves the app under test:
//   - default (local): PHP's built-in `php -S` — every dev has it, no install.
//   - E2E_SERVER=frankenphp (CI): FrankenPHP in classic (non-worker) mode — the
//     production runtime. The GitHub x86_64 runner's `php -S` (ondrej build)
//     runs opcache's tracing JIT, which segfaults (exit 139) while compiling
//     the first request; FrankenPHP runs with JIT off, as prod does, so it
//     exercises the real server without tripping the JIT bug.
const SERVE =
    process.env.E2E_SERVER === 'frankenphp'
        ? 'frankenphp php-server --listen 127.0.0.1:8765 --root public'
        : `${PHP} -S 127.0.0.1:8765 -t public`;

// the readiness poll on /user/active fires before globalSetup runs, so the
// fresh schema must already exist when the server accepts its first request.
// the whole chain is wrapped so all of its output — schema errors, the
// server's per-request log, any PHP fatal — lands in one file. CI uploads it
// on failure: the log is the only record of why the server process exits.
const SERVER_LOG = 'var/e2e-server.log';
const PHP_SERVER = `( rm -f var/e2e.db && ${PHP} bin/console doctrine:schema:create --quiet && ${SERVE} ) > ${SERVER_LOG} 2>&1`;

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
        // the dev server's request log already goes to var/e2e-server.log (see
        // PHP_SERVER), so the piped streams carry nothing useful here — the log
        // file, uploaded by CI on failure, is the source of truth for crashes
        stdout: 'ignore',
        stderr: 'ignore',
        env: {
            DATABASE_URL: 'sqlite:///%kernel.project_dir%/var/e2e.db',
            APP_ENV: 'dev',
        },
    },
});
