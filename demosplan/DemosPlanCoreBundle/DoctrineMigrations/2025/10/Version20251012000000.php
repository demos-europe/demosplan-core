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

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add order_in_statement column for unified content block ordering.
 *
 * This migration adds a new column that will eventually replace order_in_procedure
 * for unified ordering of Segments and TextSections within a Statement.
 * Both columns coexist during the transition period.
 */
final class Version20251012000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order_in_statement column for unified content block ordering (Segment + TextSection)';
    }

    public function up(Schema $schema): void
    {
        // Add new order_in_statement column
        $this->addSql('ALTER TABLE _statement ADD COLUMN order_in_statement INT DEFAULT NULL');

        // Populate new column from existing data for segments that have order_in_procedure
        $this->addSql('UPDATE _statement SET order_in_statement = order_in_procedure WHERE order_in_procedure IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE _statement DROP COLUMN order_in_statement');
    }
}
