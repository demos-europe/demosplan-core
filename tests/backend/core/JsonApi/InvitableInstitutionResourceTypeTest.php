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
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitableInstitutionResourceType;
use EDT\Wrapping\EntityData;
use Tests\Base\FunctionalTestCase;

class InvitableInstitutionResourceTypeTest extends FunctionalTestCase
{
    protected ?InvitableInstitutionResourceType $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(InvitableInstitutionResourceType::class);

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);
        $this->enablePermissions([
            'feature_institution_tag_assign',
            'feature_institution_tag_read',
            'feature_institution_tag_update',
        ]);
    }

    /**
     * An institution can be a member of several customers and carries the tags of all of them,
     * while a request only ever covers the current customer. Sending the tags of the current
     * customer must not drop the tags an institution holds elsewhere.
     */
    public function testUpdateKeepsTagsOfOtherCustomers(): void
    {
        // Arrange
        $institution = $this->getInvitableInstitution();
        $currentCustomer = $this->getCurrentCustomerOf($institution);
        $this->makeCustomerCurrent($currentCustomer);

        $ownTag = InstitutionTagFactory::createOne([
            'category' => InstitutionTagCategoryFactory::new(['customer' => $currentCustomer]),
        ])->_real();
        $foreignTag = InstitutionTagFactory::createOne([
            'category' => InstitutionTagCategoryFactory::new([
                'customer' => CustomerFactory::new(['subdomain' => 'other-customer-'.uniqid()]),
            ]),
        ])->_real();

        $institution->addAssignedTag($ownTag);
        $institution->addAssignedTag($foreignTag);
        $this->getEntityManager()->flush();

        // Act
        $this->sut->updateEntity(
            $institution->getId(),
            new EntityData('InvitableInstitution', [], [], ['assignedTags' => []])
        );
        $this->getEntityManager()->flush();

        // Assert
        $assignedTagIds = $institution->getAssignedTags()
            ->map(static fn (InstitutionTag $tag): string => $tag->getId())
            ->toArray();
        self::assertNotContains($ownTag->getId(), $assignedTagIds);
        self::assertContains($foreignTag->getId(), $assignedTagIds);
    }

    public function testUpdateRemovesTagsOfCurrentCustomer(): void
    {
        // Arrange
        $institution = $this->getInvitableInstitution();
        $currentCustomer = $this->getCurrentCustomerOf($institution);
        $this->makeCustomerCurrent($currentCustomer);

        $keptTag = InstitutionTagFactory::createOne([
            'category' => InstitutionTagCategoryFactory::new(['customer' => $currentCustomer]),
        ])->_real();
        $removedTag = InstitutionTagFactory::createOne([
            'category' => InstitutionTagCategoryFactory::new(['customer' => $currentCustomer]),
        ])->_real();

        $institution->addAssignedTag($keptTag);
        $institution->addAssignedTag($removedTag);
        $this->getEntityManager()->flush();

        // Act
        $this->sut->updateEntity(
            $institution->getId(),
            new EntityData('InvitableInstitution', [], [], [
                'assignedTags' => [['type' => 'InstitutionTag', 'id' => $keptTag->getId()]],
            ])
        );
        $this->getEntityManager()->flush();

        // Assert
        $assignedTagIds = $institution->getAssignedTags()
            ->map(static fn (InstitutionTag $tag): string => $tag->getId())
            ->toArray();
        self::assertContains($keptTag->getId(), $assignedTagIds);
        self::assertNotContains($removedTag->getId(), $assignedTagIds);
    }

    private function getInvitableInstitution(): Orga
    {
        return $this->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY);
    }

    private function getCurrentCustomerOf(Orga $institution): Customer
    {
        return $institution->getStatusInCustomers()->first()->getCustomer();
    }

    private function makeCustomerCurrent(Customer $customer): void
    {
        $globalConfig = $this->getContainer()->get(GlobalConfigInterface::class);
        $globalConfig->setSubdomain($customer->getSubdomain());
    }
}
