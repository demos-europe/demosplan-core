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

class Version20240105113406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T34551: Remove unique-constraint for procedures - customer
        regardless of collation';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY IF EXISTS FK_D1A01D0281398E09');
        try {
            // Drop the unique constraint
            $this->addSql('ALTER TABLE _procedure DROP INDEX UNIQ_D1A01D0281398E09');
        } catch (Exception $e) {
            // Ignore if the index does not exist
        }
        $this->addSql('ALTER TABLE _procedure ADD CONSTRAINT FK_D1A01D0281398E09 FOREIGN KEY (customer) REFERENCES customer(_c_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY IF EXISTS FK_D1A01D0281398E09');
        try {
            // Drop the unique constraint
            $this->addSql('ALTER TABLE _procedure DROP INDEX UNIQ_D1A01D0281398E09');
        } catch (Exception $e) {
            // Ignore if the index does not exist
        }
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
