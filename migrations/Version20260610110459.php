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
        $this->addSql('CREATE INDEX transactions_created_idx ON transactions (created)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX transactions_created_idx');
    }
}
