<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20191013083741 extends AbstractMigration {

    function getDescription(): string {
        return 'Introduces multiple barcodes and tags for an article';
    }

    function up(Schema $schema): void {
        $platform = $this->connection->getDatabasePlatform()->getName();

        switch ($platform) {
            case 'mysql':       $this->upMySQL();       return;
            case 'sqlite':      $this->upSQLite();      return;
            case 'postgresql':  $this->upPostgreSQL();  return;

            default:
                $this->abortIf(true, sprintf("Database migration for Platform '%s' is not supported.", $platform));
        }
    }

    private function upMySQL(): void {
        $this->addSql('CREATE TABLE barcode (id INT AUTO_INCREMENT NOT NULL, article_id INT DEFAULT NULL, barcode VARCHAR(32) NOT NULL, created DATETIME NOT NULL, INDEX IDX_97AE02667294869C (article_id), UNIQUE INDEX barcode (barcode), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE barcode ADD CONSTRAINT FK_97AE02667294869C FOREIGN KEY (article_id) REFERENCES article (id)');

        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, article_id INT DEFAULT NULL, tag VARCHAR(255) NOT NULL, created DATETIME NOT NULL, INDEX IDX_389B7837294869C (article_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE article_tag (id INT AUTO_INCREMENT NOT NULL, article_id INT DEFAULT NULL, tag_id INT DEFAULT NULL, created DATETIME NOT NULL, INDEX IDX_919694F97294869C (article_id), INDEX IDX_919694F9BAD26311 (tag_id), UNIQUE INDEX article_tag (article_id, tag_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F97294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F9BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id)');

        // Migrate barcodes
        $this->addSql("INSERT INTO barcode (article_id, barcode, created) SELECT id, barcode, NOW() FROM article WHERE barcode IS NOT NULL AND barcode <> '' AND active = 1");

        $this->addSql('ALTER TABLE article DROP barcode');
    }

    private function upSQLite() {
        $this->addSql('CREATE TABLE article_tag (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, article_id INTEGER DEFAULT NULL, tag_id INTEGER DEFAULT NULL, created DATETIME NOT NULL)');
        $this->addSql('CREATE INDEX IDX_919694F97294869C ON article_tag (article_id)');
        $this->addSql('CREATE INDEX IDX_919694F9BAD26311 ON article_tag (tag_id)');
        $this->addSql('CREATE UNIQUE INDEX article_tag ON article_tag (article_id, tag_id)');

        $this->addSql('CREATE TABLE barcode (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, article_id INTEGER DEFAULT NULL, barcode VARCHAR(32) NOT NULL, created DATETIME NOT NULL)');
        $this->addSql('CREATE INDEX IDX_97AE02667294869C ON barcode (article_id)');
        $this->addSql('CREATE UNIQUE INDEX barcode ON barcode (barcode)');

        $this->addSql('CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tag VARCHAR(255) NOT NULL, created DATETIME NOT NULL)');

        // Migrate barcodes
        $this->addSql("INSERT INTO barcode (article_id, barcode, created) SELECT id, barcode, date('now') FROM article WHERE barcode IS NOT NULL AND barcode <> '' AND active = 1");

        // SQLITE does not support alter table add/remove column, so we have to copy the whole table with a temp table
        $this->addSql('DROP INDEX UNIQ_23A0E66FA546BCC');
        $this->addSql('CREATE TEMPORARY TABLE __temp__article AS SELECT id, precursor_id, name, amount, active, created, usage_count FROM article');
        $this->addSql('DROP TABLE article');
        $this->addSql('CREATE TABLE article (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, precursor_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL COLLATE BINARY, amount INTEGER NOT NULL, active BOOLEAN NOT NULL, created DATETIME NOT NULL, usage_count INTEGER NOT NULL, CONSTRAINT FK_23A0E66FA546BCC FOREIGN KEY (precursor_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO article (id, precursor_id, name, amount, active, created, usage_count) SELECT id, precursor_id, name, amount, active, created, usage_count FROM __temp__article');
        $this->addSql('DROP TABLE __temp__article');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_23A0E66FA546BCC ON article (precursor_id)');
    }

    private function upPostgreSQL() {
        $this->addSql('CREATE SEQUENCE barcode_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tag_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE barcode (id INT NOT NULL, article_id INT DEFAULT NULL, barcode VARCHAR(32) NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_97AE02667294869C ON barcode (article_id)');
        $this->addSql('CREATE UNIQUE INDEX barcode ON barcode (barcode)');

        $this->addSql('CREATE TABLE tag (id INT NOT NULL, tag VARCHAR(255) NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE TABLE article_tag (id INT NOT NULL, article_id INT DEFAULT NULL, tag_id INT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_919694F97294869C ON article_tag (article_id)');
        $this->addSql('CREATE INDEX IDX_919694F9BAD26311 ON article_tag (tag_id)');
        $this->addSql('CREATE UNIQUE INDEX article_tag ON article_tag (article_id, tag_id)');

        // Migrate barcodes
        $this->addSql("INSERT INTO barcode (article_id, barcode, created) SELECT id, barcode, NOW()FROM article WHERE barcode IS NOT NULL AND barcode <> '' AND active = 1");

        $this->addSql('ALTER TABLE article DROP barcode');
    }

    function down(Schema $schema): void {
    }
}
