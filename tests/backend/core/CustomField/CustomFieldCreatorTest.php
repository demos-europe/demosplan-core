<?php
declare(strict_types=1);

namespace Tests\Core\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldCreator;
use Tests\Base\UnitTestCase;

class CustomFieldCreatorTest extends UnitTestCase
{
    /**
     * @var CustomFieldCreator|null
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldCreator::class);
    }

    /**
     * Test SingleSelect field creation with all attributes properly set
     */
    public function testCreateSingleSelectFieldSuccessfully(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $attributes = [
            'fieldType' => 'singleSelect',
            'name' => 'Priority Level',
            'description' => 'Select priority level for this item',
            'options' => [
                ['label' => 'High'],
                ['label' => 'Medium'],
                ['label' => 'Low']
            ],
            'sourceEntity' => 'PROCEDURE',
            'sourceEntityId' => $procedure->getId(),
            'targetEntity' => 'SEGMENT'
        ];

        // Act
        $result = $this->sut->createCustomField($attributes);

        // Assert - Test the superficial layer behavior
        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertInstanceOf(RadioButtonField::class, $result);
        static::assertEquals('Priority Level', $result->getName());
        static::assertEquals('Select priority level for this item', $result->getDescription());
        static::assertEquals('singleSelect', $result->getType());

        // Verify options are properly created with UUIDs
        $options = $result->getOptions();
        static::assertCount(3, $options);
        static::assertEquals('High', $options[0]->getLabel());
        static::assertEquals('Medium', $options[1]->getLabel());
        static::assertEquals('Low', $options[2]->getLabel());

        // Verify all options have UUIDs
        foreach ($options as $option) {
            static::assertNotEmpty($option->getId());
            static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $option->getId());
        }

        // Verify ID is set from configuration
        static::assertNotEmpty($result->getId());
    }


}
