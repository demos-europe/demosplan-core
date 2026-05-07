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

class Version20260506150528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-17697: Insert account-deletion mail templates for existing instances (idempotent — skips labels already present from ProdFixtures or earlier runs)';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        foreach ($this->getTemplates() as $template) {
            if (!$this->templateExists($template['label'], $template['language'])) {
                $this->addSql(
                    'INSERT INTO _mail_templates (_mt_label, _mt_language, _mt_title, _mt_content) VALUES (?, ?, ?, ?)',
                    [$template['label'], $template['language'], $template['title'], $template['content']]
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        foreach ($this->getTemplates() as $template) {
            $this->addSql(
                'DELETE FROM _mail_templates WHERE _mt_label = ? AND _mt_language = ?',
                [$template['label'], $template['language']]
            );
        }
    }

    /**
     * @return list<array{label: string, language: string, title: string, content: string}>
     */
    private function getTemplates(): array
    {
        return [
            [
                'label'    => 'account_deletion_warning_first',
                'language' => 'de_DE',
                'title'    => 'Hinweis zur Inaktivität Ihres Kontos',
                'content'  => "Sehr geehrte/r \${firstname} \${lastname},\r\n\r\nIhr Konto ist seit längerer Zeit inaktiv. Aus datenschutzrechtlichen Gründen wird Ihr Konto am \${deletion_date} gelöscht, sofern Sie sich bis dahin nicht erneut anmelden.\r\n\r\nUm Ihr Konto zu erhalten, melden Sie sich bitte erneut an.\${link_section}\r\n\r\nMit freundlichen Grüßen\r\nIhr support Team",
            ],
            [
                'label'    => 'account_deletion_warning_second',
                'language' => 'de_DE',
                'title'    => 'Letzte Erinnerung: Ihr Konto wird am ${deletion_date} gelöscht',
                'content'  => "Sehr geehrte/r \${firstname} \${lastname},\r\n\r\nwir hatten Sie bereits darauf hingewiesen, dass Ihr Konto wegen Inaktivität gelöscht wird. Bisher haben wir keine erneute Anmeldung registriert.\r\n\r\nIhr Konto wird am \${deletion_date} gelöscht, sofern Sie sich bis dahin nicht anmelden.\${link_section}\r\n\r\nMit freundlichen Grüßen\r\nIhr support Team",
            ],
            [
                'label'    => 'account_deletion_completed',
                'language' => 'de_DE',
                'title'    => 'Ihr Konto wurde gelöscht',
                'content'  => "Sehr geehrte/r \${firstname} \${lastname},\r\n\r\nIhr Konto wurde aufgrund von Inaktivität gemäß den geltenden Datenschutzrichtlinien gelöscht.\r\n\r\nMit freundlichen Grüßen\r\nIhr support Team",
            ],
        ];
    }

    private function templateExists(string $label, string $language): bool
    {
        $existingId = $this->connection->fetchOne(
            'SELECT _mt_id FROM _mail_templates WHERE _mt_label = ? AND _mt_language = ?',
            [$label, $language]
        );

        return false !== $existingId;
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
