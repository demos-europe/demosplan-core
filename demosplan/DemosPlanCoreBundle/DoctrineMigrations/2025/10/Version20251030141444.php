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
use Ramsey\Uuid\Uuid;

/**
 * Restore missing BoilerplateCategories that were accidentally deleted due to cascade remove bug.
 * This fixes the issue where Textbaustein checkboxes (Begründung, E-Mail, Aktuelle Mitteilungen)
 * disappear after deleting a Textbaustein.
 *
 * Related to DPLAN-16774: Fix Textbaustein checkbox cascade delete bug
 */
final class Version20251030141444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-16774: Restore missing BoilerplateCategories that were accidentally deleted due to cascade remove bug';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Get all procedures that are missing BoilerplateCategories
        $procedures = $this->connection->fetchAllAssociative('SELECT _p_id FROM _procedure');

        foreach ($procedures as $procedure) {
            $procedureId = $procedure['_p_id'];

            // Check and restore 'consideration' category
            $existingConsideration = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM _predefined_texts_category WHERE _p_id = ? AND ptc_title = ?',
                [$procedureId, 'consideration']
            );

            if ($existingConsideration == 0) {
                $this->addSql(
                    'INSERT INTO _predefined_texts_category (ptc_id, _p_id, ptc_title, ptc_text, ptc_create_date, ptc_modify_date) VALUES (?, ?, ?, ?, NOW(), NOW())',
                    [Uuid::uuid4()->toString(), $procedureId, 'consideration', 'Begründung']
                );
            }

            // Check and restore 'email' category
            $existingEmail = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM _predefined_texts_category WHERE _p_id = ? AND ptc_title = ?',
                [$procedureId, 'email']
            );

            if ($existingEmail == 0) {
                $this->addSql(
                    'INSERT INTO _predefined_texts_category (ptc_id, _p_id, ptc_title, ptc_text, ptc_create_date, ptc_modify_date) VALUES (?, ?, ?, ?, NOW(), NOW())',
                    [Uuid::uuid4()->toString(), $procedureId, 'email', 'E-Mail']
                );
            }

            // Check and restore 'news.notes' category
            $existingNews = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM _predefined_texts_category WHERE _p_id = ? AND ptc_title = ?',
                [$procedureId, 'news.notes']
            );

            if ($existingNews == 0) {
                $this->addSql(
                    'INSERT INTO _predefined_texts_category (ptc_id, _p_id, ptc_title, ptc_text, ptc_create_date, ptc_modify_date) VALUES (?, ?, ?, ?, NOW(), NOW())',
                    [Uuid::uuid4()->toString(), $procedureId, 'news.notes', 'Aktuelle Mitteilungen']
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

        // We don't want to delete the restored categories in down migration
        // as they are essential for the system to work properly
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
