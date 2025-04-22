<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove obsolete survey tables.
 */
class Version20250419000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete survey tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS survey_vote');
        $this->addSql('DROP TABLE IF EXISTS survey');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE survey (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, title TINYTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, description TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_AD5F9BFCD37B63A2 (p_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE survey_vote (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, survey_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, user_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, is_agreed TINYINT(1) NOT NULL, text TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, created_date DATE NOT NULL, text_review VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_9CF985AFA76ED395 (user_id), INDEX IDX_9CF985AFB3FE509D (survey_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');;
    }
}