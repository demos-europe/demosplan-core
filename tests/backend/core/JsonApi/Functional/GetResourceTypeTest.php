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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\RoleFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagResourceType;
use Tests\Base\JsonApiTest;
use Zenstruck\Foundry\Test\ResetDatabase;

class GetResourceTypeTest extends JsonApiTest
{
    use ResetDatabase;

    protected $customer;

    protected $resourceType;

    protected $resource;

    protected $resourceFactory;

    protected function setUp(): void
    {
        $this->resourceType = InstitutionTagCategoryResourceType::class;
        $this->resourceFactory = InstitutionTagCategoryFactory::class;
        $this->resource = $this->resourceFactory::createOne()->_enableAutoRefresh();

        $this->customer = CustomerFactory::createOne()->_enableAutoRefresh();
        $this->resource->setCustomer($this->customer->_real());
        $this->resource->_save();

        $this->setUpHttpClient();
    }

    public function testGetForAllResourceTypes(): void
    {
        $urlParameters = ['fields' => [
            $this->resourceType::getName() => 'name',
        ]];

        $urlParameters['resourceType'] = $this->resourceType::getName();
        $urlParameters['resourceId'] = $this->resource->getId();
        $expectedOutcome = [];
        $permissionsToEnableArray = ['feature_institution_tag_read', 'feature_json_api_get'];
        $user = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'name'      => Role::CUSTOMER_MASTER_USER,
            'code'      => Role::CUSTOMER_MASTER_USER,
            'groupCode' => Role::CUSTOMERMASTERUSERGROUP,
            'groupName' => Role::CUSTOMERMASTERUSERGROUP,
        ]);

        $user->setDplanroles([$role->_real()]);
        $user->_save();

        $user->setCurrentCustomer($this->customer->_real());
        $user->_save();


        $this->tokenStorage = $this->getContainer()->get('security.token_storage');
        $this->logIn($user->_real());

        $this->triggerGetRequest(
            $this->resourceType::getName(),
            $permissionsToEnableArray,
            $urlParameters,
            $user->_real(),
            null,
            $expectedOutcome);
    }

    private function triggerGetRequest(
        string $resourceTypeName,
        array $permissionsToEnableArray,
        array $urlParameters,
        $user,
        $procedure,
        $expectedOutcome): void
    {
        $this->enablePermissions($permissionsToEnableArray);
        $responseBody = $this->executeGetRequest(
            $resourceTypeName,
            $urlParameters['resourceId'],
            $user,
            $procedure,
            urlParameters: $urlParameters,
        );

        $this->assertEquals($this->resourceType::getName(), $responseBody['data']['type']);
        $this->assertEquals($this->resource->getId(), $responseBody['data']['id']);
        $this->assertEquals($this->resource->getName(), $responseBody['data']['attributes']['name']);

        // compare if outcome valid to $expectedOutcome
    }

    public function getResourceTypeData(): array
    {
        // to do data provider per project
        // and the run the test of main from the project using the data provider of the project
        // per PATch we cna send each field, and then check if they are updatable
        // CREATE -> not solved yet
        $requestDataDefinedInMain = [];

        return [
            [InstitutionTagResourceType::getName(),
                $this->loadPermissionsPerProjectAndPerRole(RoleInterface::PLANNING_AGENCY_ADMIN, 'PROJECT_KEY'),
                $requestDataDefinedInMain,
                $this->loadExpectedOutcomePerProjectAndRole(RoleInterface::PLANNING_AGENCY_ADMIN, 'PROJECT_KEY')],
        ];
    }

    public function loadPermissionsPerProjectAndPerRole($project, $role): array
    {
        // Load the permissions per project and per role
        return [
        ];
    }

    public function loadExpectedOutcomePerProjectAndRole($project, $role): array
    {
        // Load the permissions per project and per role
        return [
        ];
    }
}
