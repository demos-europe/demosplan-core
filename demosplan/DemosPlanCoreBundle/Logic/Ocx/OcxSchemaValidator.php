<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use demosplan\DemosPlanCoreBundle\Exception\OcxProcessingException;
use demosplan\DemosPlanCoreBundle\Types\OcxEntryKind;
use JsonException;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Validates request and response bodies against the OCX JSON Schemas shipped with the
 * `demos-europe/ocx` package.
 */
class OcxSchemaValidator
{
    public const MANIFEST = 'manifest';
    public const ENTRIES = 'entries';
    public const DELIVERY = 'delivery';
    public const PROBLEM = 'problem';
    public const CAPABILITIES = 'capabilities';

    private const MAX_VIOLATIONS = 50;

    private ?SchemaStorage $schemaStorage = null;

    /**
     * @var array<string, string> schema name =>
     */
    private array $schemaIds = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/vendor/demos-europe/ocx/schemas/0.1')]
        private readonly string $schemaDirectory,
    ) {
    }

    /**
     * @param mixed $data decoded JSON with objects as stdClass
     *
     * @return list<array{pointer: string, message: string}> empty if valid
     *
     * @throws JsonException
     */
    public function validate(mixed $data, string $schemaName): array
    {
        $this->getSchemaStorage();
        $schemaId = $this->schemaIds[$schemaName] ?? throw new OcxProcessingException('Unknown OCX schema '.$schemaName);

        if (self::ENTRIES === $schemaName && is_object($data) && is_array($data->entries ?? null)) {
            return $this->validateEntriesDocument($data, $schemaId);
        }

        return array_slice($this->violations($data, $schemaId), 0, self::MAX_VIOLATIONS);
    }

    /**
     * The library ignores `discriminator` and reports every entry against all kinds. Validate each
     * entry against the schema of its own kind instead, and the envelope without the entries.
     *
     * @return list<array{pointer: string, message: string}>
     *
     * @throws JsonException
     */
    private function validateEntriesDocument(object $data, string $schemaId): array
    {
        $envelope = clone $data;
        $envelope->entries = [];
        $violations = $this->violations($envelope, $schemaId);

        foreach ($data->entries as $index => $entry) {
            $kind = is_object($entry) ? ($entry->kind ?? null) : null;
            if (!is_string($kind) || null === OcxEntryKind::tryFrom($kind)) {
                $violations[] = [
                    'pointer' => '/entries/'.$index.'/kind',
                    'message' => 'Must be one of: '.implode(', ', array_column(OcxEntryKind::cases(), 'value')),
                ];
                continue;
            }
            foreach ($this->violations($entry, $schemaId.'#/$defs/'.$kind.'Entry') as $violation) {
                $violations[] = [
                    'pointer' => '/entries/'.$index.$violation['pointer'],
                    'message' => $violation['message'],
                ];
            }
            if (count($violations) >= self::MAX_VIOLATIONS) {
                break;
            }
        }

        return array_slice($violations, 0, self::MAX_VIOLATIONS);
    }

    /**
     * @return list<array{pointer: string, message: string}>
     *
     * @throws JsonException
     */
    private function violations(mixed $data, string $schemaRef): array
    {
        $validator = new Validator(new Factory($this->getSchemaStorage()));
        $validator->validate($data, (object) ['$ref' => $schemaRef]);

        return array_map(
            static fn (array $error): array => ['pointer' => (string) $error['pointer'], 'message' => (string) $error['message']],
            $validator->getErrors()
        );
    }

    /**
     * @throws JsonException
     */
    private function getSchemaStorage(): SchemaStorage
    {
        if (null !== $this->schemaStorage) {
            return $this->schemaStorage;
        }

        if (!is_dir($this->schemaDirectory)) {
            throw new OcxProcessingException('OCX schemas not found in '.$this->schemaDirectory);
        }

        $storage = new SchemaStorage();
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->schemaDirectory));
        foreach ($files as $file) {
            if (!str_ends_with($file->getFilename(), '.schema.json')) {
                continue;
            }
            $schema = json_decode((string) file_get_contents($file->getPathname()), false, 512, JSON_THROW_ON_ERROR);
            $storage->addSchema($schema->{'$id'}, $schema);
            $this->schemaIds[basename($file->getFilename(), '.schema.json')] = $schema->{'$id'};
        }

        return $this->schemaStorage = $storage;
    }
}
