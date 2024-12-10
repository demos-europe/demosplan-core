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
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagResourceType;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Tests\Base\JsonApiTest;
use Zenstruck\Foundry\Test\ResetDatabase;

class GetResourceTypeTest extends JsonApiTest
{
    use ResetDatabase;
    protected $institutionTagCategory;

    protected function setUp(): void
    {
        parent::setUp();

        // $this->entityManager = self::$container->get(EntityManagerInterface::class);
        $customer = CustomerFactory::createOne();
        $this->institutionTagCategory = InstitutionTagCategoryFactory::createOne()->_enableAutoRefresh();

        static::ensureKernelShutdown();
        // the createClient() method cannot be used when kernel is booted
        $this->client = static::createClient();
        $serverParameters = $this->getServerParameters();
        $this->client->setServerParameters($serverParameters);

        $this->router = $this->getContainer()->get(RouterInterface::class);
        $this->tokenManager = $this->getContainer()->get(JWTTokenManagerInterface::class);
    }

    public function testGetForAllResourceTypes(): void
    {
        $urlParameters = ['fields' => [
            InstitutionTagCategoryResourceType::getName() => 'name',
        ]];

        $urlParameters['resourceType'] = InstitutionTagCategoryResourceType::getName();
        $urlParameters['resourceId'] = $this->institutionTagCategory->getId();
        $expectedOutcome = [];
        $permissionsToEnableArray = ['feature_institution_tag_read'];
        $user = UserFactory::createOne();
        $this->tokenStorage = $this->getContainer()->get('security.token_storage');

        // $user = $this->getUserReference(LoadUserData::TEST_USER_CUSTOMER_MASTER);
        $this->triggerGetRequest(
            InstitutionTagCategoryResourceType::getName(),
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
        $bla = 'bla';

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
