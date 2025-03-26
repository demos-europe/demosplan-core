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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldList;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType;
use RuntimeException;

/**
 * Handle the storage and retrieval of `StoredQueryInterface`.
 */
class CustomFieldType extends JsonType
{
    final public const DPLAN_STORED_QUERY = 'dplan.segment_custom_fields_template';

    private const TYPE_CLASSES = [
        CustomFieldList::class,
    ];

    /**
     */
    public function loadFromJson(
        ?array $json
    ): ?CustomFieldInterface {

        if (null === $json){
            return null;
        }


        return collect(self::TYPE_CLASSES)
            ->map(
                static function (string $customFieldClass) {
                    // explicitly switch the classes to get IDE-findable class uses
                    $customField = null;


                    if ($customFieldClass == CustomFieldList::class) {
                        $customField = new CustomFieldList();
                    }

                    return new CustomFieldList();
                }
            )
            ->map(
                static function (?CustomFieldInterface $customField) use (
                    $json
                ) {

                    if ($customField == null) {
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
        AbstractPlatform $platform
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
        AbstractPlatform $platform
    ): string {
        if (!is_a($value, CustomFieldInterface::class)) {
            throw new RuntimeException('This field can only handle '.CustomFieldInterface::class.' as data');
        }

        return parent::convertToDatabaseValue($value->toJson(), $platform);
    }

    public function getName(): string
    {
        return self::DPLAN_STORED_QUERY;
    }
}
