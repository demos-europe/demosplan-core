<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldList;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class CustomFieldFactory
{
    public function createCustomField(array $attributes): CustomFieldInterface
    {
        $type = $attributes['fieldType'];
        if (!isset(CustomFieldList::TYPE_CLASSES[$type])) {
            throw new InvalidArgumentException('Unknown custom field type: '.$type);
        }
        $customFieldClass = CustomFieldList::TYPE_CLASSES[$type];
        $customField = new $customFieldClass();

        $customField->setId(Uuid::uuid4()->toString());
        $customField->setFieldType($type);
        $customField->setName($attributes['name']);
        $customField->setDescription($attributes['description']);

        if (isset($attributes['options']) && method_exists($customField, 'setOptions')) {
            $customField->setOptions($attributes['options']);
        }

        return $customField;
    }
}
