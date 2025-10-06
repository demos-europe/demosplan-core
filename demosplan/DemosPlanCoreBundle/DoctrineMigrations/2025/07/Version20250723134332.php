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
 * Transform custom field options from string arrays to objects with IDs.
 *
 * Before: options: ["Option 1", "Option 2"]
 * After:  options: [{"id": "uuid1", "label": "Option 1"}, {"id": "uuid2", "label": "Option 2"}]
 */
class Version20250723134332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transform custom field options from labels to ID-based objects';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Step 1: Transform configurations and build label→ID mapping
        $labelToIdMapping = [];
        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, configuration FROM custom_field_configuration'
        );

        foreach ($configs as $config) {
            $data = json_decode((string) $config['configuration'], true);
            if (!isset($data['options']) || !is_array($data['options'])) {
                continue;
            }

            // Convert string options to objects and build mapping
            $newOptions = [];
            foreach ($data['options'] as $label) {
                $optionId = Uuid::uuid4()->toString();
                $newOptions[] = ['id' => $optionId, 'label' => $label];
                $labelToIdMapping[$config['id'].'::'.$label] = $optionId;
            }

            $data['options'] = $newOptions;
            $this->addSql(
                'UPDATE custom_field_configuration SET configuration = ? WHERE id = ?',
                [json_encode($data), $config['id']]
            );
        }

        // Step 2: Update segment values from labels to IDs
        $segments = $this->connection->fetchAllAssociative(
            'SELECT _st_id, custom_fields FROM _statement
               WHERE entity_type = \'Segment\' AND custom_fields IS NOT NULL'
        );

        foreach ($segments as $segment) {
            $fields = json_decode((string) $segment['custom_fields'], true);
            if (!is_array($fields)) {
                continue;
            }

            $updated = false;
            foreach ($fields as &$field) {
                $key = $field['id'].'::'.$field['value'];
                if (isset($labelToIdMapping[$key])) {
                    $field['value'] = $labelToIdMapping[$key];
                    $updated = true;
                }
            }

            if ($updated) {
                $this->addSql(
                    'UPDATE _statement SET custom_fields = ? WHERE _st_id = ?',
                    [json_encode($fields), $segment['_st_id']]
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

        // Step 1: Build ID→label mapping and revert configurations
        $idToLabelMapping = [];
        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, configuration FROM custom_field_configuration'
        );

        foreach ($configs as $config) {
            $data = json_decode((string) $config['configuration'], true);
            if (!isset($data['options']) || !is_array($data['options'])) {
                continue;
            }

            // Convert objects back to strings and build mapping
            $labels = [];
            foreach ($data['options'] as $option) {
                if (isset($option['id'], $option['label'])) {
                    $idToLabelMapping[$config['id'].'::'.$option['id']] = $option['label'];
                    $labels[] = $option['label'];
                }
            }

            $data['options'] = $labels;
            $this->addSql(
                'UPDATE custom_field_configuration SET configuration = ? WHERE id = ?',
                [json_encode($data), $config['id']]
            );
        }

        // Step 2: Update segment values from IDs back to labels
        $segments = $this->connection->fetchAllAssociative(
            'SELECT _st_id, custom_fields FROM _statement
               WHERE entity_type = \'Segment\' AND custom_fields IS NOT NULL'
        );

        foreach ($segments as $segment) {
            $fields = json_decode((string) $segment['custom_fields'], true);
            if (!is_array($fields)) {
                continue;
            }

            $updated = false;
            foreach ($fields as &$field) {
                $key = $field['id'].'::'.$field['value'];
                if (isset($idToLabelMapping[$key])) {
                    $field['value'] = $idToLabelMapping[$key];
                    $updated = true;
                }
            }

            if ($updated) {
                $this->addSql(
                    'UPDATE _statement SET custom_fields = ? WHERE _st_id = ?',
                    [json_encode($fields), $segment['_st_id']]
                );
            }
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
