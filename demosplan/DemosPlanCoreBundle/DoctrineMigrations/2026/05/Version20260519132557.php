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

/**
 * refs DPLAN-16766: Create procedure_phase_definition table and add nullable phase_definition_id FKs
 * to procedure_phase, _statement, _draft_statement, _draft_statement_versions and institution_mail.
 */
class Version20260519132557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-16766: Add procedure_phase_definition table and nullable FK columns on procedure_phase, _statement, _draft_statement, _draft_statement_versions, institution_mail';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if (!$schema->hasTable('procedure_phase_definition')) {
            $this->addSql('CREATE TABLE procedure_phase_definition (id CHAR(36) NOT NULL, customer_id CHAR(36) DEFAULT NULL, name VARCHAR(255) NOT NULL, audience VARCHAR(25) NOT NULL, permission_set VARCHAR(10) NOT NULL, participation_state VARCHAR(50) DEFAULT NULL, closing_phase TINYINT(1) DEFAULT 0 NOT NULL, order_in_audience INT UNSIGNED DEFAULT 0 NOT NULL, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, INDEX IDX_4C38943C9395C3F3 (customer_id), UNIQUE INDEX uniq_name_customer_audience (name, customer_id, audience), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE procedure_phase_definition ADD CONSTRAINT FK_4C38943C9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE');

            // Seed global "Konfiguration" rows (customer_id IS NULL) so the project-level
            // fallback in customer-agnostic procedures (e.g. master templates without a customer)
            // always finds a matching phase definition.
            foreach (['internal', 'external'] as $audience) {
                $this->addSql(
                    'INSERT INTO procedure_phase_definition
                        (id, customer_id, name, audience, permission_set, participation_state, closing_phase, order_in_audience, creation_date, modification_date)
                        VALUES (UUID(), NULL, :name, :audience, :permissionSet, NULL, 0, 0, NOW(), NOW())',
                    [
                        'name'          => 'Konfiguration',
                        'audience'      => $audience,
                        'permissionSet' => 'hidden',
                    ]
                );
            }
        }

        if ($schema->hasTable('procedure_phase')) {
            $table = $schema->getTable('procedure_phase');
            if (!$table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE procedure_phase ADD phase_definition_id CHAR(36) DEFAULT NULL, ADD designated_phase_definition_id CHAR(36) DEFAULT NULL');
                $this->addSql('ALTER TABLE procedure_phase ADD CONSTRAINT FK_52C669818EFDFE33 FOREIGN KEY (phase_definition_id) REFERENCES procedure_phase_definition (id) ON DELETE RESTRICT');
                $this->addSql('ALTER TABLE procedure_phase ADD CONSTRAINT FK_52C66981694E14D5 FOREIGN KEY (designated_phase_definition_id) REFERENCES procedure_phase_definition (id) ON DELETE RESTRICT');
                $this->addSql('CREATE INDEX IDX_52C669818EFDFE33 ON procedure_phase (phase_definition_id)');
                $this->addSql('CREATE INDEX IDX_52C66981694E14D5 ON procedure_phase (designated_phase_definition_id)');
            }
        }

        if ($schema->hasTable('_statement')) {
            $table = $schema->getTable('_statement');
            if (!$table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE _statement ADD phase_definition_id CHAR(36) DEFAULT NULL');
                $this->addSql('ALTER TABLE _statement ADD CONSTRAINT FK_8D47F06B8EFDFE33 FOREIGN KEY (phase_definition_id) REFERENCES procedure_phase_definition (id) ON DELETE RESTRICT');
                $this->addSql('CREATE INDEX IDX_8D47F06B8EFDFE33 ON _statement (phase_definition_id)');
            }
        }

        if ($schema->hasTable('_draft_statement')) {
            $table = $schema->getTable('_draft_statement');
            if (!$table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE _draft_statement ADD phase_definition_id CHAR(36) DEFAULT NULL');
                $this->addSql('ALTER TABLE _draft_statement ADD CONSTRAINT FK_5D77C03C8EFDFE33 FOREIGN KEY (phase_definition_id) REFERENCES procedure_phase_definition (id) ON DELETE RESTRICT');
                $this->addSql('CREATE INDEX IDX_5D77C03C8EFDFE33 ON _draft_statement (phase_definition_id)');
            }
        }

        if ($schema->hasTable('_draft_statement_versions')) {
            $table = $schema->getTable('_draft_statement_versions');
            if (!$table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE _draft_statement_versions ADD phase_definition_id CHAR(36) DEFAULT NULL');
                $this->addSql('ALTER TABLE _draft_statement_versions ADD CONSTRAINT FK_1C6084CF8EFDFE33 FOREIGN KEY (phase_definition_id) REFERENCES procedure_phase_definition (id) ON DELETE RESTRICT');
                $this->addSql('CREATE INDEX IDX_1C6084CF8EFDFE33 ON _draft_statement_versions (phase_definition_id)');
            }
        }

        if ($schema->hasTable('institution_mail')) {
            $table = $schema->getTable('institution_mail');
            if (!$table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE institution_mail ADD phase_definition_id CHAR(36) DEFAULT NULL');
                $this->addSql('ALTER TABLE institution_mail ADD CONSTRAINT FK_47257C038EFDFE33 FOREIGN KEY (phase_definition_id) REFERENCES procedure_phase_definition (id) ON DELETE RESTRICT');
                $this->addSql('CREATE INDEX IDX_47257C038EFDFE33 ON institution_mail (phase_definition_id)');
            }
        }
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if ($schema->hasTable('institution_mail')) {
            $table = $schema->getTable('institution_mail');
            if ($table->hasForeignKey('FK_47257C038EFDFE33')) {
                $this->addSql('ALTER TABLE institution_mail DROP FOREIGN KEY FK_47257C038EFDFE33');
            }
            if ($table->hasIndex('IDX_47257C038EFDFE33')) {
                $this->addSql('DROP INDEX IDX_47257C038EFDFE33 ON institution_mail');
            }
            if ($table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE institution_mail DROP phase_definition_id');
            }
        }

        if ($schema->hasTable('_draft_statement_versions')) {
            $table = $schema->getTable('_draft_statement_versions');
            if ($table->hasForeignKey('FK_1C6084CF8EFDFE33')) {
                $this->addSql('ALTER TABLE _draft_statement_versions DROP FOREIGN KEY FK_1C6084CF8EFDFE33');
            }
            if ($table->hasIndex('IDX_1C6084CF8EFDFE33')) {
                $this->addSql('DROP INDEX IDX_1C6084CF8EFDFE33 ON _draft_statement_versions');
            }
            if ($table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE _draft_statement_versions DROP phase_definition_id');
            }
        }

        if ($schema->hasTable('_draft_statement')) {
            $table = $schema->getTable('_draft_statement');
            if ($table->hasForeignKey('FK_5D77C03C8EFDFE33')) {
                $this->addSql('ALTER TABLE _draft_statement DROP FOREIGN KEY FK_5D77C03C8EFDFE33');
            }
            if ($table->hasIndex('IDX_5D77C03C8EFDFE33')) {
                $this->addSql('DROP INDEX IDX_5D77C03C8EFDFE33 ON _draft_statement');
            }
            if ($table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE _draft_statement DROP phase_definition_id');
            }
        }

        if ($schema->hasTable('_statement')) {
            $table = $schema->getTable('_statement');
            if ($table->hasForeignKey('FK_8D47F06B8EFDFE33')) {
                $this->addSql('ALTER TABLE _statement DROP FOREIGN KEY FK_8D47F06B8EFDFE33');
            }
            if ($table->hasIndex('IDX_8D47F06B8EFDFE33')) {
                $this->addSql('DROP INDEX IDX_8D47F06B8EFDFE33 ON _statement');
            }
            if ($table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE _statement DROP phase_definition_id');
            }
        }

        if ($schema->hasTable('procedure_phase')) {
            $table = $schema->getTable('procedure_phase');
            if ($table->hasForeignKey('FK_52C669818EFDFE33')) {
                $this->addSql('ALTER TABLE procedure_phase DROP FOREIGN KEY FK_52C669818EFDFE33');
            }
            if ($table->hasForeignKey('FK_52C66981694E14D5')) {
                $this->addSql('ALTER TABLE procedure_phase DROP FOREIGN KEY FK_52C66981694E14D5');
            }
            if ($table->hasIndex('IDX_52C669818EFDFE33')) {
                $this->addSql('DROP INDEX IDX_52C669818EFDFE33 ON procedure_phase');
            }
            if ($table->hasIndex('IDX_52C66981694E14D5')) {
                $this->addSql('DROP INDEX IDX_52C66981694E14D5 ON procedure_phase');
            }
            if ($table->hasColumn('phase_definition_id') && $table->hasColumn('designated_phase_definition_id')) {
                $this->addSql('ALTER TABLE procedure_phase DROP phase_definition_id, DROP designated_phase_definition_id');
            } elseif ($table->hasColumn('phase_definition_id')) {
                $this->addSql('ALTER TABLE procedure_phase DROP phase_definition_id');
            } elseif ($table->hasColumn('designated_phase_definition_id')) {
                $this->addSql('ALTER TABLE procedure_phase DROP designated_phase_definition_id');
            }
        }

        if ($schema->hasTable('procedure_phase_definition')) {
            $table = $schema->getTable('procedure_phase_definition');
            if ($table->hasForeignKey('FK_4C38943C9395C3F3')) {
                $this->addSql('ALTER TABLE procedure_phase_definition DROP FOREIGN KEY FK_4C38943C9395C3F3');
            }
            $this->addSql('DROP TABLE procedure_phase_definition');
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
