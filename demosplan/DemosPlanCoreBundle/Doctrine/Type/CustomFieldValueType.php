<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Doctrine\Type;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType;

/**
 * Handle the storage and retrieval of `CustomFieldValuesList`.
 */
class CustomFieldValueType extends JsonType
{
    final public const DPLAN_STORED_QUERY = 'dplan.custom_fields_value';
    private const TYPE_CLASSES = [
        CustomFieldValuesList::class,
    ];

    public function loadFromJson(
        ?array $json,
    ): ?CustomFieldValuesList {
        if (null === $json) {
            return null;
        }

        return collect(self::TYPE_CLASSES)
            ->map(
                static function (string $customFieldClass) {
                    if (CustomFieldValuesList::class === $customFieldClass) {
                        return new CustomFieldValuesList();
                    }

                    throw new InvalidArgumentException(sprintf('CustomFieldValueListType does not support %s', $customFieldClass));
                }
            )
            ->map(
                static function (?CustomFieldValuesList $customField) use (
                    $json
                ) {
                    if (null === $customField) {
                        return null;
                    }

                    $customField->fromJson($json);

                    return $customField;
                }
            )
            ->first();
    }

    public function convertToPHPValue(
        $value,
        AbstractPlatform $platform,
    ): ?CustomFieldValuesList {
        $parsedJson = parent::convertToPHPValue($value, $platform);

        return $this->loadFromJson($parsedJson);
    }

    /**
     * Convert a CustomFieldInterface into it's database representation
     * by wrapping it's type and contents into an array.
     *
     * {@inheritdoc}
     *
     * @throws ConversionException On json conversion errors
     */
    public function convertToDatabaseValue(
        $value,
        AbstractPlatform $platform,
    ): ?string {
        if (null === $value) {
            return parent::convertToDatabaseValue($value, $platform);
        }

        if (!is_a($value, CustomFieldValuesList::class)) {
            throw new RuntimeException('This field can only handle '.CustomFieldValuesList::class.' as data');
        }

        return parent::convertToDatabaseValue($value->toJson(), $platform);
    }

    public function getName(): string
    {
        return self::DPLAN_STORED_QUERY;
    }
}
