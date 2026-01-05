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
 * Remove section_type column from text_section table.
 *
 * The section_type field (preamble/interlude/conclusion) was not being used
 * and added unnecessary complexity to the text section implementation.
 */
final class Version20260102000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused section_type column from text_section table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE text_section DROP COLUMN section_type');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE text_section ADD COLUMN section_type VARCHAR(20) NOT NULL DEFAULT \'interlude\'');
    }
}
