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

        $this->transformConfigurations();
        $this->transformSegmentValues();
    }


    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->revertSegmentValues();
        $this->revertConfigurations();
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

    private function transformConfigurations(): void
    {
        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, configuration FROM custom_field_configuration'
        );


        foreach ($configs as $config) {
            $data = json_decode($config['configuration'], true);

            if (!isset($data['options']) || !is_array($data['options'])) {
                continue;
            }

            $newOptions = [];
            foreach ($data['options'] as $label) {
                $newOptions[] = [
                    'id' => Uuid::uuid4()->toString(),
                    'label' => $label
                ];
            }

            $data['options'] = $newOptions;

            $this->addSql(
                'UPDATE custom_field_configuration SET configuration = ? WHERE id = ?',
                [json_encode($data), $config['id']]
            );
        }
    }

    private function transformSegmentValues(): void
    {
        // Build label → ID mapping
        $mapping = [];
        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, configuration FROM custom_field_configuration'
        );

        foreach ($configs as $config) {
            $data = json_decode($config['configuration'], true);
            if (!isset($data['options'])) continue;

            foreach ($data['options'] as $option) {
                if (isset($option['id'], $option['label'])) {
                    $key = $config['id'] . '::' . $option['label'];
                    $mapping[$key] = $option['id'];
                }
            }
        }

        // Update segment values
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

    private function revertConfigurations(): void
    {
        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, configuration FROM custom_field_configuration'
        );

        foreach ($configs as $config) {
            $data = json_decode($config['configuration'], true);
            if (!isset($data['options'])) continue;

            $labels = [];
            foreach ($data['options'] as $option) {
                if (isset($option['label'])) {
                    $labels[] = $option['label'];
                }
            }

            $data['options'] = $labels;

            $this->addSql(
                'UPDATE custom_field_configuration SET configuration = ? WHERE id = ?',
                [json_encode($data), $config['id']]
            );
        }
    }

    private function revertSegmentValues(): void
    {
        // Build ID → label mapping
        $mapping = [];
        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, configuration FROM custom_field_configuration'
        );

        foreach ($configs as $config) {
            $data = json_decode($config['configuration'], true);
            if (!isset($data['options'])) continue;

            foreach ($data['options'] as $option) {
                if (isset($option['id'], $option['label'])) {
                    $key = $config['id'] . '::' . $option['id'];
                    $mapping[$key] = $option['label'];
                }
            }
        }

        // Update segments back to labels
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



}
