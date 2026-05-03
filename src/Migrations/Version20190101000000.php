<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190101000000 extends AbstractMigration {

    function getDescription(): string {
        return 'Initial base schema';
    }

    function up(Schema $schema): void {
        if ($schema->hasTable('user')) {
            return;
        }

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
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, precursor_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, barcode VARCHAR(32) DEFAULT NULL, amount INT NOT NULL, active TINYINT(1) NOT NULL, created DATETIME NOT NULL, usage_count INT NOT NULL, UNIQUE INDEX UNIQ_23A0E66FA546BCC (precursor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transactions (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, article_id INT DEFAULT NULL, recipient_transaction_id INT DEFAULT NULL, sender_transaction_id INT DEFAULT NULL, quantity INT DEFAULT NULL, comment VARCHAR(255) DEFAULT NULL, amount INT NOT NULL, deleted TINYINT(1) NOT NULL, created DATETIME NOT NULL, INDEX IDX_EAA81A4CA76ED395 (user_id), INDEX IDX_EAA81A4C7294869C (article_id), UNIQUE INDEX UNIQ_EAA81A4C87F3EDB8 (recipient_transaction_id), UNIQUE INDEX UNIQ_EAA81A4CFE2C36CC (sender_transaction_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) NOT NULL, email VARCHAR(255) DEFAULT NULL, balance INT NOT NULL, disabled TINYINT(1) NOT NULL, created DATETIME NOT NULL, updated DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D6495E237E06 (name), INDEX disabled_updated (disabled, updated), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66FA546BCC FOREIGN KEY (precursor_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C87F3EDB8 FOREIGN KEY (recipient_transaction_id) REFERENCES transactions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CFE2C36CC FOREIGN KEY (sender_transaction_id) REFERENCES transactions (id) ON DELETE CASCADE');
    }

    public function upSQLite(): void {
        $this->addSql('CREATE TABLE article (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, precursor_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, barcode VARCHAR(32) DEFAULT NULL, amount INTEGER NOT NULL, active BOOLEAN NOT NULL, created DATETIME NOT NULL, usage_count INTEGER NOT NULL, CONSTRAINT FK_23A0E66FA546BCC FOREIGN KEY (precursor_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_23A0E66FA546BCC ON article (precursor_id)');
        $this->addSql('CREATE TABLE transactions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, article_id INTEGER DEFAULT NULL, recipient_transaction_id INTEGER DEFAULT NULL, sender_transaction_id INTEGER DEFAULT NULL, quantity INTEGER DEFAULT NULL, comment VARCHAR(255) DEFAULT NULL, amount INTEGER NOT NULL, deleted BOOLEAN NOT NULL, created DATETIME NOT NULL, CONSTRAINT FK_EAA81A4CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4C7294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4C87F3EDB8 FOREIGN KEY (recipient_transaction_id) REFERENCES transactions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4CFE2C36CC FOREIGN KEY (sender_transaction_id) REFERENCES transactions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EAA81A4CA76ED395 ON transactions (user_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C7294869C ON transactions (article_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAA81A4C87F3EDB8 ON transactions (recipient_transaction_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAA81A4CFE2C36CC ON transactions (sender_transaction_id)');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(64) NOT NULL, email VARCHAR(255) DEFAULT NULL, balance INTEGER NOT NULL, disabled BOOLEAN NOT NULL, created DATETIME NOT NULL, updated DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6495E237E06 ON "user" (name)');
        $this->addSql('CREATE INDEX disabled_updated ON "user" (disabled, updated)');
    }

    private function upPostgreSQL(): void {
        $this->addSql('CREATE SEQUENCE article_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE transactions_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "user_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE article (id INT NOT NULL, precursor_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, barcode VARCHAR(32) DEFAULT NULL, amount INT NOT NULL, active BOOLEAN NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, usage_count INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_23A0E66FA546BCC ON article (precursor_id)');
        $this->addSql('CREATE TABLE transactions (id INT NOT NULL, user_id INT NOT NULL, article_id INT DEFAULT NULL, recipient_transaction_id INT DEFAULT NULL, sender_transaction_id INT DEFAULT NULL, quantity INT DEFAULT NULL, comment VARCHAR(255) DEFAULT NULL, amount INT NOT NULL, deleted BOOLEAN NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EAA81A4CA76ED395 ON transactions (user_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C7294869C ON transactions (article_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAA81A4C87F3EDB8 ON transactions (recipient_transaction_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAA81A4CFE2C36CC ON transactions (sender_transaction_id)');
        $this->addSql('CREATE TABLE "user" (id INT NOT NULL, name VARCHAR(64) NOT NULL, email VARCHAR(255) DEFAULT NULL, balance INT NOT NULL, disabled BOOLEAN NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6495E237E06 ON "user" (name)');
        $this->addSql('CREATE INDEX disabled_updated ON "user" (disabled, updated)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66FA546BCC FOREIGN KEY (precursor_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C7294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C87F3EDB8 FOREIGN KEY (recipient_transaction_id) REFERENCES transactions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CFE2C36CC FOREIGN KEY (sender_transaction_id) REFERENCES transactions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    function down(Schema $schema): void {
        $this->throwIrreversibleMigrationException();
    }
}
