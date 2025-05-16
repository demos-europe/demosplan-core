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
use Tests\Base\JsonApiTest;
use Zenstruck\Foundry\Persistence\Proxy;

class CustomFieldResourceTypeTest extends JsonApiTest
{
    private User|Proxy|null $user;
    private Role|Proxy|null $role;
    private Customer|Proxy|null $customer;

    public function testCreateCustomField(): void
    {
        $this->setUpHttpClient();
        $this->user = UserFactory::createOne();
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
        $this->logIn($this->user->_real());

        $this->enablePermissions(['area_admin_custom_fields', 'feature_json_api_create']);

        $user = $this->user->_real();

        $procedure = ProcedureFactory::createOne();

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
    }
}
