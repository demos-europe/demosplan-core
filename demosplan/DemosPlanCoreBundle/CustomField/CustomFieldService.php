<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

class CustomFieldService
{
    private const TYPE_CLASSES = [
        CustomFieldList::class,
    ];

    public function loadFromJson(
        ?array $json,
    ): ?CustomFieldInterface {
        return collect(self::TYPE_CLASSES)
            ->map(
                static function (string $customFieldClass) {
                    // explicitly switch the classes to get IDE-findable class uses
                    $customField = null;

                    if (CustomFieldList::class == $customFieldClass) {
                        $customField = new CustomFieldList();
                    }

                    return new CustomFieldList();
                }
            )
            ->map(
                static function (CustomFieldInterface $customField) use (
                    $json
                ) {
                    $customField->fromJson($json);

                    return $customField;
                }
            )
            ->first();
    }
}
