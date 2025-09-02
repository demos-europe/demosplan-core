<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Doctrine\Type;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\MultiSelectField;
use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType;

/**
 * Handle the storage and retrieval of `StoredQueryInterface`.
 */
class CustomFieldType extends JsonType
{
    final public const DPLAN_STORED_QUERY = 'dplan.custom_field_configuration';

    private const TYPE_CLASSES = [
        RadioButtonField::class,
        MultiSelectField::class,
    ];

    public function loadFromJson(
        ?array $json,
    ): ?CustomFieldInterface {
        if (null === $json) {
            return null;
        }

        if (!isset($json['fieldType'])) {
            throw new ConversionException('Missing fieldType in custom field JSON');
        }

        $fieldType = $json['fieldType'];

        return collect(self::TYPE_CLASSES)
            ->map(
                static function (string $fieldType) {
                    // explicitly switch the classes to get IDE-findable class uses
                    $field = null;

                    switch ($fieldType) {
                        case RadioButtonField::class:
                            $field = new RadioButtonField();
                            break;
                        case MultiSelectField::class:
                            $field = new MultiSelectField();
                            break;
                        default:
                            throw new ConversionException("Unhandled custom field class: {$fieldType}");
                    }

                    return $field;
                }
            )
            ->filter(
                static fn (CustomFieldInterface $field) =>  $fieldType === $field->getFormat()
            )
            ->map(
                static function (CustomFieldInterface $field) use (
                    $json
                ) {
                    $field->fromJson($json);

                    return $field;
                }
            )
            ->first();
    }

    public function convertToPHPValue(
        $value,
        AbstractPlatform $platform,
    ): ?CustomFieldInterface {
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
    ): string {
        /* if (!is_a($value, CustomFieldInterface::class)) {
             throw new RuntimeException('This field can only handle '.CustomFieldInterface::class.' as data');
         }

         return parent::convertToDatabaseValue($value->toJson(), $platform);*/

        return parent::convertToDatabaseValue($value->toJson(), $platform);
    }

    public function getName(): string
    {
        return self::DPLAN_STORED_QUERY;
    }
}
