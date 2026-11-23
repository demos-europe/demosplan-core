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

class Version20261123170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'index _statement by procedure and extern id, source statement id and segment order';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE INDEX procedure_extern_id ON _statement (_p_id, _st_extern_id)');
        $this->addSql('CREATE INDEX procedure_source_statement_id ON _statement (_p_id, source_statement_id)');
        $this->addSql('CREATE INDEX procedure_order_in_procedure ON _statement (_p_id, order_in_procedure)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP INDEX procedure_extern_id ON _statement');
        $this->addSql('DROP INDEX procedure_source_statement_id ON _statement');
        $this->addSql('DROP INDEX procedure_order_in_procedure ON _statement');
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
