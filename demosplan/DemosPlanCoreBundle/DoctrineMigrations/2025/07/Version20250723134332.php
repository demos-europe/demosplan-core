<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;


class Version20250723134332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transform custom field options to ID-based format';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->transformCustomFields();
    }



    private function transformCustomFields(): void
    {
        $mapping = [];

        // Get all configurations and transform them while building mapping
        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, configuration FROM custom_field_configuration'
        );

        foreach ($configs as $config) {
            $data = json_decode($config['configuration'], true);

            if (!isset($data['options']) || !is_array($data['options'])) {
                continue;
            }

            // Transform string options to object options and build mapping
            $newOptions = [];
            foreach ($data['options'] as $label) {
                $id = Uuid::uuid4()->toString();
                $newOptions[] = [
                    'id' => $id,
                    'label' => $label
                ];

                // Build mapping for segment transformation
                $key = $config['id'] . '::' . $label;
                $mapping[$key] = $id;
            }

            $data['options'] = $newOptions;

            // Update configuration
            $this->addSql(
                'UPDATE custom_field_configuration SET configuration = ? WHERE id = ?',
                [json_encode($data), $config['id']]
            );
        }

        // Now update segment values using the mapping
        $segments = $this->connection->fetchAllAssociative(
            'SELECT _st_id, custom_fields FROM _statement
             WHERE entity_type = "Segment" AND custom_fields IS NOT NULL'
        );

        foreach ($segments as $segment) {
            $fields = json_decode($segment['custom_fields'], true);
            if (!is_array($fields)) continue;

            $updated = false;
            foreach ($fields as &$field) {
                $key = $field['id'] . '::' . $field['value'];

                if (isset($mapping[$key])) {
                    $field['value'] = $mapping[$key];
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
        $this->revertCustomFields();
    }

    private function revertCustomFields(): void
    {
        $mapping = [];

        // Get all configurations and build ID→label mapping
        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, configuration FROM custom_field_configuration'
        );

        foreach ($configs as $config) {
            $data = json_decode($config['configuration'], true);

            if (!isset($data['options']) || !is_array($data['options'])) {
                continue;
            }

            // Build mapping for segment reversion and convert back to string array
            $labels = [];
            foreach ($data['options'] as $option) {
                if (isset($option['id'], $option['label'])) {
                    // Build mapping: configId::optionId -> optionLabel
                    $key = $config['id'] . '::' . $option['id'];
                    $mapping[$key] = $option['label'];
                    $labels[] = $option['label'];
                }
            }

            $data['options'] = $labels;

            // Update configuration back to string array
            $this->addSql(
                'UPDATE custom_field_configuration SET configuration = ? WHERE id = ?',
                [json_encode($data), $config['id']]
            );
        }

        // Now revert segment values using the mapping
        $segments = $this->connection->fetchAllAssociative(
            'SELECT _st_id, custom_fields FROM _statement
             WHERE entity_type = "Segment" AND custom_fields IS NOT NULL'
        );

        foreach ($segments as $segment) {
            $fields = json_decode($segment['custom_fields'], true);
            if (!is_array($fields)) continue;

            $updated = false;
            foreach ($fields as &$field) {
                $key = $field['id'] . '::' . $field['value'];

                if (isset($mapping[$key])) {
                    $field['value'] = $mapping[$key];
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
