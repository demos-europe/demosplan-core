<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230113141824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T30254: remove the table statement_import_email_processed_attachments';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE statement_import_email_processed_attachments DROP FOREIGN KEY FK_8D51E2FFFF064ED4');
        $this->addSql('ALTER TABLE statement_import_email_processed_attachments DROP FOREIGN KEY FK_8D51E2FF3174D6D6');
        $this->addSql('DROP TABLE statement_import_email_processed_attachments');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE statement_import_email_processed_attachments (statement_import_email_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, annotated_statement_pdf_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_8D51E2FF3174D6D6 (statement_import_email_id), UNIQUE INDEX UNIQ_8D51E2FFFF064ED4 (annotated_statement_pdf_id), PRIMARY KEY(statement_import_email_id, annotated_statement_pdf_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE statement_import_email_processed_attachments ADD CONSTRAINT FK_8D51E2FFFF064ED4 FOREIGN KEY (annotated_statement_pdf_id) REFERENCES annotated_statement_pdf (id)');
        $this->addSql('ALTER TABLE statement_import_email_processed_attachments ADD CONSTRAINT FK_8D51E2FF3174D6D6 FOREIGN KEY (statement_import_email_id) REFERENCES statement_import_email (id)');
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            'mysql' !== $this->connection->getDatabasePlatform()->getName(),
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
