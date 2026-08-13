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

class Version20260505123150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-17697: Add account_deletion_tracking table for inactivity-based user deletion workflow';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if ($schema->hasTable('account_deletion_tracking')) {
            return;
        }

        $this->addSql('CREATE TABLE account_deletion_tracking (id VARCHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, first_warning_mail_id INT DEFAULT NULL, second_warning_mail_id INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_A77609BBA76ED395 (user_id), INDEX IDX_A77609BBEFDFF1B4 (first_warning_mail_id), INDEX IDX_A77609BB169750BC (second_warning_mail_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE account_deletion_tracking ADD CONSTRAINT FK_A77609BBA76ED395 FOREIGN KEY (user_id) REFERENCES _user (_u_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE account_deletion_tracking ADD CONSTRAINT FK_A77609BBEFDFF1B4 FOREIGN KEY (first_warning_mail_id) REFERENCES _mail_send (_ms_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE account_deletion_tracking ADD CONSTRAINT FK_A77609BB169750BC FOREIGN KEY (second_warning_mail_id) REFERENCES _mail_send (_ms_id) ON DELETE SET NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if (!$schema->hasTable('account_deletion_tracking')) {
            return;
        }

        $this->addSql('ALTER TABLE account_deletion_tracking DROP FOREIGN KEY FK_A77609BBA76ED395');
        $this->addSql('ALTER TABLE account_deletion_tracking DROP FOREIGN KEY FK_A77609BBEFDFF1B4');
        $this->addSql('ALTER TABLE account_deletion_tracking DROP FOREIGN KEY FK_A77609BB169750BC');
        $this->addSql('DROP TABLE account_deletion_tracking');
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
