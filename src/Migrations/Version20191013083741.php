<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20191013083741 extends AbstractMigration {

    function getDescription(): string {
        return 'Introduces multiple barcodes and tags for an article';
    }

    function up(Schema $schema): void {
        // Check if migration is already applied
        if (!$schema->getTable('article')->hasColumn('barcode')) {
            return;
        }

        $duplicates = $this->connection->fetchFirstColumn(
            "SELECT barcode
               FROM article
              WHERE barcode IS NOT NULL AND barcode <> '' AND active = :active
              GROUP BY barcode
             HAVING COUNT(*) > 1
              ORDER BY barcode",
            ['active' => true],
            ['active' => ParameterType::BOOLEAN],
        );

        $this->abortIf(
            $duplicates !== [],
            sprintf(
                "Cannot migrate: %d barcode value(s) appear on more than one active article. "
                . "Remove duplicates in `article.barcode` first, then re-run the migration. "
                . "Offending barcodes: %s",
                count($duplicates),
                implode(', ', $duplicates),
            ),
        );

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->upMySQL();
            return;
        }
        if ($platform instanceof SqlitePlatform) {
            $this->upSQLite();
            return;
        }
        if ($platform instanceof PostgreSQLPlatform) {
            $this->upPostgreSQL();
            return;
        }

        $this->abortIf(true, sprintf("Database migration for Platform '%s' is not supported.", $platform::class));
    }

    private function upMySQL(): void {
        $this->addSql('CREATE TABLE barcode (id INT AUTO_INCREMENT NOT NULL, article_id INT NOT NULL, barcode VARCHAR(32) NOT NULL, created DATETIME NOT NULL, INDEX IDX_97AE02667294869C (article_id), UNIQUE INDEX UNIQ_97AE026697AE0266 (barcode), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE barcode ADD CONSTRAINT FK_97AE02667294869C FOREIGN KEY (article_id) REFERENCES article (id)');

        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, tag VARCHAR(255) NOT NULL, created DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE article_tag (id INT AUTO_INCREMENT NOT NULL, article_id INT NOT NULL, tag_id INT NOT NULL, created DATETIME NOT NULL, INDEX IDX_919694F97294869C (article_id), INDEX IDX_919694F9BAD26311 (tag_id), UNIQUE INDEX UNIQ_919694F97294869CBAD26311 (article_id, tag_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F97294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F9BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id)');

        // Migrate barcodes
        $this->addSql("INSERT INTO barcode (article_id, barcode, created) SELECT id, barcode, NOW() FROM article WHERE barcode IS NOT NULL AND barcode <> '' AND active = 1");

        $this->addSql('ALTER TABLE article DROP barcode');
    }

    private function upSQLite() {
        $this->addSql('CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tag VARCHAR(255) NOT NULL, created DATETIME NOT NULL)');

        $this->addSql('CREATE TABLE article_tag (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, article_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, created DATETIME NOT NULL, CONSTRAINT FK_919694F97294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_919694F9BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_919694F97294869C ON article_tag (article_id)');
        $this->addSql('CREATE INDEX IDX_919694F9BAD26311 ON article_tag (tag_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_919694F97294869CBAD26311 ON article_tag (article_id, tag_id)');

        $this->addSql('CREATE TABLE barcode (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, article_id INTEGER NOT NULL, barcode VARCHAR(32) NOT NULL, created DATETIME NOT NULL, CONSTRAINT FK_97AE02667294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_97AE02667294869C ON barcode (article_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_97AE026697AE0266 ON barcode (barcode)');

        // Migrate barcodes
        $this->addSql("INSERT INTO barcode (article_id, barcode, created) SELECT id, barcode, datetime('now') FROM article WHERE barcode IS NOT NULL AND barcode <> '' AND active = 1");

        $this->addSql('ALTER TABLE article DROP COLUMN barcode');
    }

    private function upPostgreSQL() {
        $this->addSql('CREATE SEQUENCE barcode_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tag_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE article_tag_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE barcode (id INT NOT NULL, article_id INT NOT NULL, barcode VARCHAR(32) NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_97AE02667294869C ON barcode (article_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_97AE026697AE0266 ON barcode (barcode)');

        $this->addSql('CREATE TABLE tag (id INT NOT NULL, tag VARCHAR(255) NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE TABLE article_tag (id INT NOT NULL, article_id INT NOT NULL, tag_id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_919694F97294869C ON article_tag (article_id)');
        $this->addSql('CREATE INDEX IDX_919694F9BAD26311 ON article_tag (tag_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_919694F97294869CBAD26311 ON article_tag (article_id, tag_id)');

        $this->addSql('ALTER TABLE barcode ADD CONSTRAINT FK_97AE02667294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F97294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F9BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Migrate barcodes
        $this->addSql("INSERT INTO barcode (id, article_id, barcode, created) SELECT nextval('barcode_id_seq'), id, barcode, NOW() FROM article WHERE barcode IS NOT NULL AND barcode <> '' AND active = true");

        $this->addSql('ALTER TABLE article DROP barcode');
    }

    function down(Schema $schema): void {
        $this->throwIrreversibleMigrationException();
    }
}
