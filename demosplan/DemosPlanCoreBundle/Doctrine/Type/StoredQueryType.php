<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Doctrine\Type;

use demosplan\DemosPlanCoreBundle\StoredQuery\AssessmentTableQuery;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use demosplan\DemosPlanCoreBundle\StoredQuery\StoredQueryInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType;
use RuntimeException;

/**
 * Handle the storage and retrieval of `StoredQueryInterface`.
 */
class StoredQueryType extends JsonType
{
    final public const DPLAN_STORED_QUERY = 'dplan.stored_query';

    private const TYPE_CLASSES = [
        AssessmentTableQuery::class,
        SegmentListQuery::class,
    ];

    /**
     * Resolves the array fields `query` and `query_format`
     * to the corresponding implementation of `StoredQueryInterface`.
     *
     * @see StoredQueryInterface
     */
    private function loadFromJson(
        array $json,
        string $queryFormat
    ): StoredQueryInterface {
        return collect(self::TYPE_CLASSES)
            ->map(
                static function (string $queryClass) {
                    // explicitly switch the classes to get IDE-findable class uses
                    $query = null;

                    switch ($queryClass) {
                        case AssessmentTableQuery::class:
                            $query = new AssessmentTableQuery();
                            break;

                        case SegmentListQuery::class:
                            $query = new SegmentListQuery();
                            break;
                    }

                    return $query;
                }
            )
            ->filter(
                static fn (StoredQueryInterface $query) => $query->getFormat() === $queryFormat
            )
            ->map(
                static function (StoredQueryInterface $query) use (
                    $json
                ) {
                    $query->fromJson($json);

                    return $query;
                }
            )
            ->first();
    }

    public function convertToPHPValue(
        $value,
        AbstractPlatform $platform
    ): StoredQueryInterface {
        $parsedJson = parent::convertToPHPValue($value, $platform);

        return $this->loadFromJson(
            $parsedJson['query'],
            $parsedJson['format']
        );
    }

    /**
     * Convert a StoredQueryInterface into it's database representation
     * by wrapping it's type and contents into an array.
     *
     * {@inheritdoc}
     *
     * @throws ConversionException On json conversion errors
     */
    public function convertToDatabaseValue(
        $value,
        AbstractPlatform $platform
    ): string {
        if (!is_a($value, StoredQueryInterface::class)) {
            throw new RuntimeException('This field can only handle '.StoredQueryInterface::class.' as data');
        }

        $combinedValue = [
            'format' => $value->getFormat(),
            'query'  => $value->toJson(),
        ];

        return parent::convertToDatabaseValue($combinedValue, $platform);
    }

    public function getName(): string
    {
        return self::DPLAN_STORED_QUERY;
    }
}
