// @ts-check
import { defineConfig } from '@playwright/test';

// -d variables_order=EGPCS: without E, PHP never populates $_ENV and
// Symfony silently falls back to .env.local — the e2e DB isolation depends on it
const PHP = 'php -d variables_order=EGPCS';

// the readiness poll on /user/active fires before globalSetup runs, so the
// fresh schema must already exist when the server accepts its first request.
// the whole chain is wrapped so all of its output — schema errors, the dev
// server's per-request log, any PHP fatal — lands in one file. CI uploads it
// on failure: it is the only way to see why the server process exits (e.g. the
// SIGSEGV behind "Exit code: 139", which otherwise leaves no trace at all).
const SERVER_LOG = 'var/e2e-server.log';
const PHP_SERVER = `( rm -f var/e2e.db && ${PHP} bin/console doctrine:schema:create --quiet && ${PHP} -S 127.0.0.1:8765 -t public ) > ${SERVER_LOG} 2>&1`;

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
