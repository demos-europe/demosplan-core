<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20261120181246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add ocx_delivery for deliveries received via the OCX intake API';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE ocx_delivery (id CHAR(36) NOT NULL, procedure_id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, organisation_id CHAR(36) DEFAULT NULL, idempotency_key VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, failure VARCHAR(32) DEFAULT NULL, manifest JSON NOT NULL COMMENT \'(DC2Type:json)\', received_parts JSON NOT NULL COMMENT \'(DC2Type:json)\', received_attachments JSON NOT NULL COMMENT \'(DC2Type:json)\', results JSON NOT NULL COMMENT \'(DC2Type:json)\', counts JSON NOT NULL COMMENT \'(DC2Type:json)\', permitted_kinds JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, processed_at DATETIME DEFAULT NULL, processed_entries INT DEFAULT 0 NOT NULL, progress_updated_at DATETIME DEFAULT NULL, INDEX ocx_delivery_status_created_at (status, created_at), UNIQUE INDEX ocx_delivery_user_idempotency_key (user_id, idempotency_key), INDEX IDX_F2F3BD9D1624BCD2 (procedure_id), INDEX IDX_F2F3BD9DA76ED395 (user_id), INDEX IDX_F2F3BD9D9E6B1585 (organisation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ocx_delivery ADD CONSTRAINT FK_F2F3BD9D1624BCD2 FOREIGN KEY (procedure_id) REFERENCES _procedure (_p_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ocx_delivery ADD CONSTRAINT FK_F2F3BD9DA76ED395 FOREIGN KEY (user_id) REFERENCES _user (_u_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ocx_delivery ADD CONSTRAINT FK_F2F3BD9D9E6B1585 FOREIGN KEY (organisation_id) REFERENCES _orga (_o_id) ON DELETE SET NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE ocx_delivery DROP FOREIGN KEY FK_F2F3BD9D1624BCD2');
        $this->addSql('ALTER TABLE ocx_delivery DROP FOREIGN KEY FK_F2F3BD9DA76ED395');
        $this->addSql('ALTER TABLE ocx_delivery DROP FOREIGN KEY FK_F2F3BD9D9E6B1585');
        $this->addSql('DROP TABLE ocx_delivery');
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
