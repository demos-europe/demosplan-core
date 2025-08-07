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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldUpdater;
use InvalidArgumentException;
use Tests\Base\UnitTestCase;

class CustomFieldUpdaterTest extends UnitTestCase
{
    /**
     * @var CustomFieldUpdater|null
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldUpdater::class);
    }

    public function testUpdateCustomFieldWithValidNameUpdate(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $customField1 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color1')->create();

        $entityId = $customField1->getId();
        $attributes = ['name' => 'Updated Field Name'];

        // Act
        $result = $this->sut->updateCustomField($entityId, $attributes);

        // Assert
        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertEquals('Updated Field Name', $result->getName());
    }

    public function testUpdateCustomFieldWithValidOptionsUpdate(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton(options: ['yellow', 'green'])->create();

        $updatedOptionId = $customField->getConfiguration()->getOptions()[0]->getId();
        $toBeDeletedOptionId = $customField->getConfiguration()->getOptions()[1]->getId();

        $newOptions = [
            ['id' => $updatedOptionId, 'label' => 'Updated Option 1'],
            ['label' => 'New Option 2'],  // New option without ID
        ];
        $attributes = ['options' => $newOptions];

        // Act
        $result = $this->sut->updateCustomField($customField->getId(), $attributes);

        // Assert
        $newOptionLabels = array_map(static fn ($option) => $option->getLabel(), $result->getOptions());
        $optionIds = array_map(static fn ($option) => $option->getId(), $result->getOptions());

        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertCount(2, $result->getOptions());
        static::assertContains($updatedOptionId, $optionIds);
        static::assertNotContains($toBeDeletedOptionId, $optionIds);
        static::assertContains('Updated Option 1', $newOptionLabels);
        static::assertContains('New Option 2', $newOptionLabels);
    }

    /*public function testUpdateCustomFieldWithEmptyOptionLabelsThrowsInvalidArgumentException(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $customField1 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color1', options: ['green', 'yellow'])->create();

        $entityId = $customField1->getId();
        $attributes['options'] = [
            ['id' => 'option1', 'label' => ''], // Empty label
            ['id' => 'option2', 'label' => 'Valid Option 2']
        ];

        $this->sut->updateCustomField($entityId, $attributes);

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All options must have a non-empty label');

        $this->sut->updateCustomField($entityId, $attributes);
    }*/
}
