<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610110459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index transactions.created — the per-day metrics aggregation filters on it for every /metrics and /api/metrics request.';
    }

    public function up(Schema $schema): void
    {
        // Idempotent like the foundational migrations: skip if the index is
        // already present, so `migrate` succeeds when run against an existing
        // schema whose version tracking is incomplete (e.g. an older install
        // reattached via DATABASE_URL, or one created without migrations).
        if ($schema->getTable('transactions')->hasIndex('transactions_created_idx')) {
            return;
        }

        $this->addSql('CREATE INDEX transactions_created_idx ON transactions (created)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX transactions_created_idx');
    }
}
