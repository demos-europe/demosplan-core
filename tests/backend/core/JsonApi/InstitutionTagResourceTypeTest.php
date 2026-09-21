<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagResourceType;
use InvalidArgumentException;
use Tests\Base\FunctionalTestCase;

class InstitutionTagResourceTypeTest extends FunctionalTestCase
{
    protected ?InstitutionTagResourceType $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(InstitutionTagResourceType::class);

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);
        $this->enablePermissions(['feature_institution_tag_read']);
    }

    public function testTagOwnedByCurrentCustomerIsAccessible(): void
    {
        // Arrange
        $currentCustomer = CustomerFactory::createOne(['subdomain' => 'current-customer-'.uniqid()]);
        $this->makeCustomerCurrent($currentCustomer->_real());
        $ownTag = InstitutionTagFactory::createOne([
            'category' => InstitutionTagCategoryFactory::new(['customer' => $currentCustomer]),
        ]);

        // Act
        $result = $this->sut->getEntity($ownTag->getId());

        // Assert
        self::assertSame($ownTag->getId(), $result->getId());
    }

    public function testTagOwnedByAnotherCustomerIsNotAccessible(): void
    {
        // Arrange
        $currentCustomer = CustomerFactory::createOne(['subdomain' => 'current-customer-'.uniqid()]);
        $this->makeCustomerCurrent($currentCustomer->_real());
        $otherCustomer = CustomerFactory::createOne(['subdomain' => 'other-customer-'.uniqid()]);
        $foreignTag = InstitutionTagFactory::createOne([
            'category' => InstitutionTagCategoryFactory::new(['customer' => $otherCustomer]),
        ]);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->sut->getEntity($foreignTag->getId());
    }

    private function makeCustomerCurrent(Customer $customer): void
    {
        $globalConfig = $this->getContainer()->get(GlobalConfigInterface::class);
        $globalConfig->setSubdomain($customer->getSubdomain());
    }
}
