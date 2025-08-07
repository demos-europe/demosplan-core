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
}
