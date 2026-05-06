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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

class CustomFieldResourceTypeSecurityTest extends JsonApiTest
{
    /**
     * A CUSTOMER-scoped field whose sourceEntityId does not match the current customer
     * must not appear in the list — even when the caller knows its ID.
     */
    public function testListDoesNotExposeOtherCustomersFields(): void
    {
        $foreignCustomer = CustomerFactory::createOne();

        $foreignField = CustomFieldConfigurationFactory::new()
            ->asTextField()
            ->create([
                'sourceEntityClass' => 'CUSTOMER',
                'sourceEntityId'    => $foreignCustomer->getId(),
            ]);

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $response = $this->executeListRequest(
            'CustomField',
            $user,
            null,
            Response::HTTP_OK,
            ['filter[sourceEntity]' => 'CUSTOMER']
        );

        $returnedIds = array_column($response['data'], 'id');

        self::assertNotContains(
            $foreignField->getId(),
            $returnedIds,
            'CUSTOMER-scoped field belonging to another customer must not be visible'
        );
    }

    /**
     * Whatever sourceEntityId the client sends for a CUSTOMER-source field,
     * the server must silently overwrite it with the authenticated customer's ID.
     */
    public function testCreateIgnoresClientSuppliedSourceEntityIdForCustomerSource(): void
    {
        $spoofedCustomer = CustomerFactory::createOne();

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $response = $this->executeCreationRequest(
            'CustomField',
            $user,
            [
                'data' => [
                    'type'       => 'CustomField',
                    'attributes' => [
                        'name'           => 'Spoofed Field',
                        'fieldType'      => 'text',
                        'description'    => 'Security test',
                        'sourceEntity'   => 'CUSTOMER',
                        'targetEntity'   => 'ORGA',
                        'sourceEntityId' => $spoofedCustomer->getId(),
                    ],
                ],
            ]
        );

        // sourceEntityId is not exposed in the JSON:API response — verify the stored record
        $createdId    = $response['data']['id'];
        $storedConfig = $this->getContainer()
            ->get(CustomFieldConfigurationRepository::class)
            ->find($createdId);

        $currentCustomer = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer();

        self::assertSame(
            $currentCustomer->getId(),
            $storedConfig->getSourceEntityId(),
            'sourceEntityId must be the authenticated customer, not the client-supplied value'
        );
        self::assertNotSame(
            $spoofedCustomer->getId(),
            $storedConfig->getSourceEntityId(),
            'Client-supplied sourceEntityId must have been rejected'
        );
    }
}
