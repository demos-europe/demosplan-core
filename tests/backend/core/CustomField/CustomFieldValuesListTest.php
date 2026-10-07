<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use PHPUnit\Framework\TestCase;

class CustomFieldValuesListTest extends TestCase
{
    public function testGetOptionIdsReturnsTheSingleOptionOfASingleSelectField(): void
    {
        $list = $this->buildList(['field-1' => 'option-a']);

        self::assertSame(['option-a'], $list->getOptionIds('field-1'));
    }

    public function testGetOptionIdsReturnsAllOptionsOfAMultiSelectField(): void
    {
        $list = $this->buildList(['field-1' => ['option-a', 'option-b']]);

        self::assertSame(['option-a', 'option-b'], $list->getOptionIds('field-1'));
    }

    public function testGetOptionIdsReturnsNothingForAFieldWithoutValue(): void
    {
        $list = $this->buildList(['field-1' => 'option-a']);

        self::assertSame([], $list->getOptionIds('field-2'));
        self::assertSame([], (new CustomFieldValuesList())->getOptionIds('field-1'));
    }

    public function testGetOptionIdsIgnoresEmptyAndNonStringValues(): void
    {
        $list = $this->buildList(['field-1' => ['', null, 5, 'option-a'], 'field-2' => null, 'field-3' => '']);

        self::assertSame(['option-a'], $list->getOptionIds('field-1'));
        self::assertSame([], $list->getOptionIds('field-2'));
        self::assertSame([], $list->getOptionIds('field-3'));
    }

    /**
     * @param array<string, mixed> $valuesByFieldId
     */
    private function buildList(array $valuesByFieldId): CustomFieldValuesList
    {
        $list = new CustomFieldValuesList();
        foreach ($valuesByFieldId as $fieldId => $value) {
            $customFieldValue = new CustomFieldValue();
            $customFieldValue->setId($fieldId);
            $customFieldValue->setValue($value);
            $list->addCustomFieldValue($customFieldValue);
        }

        return $list;
    }
}
