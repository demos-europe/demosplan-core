<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\RoleFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValidator;
use Tests\Base\JsonApiTest;
use Zenstruck\Foundry\Persistence\Proxy;

class CustomFieldResourceTypeTest extends JsonApiTest
{
    private User|Proxy|null $user;
    private Role|Proxy|null $role;
    private Customer|Proxy|null $customer;

    public function testCreateCustomField(): void
    {
        $procedure = ProcedureFactory::createOne();

        $this->user = UserFactory::createOneWithCompletedProfile();
        $this->customer = CustomerFactory::createOne();
        $this->role = RoleFactory::createOne([
            'name'      => Role::PLANNING_AGENCY_ADMIN,
            'code'      => Role::PLANNING_AGENCY_ADMIN,
            'groupCode' => Role::GLAUTH,
            'groupName' => Role::GLAUTH,
        ]);
        $this->user->setDplanroles([$this->role->_real()]);
        $this->user->_save();
        $this->user->setCurrentCustomer($this->customer->_real());
        $this->user->_save();
        $this->tokenStorage = $this->getContainer()->get('security.token_storage');

        $this->enablePermissions(['area_admin_custom_fields', 'feature_json_api_create']);

        $user = $this->user->_real();

        $data = [
            'type'       => 'CustomField',
            'attributes' => [
                'fieldType'      => 'singleSelect',
                'name'           => 'Beschlussvorschlag',
                'description'    => 'This is a description for this custom field',
                'options'        => ['Wird gefolgt', 'Wird nicht gefolgt', 'Zur Kenntnis genommen'],
                'targetEntity'   => 'SEGMENT',
                'sourceEntity'   => 'PROCEDURE',
                'sourceEntityId' => $procedure->getId(),
            ],
        ];

        $result = $this->executeCreationRequest(
            'CustomField',
            $user,
            ['data' => $data],
            $procedure->_real()
        );

        static::assertArrayHasKey('data', $result);
        static::assertSame('CustomField', $result['data']['type']);
        static::assertNotEmpty($result['data']['id']);
        static::assertArrayHasKey('attributes', $result['data']);

        // Check each attribute individually
        static::assertSame('singleSelect', $result['data']['attributes']['fieldType']);
        static::assertSame('Beschlussvorschlag', $result['data']['attributes']['name']);
        static::assertSame('This is a description for this custom field', $result['data']['attributes']['description']);
        static::assertSame(['Wird gefolgt', 'Wird nicht gefolgt', 'Zur Kenntnis genommen'], $result['data']['attributes']['options']);
    }
}
