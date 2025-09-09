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
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230531123431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T31885: create platform_faq, platform_faq_role, platform_faq_category, to allow getting a kind of global faqs besides the already existing customer-faqs';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE platform_faq (id CHAR(36) NOT NULL, platform_faq_category_id CHAR(36) NOT NULL, title VARCHAR(255) DEFAULT \'\' NOT NULL, text LONGTEXT DEFAULT NULL, enabled TINYINT(1) DEFAULT 0 NOT NULL, create_date DATETIME NOT NULL, modify_date DATETIME NOT NULL, INDEX IDX_5ED987DF259D9BFA (platform_faq_category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE platform_faq_role (role_id CHAR(36) NOT NULL, platformFaq_id CHAR(36) NOT NULL, INDEX IDX_D707E21E3E7645EC (platformFaq_id), INDEX IDX_D707E21ED60322AC (role_id), PRIMARY KEY(platformFaq_id, role_id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE platform_faq_category (id CHAR(36) NOT NULL, title VARCHAR(255) DEFAULT \'\' NOT NULL, create_date DATETIME NOT NULL, modify_date DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE platform_faq ADD CONSTRAINT FK_5ED987DF259D9BFA FOREIGN KEY (platform_faq_category_id) REFERENCES platform_faq_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE platform_faq_role ADD CONSTRAINT FK_D707E21E3E7645EC FOREIGN KEY (platformFaq_id) REFERENCES platform_faq (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE platform_faq_role ADD CONSTRAINT FK_D707E21ED60322AC FOREIGN KEY (role_id) REFERENCES _role (_r_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE platform_faq DROP FOREIGN KEY FK_5ED987DF259D9BFA');
        $this->addSql('ALTER TABLE platform_faq_role DROP FOREIGN KEY FK_D707E21E3E7645EC');
        $this->addSql('ALTER TABLE platform_faq_role DROP FOREIGN KEY FK_D707E21ED60322AC');
        $this->addSql('DROP TABLE platform_faq');
        $this->addSql('DROP TABLE platform_faq_role');
        $this->addSql('DROP TABLE platform_faq_category');
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
