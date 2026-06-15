import { execSync } from 'node:child_process';

// seed articles into the schema the webServer command just created;
// users are created through the UI by the tests
export default function globalSetup() {
    const env = {
        ...process.env,
        DATABASE_URL: 'sqlite:///%kernel.project_dir%/var/e2e.db',
    };
    const php = (cmd) =>
        execSync(`php -d variables_order=EGPCS ${cmd}`, { env, stdio: 'pipe' });

    php(
        `bin/console dbal:run-sql "INSERT INTO article (name, amount, active, created, usage_count) VALUES ('Club Mate', 150, 1, datetime('now'), 0), ('Beer', 90, 1, datetime('now'), 0)"`,
    );
}
