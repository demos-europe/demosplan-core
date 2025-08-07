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

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldUpdater;
use Tests\Base\UnitTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

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
