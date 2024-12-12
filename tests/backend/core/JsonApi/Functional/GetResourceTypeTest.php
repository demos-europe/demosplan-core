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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\InstitutionTagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\RoleFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagResourceType;
use ReflectionClass;
use Tests\Base\JsonApiTest;
use Zenstruck\Foundry\Test\ResetDatabase;

class GetResourceTypeTest extends JsonApiTest
{
    use ResetDatabase;

    protected $customer;

    protected $user;

    protected $role;

    protected $resourceType;

    protected $resource;

    protected $resourceFactory;

    // Keep setup empty so that setup Parents are not executed
    protected function setUp(): void
    {
    }

    /**
     * @dataProvider resourceTypeDataProvider
     */
    public function testGetForAllResourceTypes(string $resourceTypeClass, string $resourceFactoryClass, array $permissions, array $expectedOutcome): void
    {
        $this->initializeResourceType($resourceTypeClass, $resourceFactoryClass);
        $this->setUpHttpClient();
        $this->initializeUserAndRole();

        $urlParameters = [
            'fields' => [
                $this->resourceType::getName() => 'name',
            ],
            'resourceType' => $this->resourceType::getName(),
            'resourceId'   => $this->resource->getId(),
        ];

        $this->triggerGetRequest(
            $this->resourceType::getName(),
            $permissions,
            $urlParameters,
            $this->user->_real(),
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
        // $this->assertEquals($this->resource->getName(), $responseBody['data']['attributes']['name']);

        // compare if outcome valid to $expectedOutcome
    }

    protected function initializeResourceType(string $resourceTypeClass, string $resourceFactoryClass): void
    {
        $this->resourceType = $resourceTypeClass;
        $this->resourceFactory = $resourceFactoryClass;
        $this->resource = $this->resourceFactory::createOne()->_enableAutoRefresh();
        $this->customer = CustomerFactory::createOne()->_enableAutoRefresh();

        $entityClassName = $this->resourceFactory::class();
        $reflectionClass = new ReflectionClass($entityClassName);

        // Check if the current class has a customer attribute, then set the current one
        if ($reflectionClass->hasMethod('setCustomer')) {
            $this->resource->setCustomer($this->customer->_real());
            $this->resource->_save();
        }
    }

    protected function initializeUserAndRole(): void
    {
        $this->user = UserFactory::createOne();
        $this->role = RoleFactory::createOne([
            'name'      => Role::CUSTOMER_MASTER_USER,
            'code'      => Role::CUSTOMER_MASTER_USER,
            'groupCode' => Role::CUSTOMERMASTERUSERGROUP,
            'groupName' => Role::CUSTOMERMASTERUSERGROUP,
        ]);

        $this->user->setDplanroles([$this->role->_real()]);
        $this->user->_save();

        $this->user->setCurrentCustomer($this->customer->_real());
        $this->user->_save();

        $this->tokenStorage = $this->getContainer()->get('security.token_storage');
        $this->logIn($this->user->_real());
    }

    public function resourceTypeDataProvider(): array
    {
        // to do data provider per project
        // and the run the test of main from the project using the data provider of the project
        // per PATch we cna send each field, and then check if they are updatable
        // CREATE -> not solved yet
        $requestDataDefinedInMain = [];

        return [
            [InstitutionTagCategoryResourceType::class, InstitutionTagCategoryFactory::class, ['feature_institution_tag_read', 'feature_json_api_get'], $this->generateExpectedTagCategoryOutcome()],
            [InstitutionTagResourceType::class, InstitutionTagFactory::class, ['feature_institution_tag_read', 'feature_json_api_get'], $this->generateExpectedTagOutcome()],
        ];
    }

    private function generateExpectedTagCategoryOutcome(): array
    {
        return [
            'data' => [
                'attributes' => [
                    'name' => 'getName',
                ],
            ],
        ];
    }

    private function generateExpectedTagOutcome(): array
    {
        return [
            'data' => [
                'attributes' => [
                    'name' => 'getLabel',
                ],
            ],
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
