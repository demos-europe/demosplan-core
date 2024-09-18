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

class Version20240912141620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-11379: Add customer_id to _platform_content table';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Temporarily disable foreign key checks
        $this->addSql('SET foreign_key_checks = 0;');

        // Add the customer_id column
        $this->addSql('ALTER TABLE _platform_content ADD customer_id CHAR(36) NOT NULL');

        // Retrieve the first customer ID
        $firstCustomer = $this->connection->fetchAssociative('SELECT _c_id FROM customer LIMIT 1');
        $firstCustomerId = $firstCustomer['_c_id'];

        // Update existing _platform_content entries without customer_id
        $this->addSql('UPDATE _platform_content SET customer_id = :customer_id', [
            'customer_id' => $firstCustomerId,
        ]);

        // Migrating data from _platform_content to _platform_content with customer_id

        // Retrieve all entries from _platform_content
        $platformContentEntries = $this->connection->fetchAllAssociative('SELECT * FROM _platform_content');

        // Retrieve all customer IDs - except first one
        $customerIds = $this->connection->fetchAllAssociative('SELECT _c_id FROM customer WHERE _c_id != :first_customer_id', [
            'first_customer_id' => $firstCustomerId,
        ]);

        foreach ($platformContentEntries as $entry) {
            foreach ($customerIds as $customer) {
                // Generate a unique ID for _pc_id
                $newPcId = $this->connection->fetchOne('SELECT UUID()');

                $this->addSql('INSERT INTO _platform_content SET
    _pc_id = :pc_id,
    _pc_create_date = NOW(),
    _pc_modify_date = NOW(),
    _pc_delete_date = NOW(),
    _pc_type = :_pc_type,
    _pc_title = :_pc_title,
    _pc_description = :_pc_description,
    _pc_text = :_pc_text,
    _pc_picture = :_pc_picture,
    _pc_picture_title = :_pc_picture_title,
    _pc_pdf = :_pc_pdf,
    _pc_pdf_title = :_pc_pdf_title,
    _pc_enabled = :_pc_enabled,
    _pc_deleted = :_pc_deleted,
    customer_id = :customer_id;', [
                    'pc_id'             => $newPcId,
                    '_pc_type'          => $entry['_pc_type'],
                    '_pc_title'         => $entry['_pc_title'],
                    '_pc_description'   => $entry['_pc_description'],
                    '_pc_text'          => $entry['_pc_text'],
                    '_pc_picture'       => $entry['_pc_picture'],
                    '_pc_picture_title' => $entry['_pc_picture_title'],
                    '_pc_pdf'           => $entry['_pc_pdf'],
                    '_pc_pdf_title'     => $entry['_pc_pdf_title'],
                    '_pc_enabled'       => $entry['_pc_enabled'],
                    '_pc_deleted'       => $entry['_pc_deleted'],
                    'customer_id'       => $customer['_c_id'],
                ]);

                $categoryId = $this->connection->fetchOne('SELECT _c_id FROM _platform_content_categories WHERE _pc_id = :current_content_entry_id LIMIT 1', [
                    'current_content_entry_id' => $entry['_pc_id'],
                ]);

                // Insert into _platform_content_categories
                $this->addSql('INSERT INTO _platform_content_categories SET
_pc_id = :platform_content_id,
_c_id = :category_id;', [
                    'platform_content_id' => $newPcId,
                    'category_id'         => $categoryId,
                ]);

                // Retrieve roles for the current content entry
                $roles = $this->connection->fetchAllAssociative('SELECT _r_id FROM _platform_content_roles WHERE _pc_id = :current_content_entry_id', [
                    'current_content_entry_id' => $entry['_pc_id'],
                ]);

                // Insert into _platform_content_roles
                foreach ($roles as $role) {
                    $this->addSql('INSERT INTO _platform_content_roles SET
                _pc_id = :platform_content_id,
                _r_id = :role_id;', [
                        'platform_content_id' => $newPcId,
                        'role_id'             => $role['_r_id'],
                    ]);
                }
            }
        }

        // Enable foreign key checks
        $this->addSql('SET foreign_key_checks = 1;');

        // Add the foreign key constraint
        $this->addSql('ALTER TABLE _platform_content ADD CONSTRAINT FK_42348F4F9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_42348F4F9395C3F3 ON _platform_content (customer_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Retrieve the first customer ID
        $firstCustomer = $this->connection->fetchAssociative('SELECT _c_id FROM customer LIMIT 1');
        $firstCustomerId = $firstCustomer['_c_id'];

        // Delete the entries in _platform_content_categories that were inserted for each customer except the first one
        $this->addSql('DELETE FROM _platform_content_categories WHERE _pc_id IN (SELECT _pc_id FROM _platform_content WHERE customer_id != :first_customer_id)', [
            'first_customer_id' => $firstCustomerId,
        ]);

        // Delete the entries in _platform_content_roles that were inserted for each customer except the first one
        $this->addSql('DELETE FROM _platform_content_roles WHERE _pc_id IN (SELECT _pc_id FROM _platform_content WHERE customer_id != :first_customer_id)', [
            'first_customer_id' => $firstCustomerId,
        ]);

        // Delete the entries in _platform_content that were inserted for each customer except the first one
        $this->addSql('DELETE FROM _platform_content WHERE customer_id != :first_customer_id', [
            'first_customer_id' => $firstCustomerId,
        ]);

        // Drop the foreign key constraint and index
        $this->addSql('ALTER TABLE _platform_content DROP FOREIGN KEY FK_42348F4F9395C3F3');
        $this->addSql('DROP INDEX IDX_42348F4F9395C3F3 ON _platform_content');

        // Drop the customer_id column
        $this->addSql('ALTER TABLE _platform_content DROP customer_id');
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
