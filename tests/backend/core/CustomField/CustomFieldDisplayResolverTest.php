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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldDisplayResolver;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use Tests\Base\FunctionalTestCase;

class CustomFieldDisplayResolverTest extends FunctionalTestCase
{
    private ?CustomFieldDisplayResolver $sut = null;
    private $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldDisplayResolver::class);
        $this->procedure = ProcedureFactory::createOne();
    }

    public function testResolveForDisplayReturnsEmptyArrayForEmptyValues(): void
    {
        $result = $this->sut->resolveForDisplay(
            new CustomFieldValuesList(),
            CustomFieldSupportedEntity::procedure,
            $this->procedure->getId(),
            CustomFieldSupportedEntity::statement
        );

        static::assertSame([], $result);
    }

    public function testResolveForDisplayReturnsSingleSelectFieldLabel(): void
    {
        // Arrange
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity(CustomFieldSupportedEntity::statement->value)
            ->asRadioButton('Priority', options: ['High', 'Medium', 'Low'])
            ->create();

        $selectedOption = $customField->getConfiguration()->getOptions()[0];
        $values = $this->buildValuesList($customField->getId(), $selectedOption->getId());

        // Act
        $result = $this->sut->resolveForDisplay(
            $values,
            CustomFieldSupportedEntity::procedure,
            $this->procedure->getId(),
            CustomFieldSupportedEntity::statement
        );

        // Assert
        static::assertCount(1, $result);
        static::assertSame($customField->getConfiguration()->getName(), $result[0]['name']);
        static::assertSame($selectedOption->getLabel(), $result[0]['value']);
    }

    public function testResolveForDisplayReturnsMultiSelectLabelsJoined(): void
    {
        // Arrange
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity(CustomFieldSupportedEntity::statement->value)
            ->asMultiSelect('Topics', options: ['Environment', 'Traffic', 'Housing'])
            ->create();

        $optionA = $customField->getConfiguration()->getOptions()[0]; // 'Environment'
        $optionB = $customField->getConfiguration()->getOptions()[2]; // 'Housing'

        $values = $this->buildValuesList($customField->getId(), [$optionA->getId(), $optionB->getId()]);

        // Act
        $result = $this->sut->resolveForDisplay(
            $values,
            CustomFieldSupportedEntity::procedure,
            $this->procedure->getId(),
            CustomFieldSupportedEntity::statement
        );

        // Assert
        static::assertCount(1, $result);
        static::assertSame($customField->getConfiguration()->getName(), $result[0]['name']);
        static::assertSame($optionA->getLabel().', '.$optionB->getLabel(), $result[0]['value']);
    }

    public function testResolveForDisplaySkipsValueWithUnknownFieldId(): void
    {
        // Arrange — value references a config ID that does not exist in the DB
        $values = $this->buildValuesList('non-existent-config-uuid', 'some-option-id');

        // Act
        $result = $this->sut->resolveForDisplay(
            $values,
            CustomFieldSupportedEntity::procedure,
            $this->procedure->getId(),
            CustomFieldSupportedEntity::statement
        );

        // Assert — unknown IDs are silently skipped, no exception thrown
        static::assertSame([], $result);
    }

    public function testResolveForDisplayResolvesMultipleFieldsTogether(): void
    {
        // Arrange
        $radioField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity(CustomFieldSupportedEntity::statement->value)
            ->asRadioButton('Status', options: ['Open', 'Closed'])
            ->create();

        $multiField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity(CustomFieldSupportedEntity::statement->value)
            ->asMultiSelect('Tags', options: ['Urgent', 'Review', 'Done'])
            ->create();

        $selectedRadioOption = $radioField->getConfiguration()->getOptions()[1];   // 'Closed'
        $selectedMultiOptionA = $multiField->getConfiguration()->getOptions()[0];  // 'Urgent'
        $selectedMultiOptionB = $multiField->getConfiguration()->getOptions()[2];  // 'Done'

        $values = new CustomFieldValuesList();
        $values->addCustomFieldValue($this->buildFieldValue($radioField->getId(), $selectedRadioOption->getId()));
        $values->addCustomFieldValue($this->buildFieldValue($multiField->getId(), [$selectedMultiOptionA->getId(), $selectedMultiOptionB->getId()]));

        // Act
        $result = $this->sut->resolveForDisplay(
            $values,
            CustomFieldSupportedEntity::procedure,
            $this->procedure->getId(),
            CustomFieldSupportedEntity::statement
        );

        // Assert
        static::assertCount(2, $result);

        $resultByName = array_column($result, null, 'name');

        static::assertArrayHasKey($radioField->getConfiguration()->getName(), $resultByName);
        static::assertSame(
            $selectedRadioOption->getLabel(),
            $resultByName[$radioField->getConfiguration()->getName()]['value']
        );

        static::assertArrayHasKey($multiField->getConfiguration()->getName(), $resultByName);
        static::assertSame(
            $selectedMultiOptionA->getLabel().', '.$selectedMultiOptionB->getLabel(),
            $resultByName[$multiField->getConfiguration()->getName()]['value']
        );
    }

    private function buildValuesList(string $configId, mixed $value): CustomFieldValuesList
    {
        $values = new CustomFieldValuesList();
        $values->addCustomFieldValue($this->buildFieldValue($configId, $value));

        return $values;
    }

    private function buildFieldValue(string $configId, mixed $value): CustomFieldValue
    {
        $fieldValue = new CustomFieldValue();
        $fieldValue->setId($configId);
        $fieldValue->setValue($value);

        return $fieldValue;
    }
}
