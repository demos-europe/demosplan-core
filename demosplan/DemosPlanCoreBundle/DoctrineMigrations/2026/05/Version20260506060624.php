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

class Version20260506060624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add UNIQUE index on customer._c_subdomain to enforce subdomain uniqueness '
            .'and speed up CustomerRepository::findCustomerBySubdomain lookups.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $customerTable = $schema->getTable('customer');

        if ($customerTable->hasIndex('uniq_customer_subdomain')) {
            return;
        }

        $duplicates = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (SELECT _c_subdomain FROM customer GROUP BY _c_subdomain HAVING COUNT(*) > 1) d'
        );

        $this->abortIf(
            (int) $duplicates > 0,
            'Cannot create UNIQUE index on customer._c_subdomain: duplicate subdomain values exist. Resolve duplicates before running this migration.'
        );

        $this->addSql('CREATE UNIQUE INDEX uniq_customer_subdomain ON customer (_c_subdomain)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $customerTable = $schema->getTable('customer');

        if (!$customerTable->hasIndex('uniq_customer_subdomain')) {
            return;
        }

        $this->addSql('ALTER TABLE customer DROP INDEX uniq_customer_subdomain');
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
