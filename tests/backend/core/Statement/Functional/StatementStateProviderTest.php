<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use ApiPlatform\Metadata\Get;
use demosplan\DemosPlanCoreBundle\Api\Serializer\PermissionGroupResolver;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\StateProvider\StatementStateProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Base\FunctionalTestCase;

/**
 * The processing status needs extra queries (it lazy-loads the segments of the statement), so the
 * provider must only compute it for users who are allowed to see it.
 */
class StatementStateProviderTest extends FunctionalTestCase
{
    private const PERMISSIONS_UNDER_TEST = [
        'area_statement_segmentation',
        'feature_json_api_statement',
    ];

    protected ?StatementStateProvider $sut = null;
    private ?User $user = null;
    private ?Statement $statement = null;
    private ?MockObject $statementService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->loginTestUser();
        $this->statement = StatementFactory::createOne()->_real();
        // the statement service is a collaborator here, the provider is the system under test
        $this->statementService = $this->createMock(StatementService::class);
        $this->statementService->method('getStatement')->willReturn($this->statement);
        $this->sut = new StatementStateProvider(
            $this->currentUserService,
            $this->statementService,
            $this->getContainer()->get(PermissionGroupResolver::class)
        );
    }

    public function testProcessingStatusIsNotComputedWithoutSegmentationPermission(): void
    {
        $this->givenOnlyThesePermissions(['feature_json_api_statement']);
        $this->statementService->expects(self::never())->method('getProcessingStatus');

        $resource = $this->provideStatement();

        self::assertNull($resource->status);
    }

    public function testProcessingStatusIsComputedWithSegmentationPermission(): void
    {
        $this->givenOnlyThesePermissions(['feature_json_api_statement', 'area_statement_segmentation']);
        $this->statementService->expects(self::once())->method('getProcessingStatus')->willReturn('processing');

        $resource = $this->provideStatement();

        self::assertSame('processing', $resource->status);
    }

    private function provideStatement(): StatementResource
    {
        $resource = $this->sut->provide(new Get(class: StatementResource::class), ['id' => $this->statement->getId()]);

        self::assertInstanceOf(StatementResource::class, $resource);

        return $resource;
    }

    /**
     * @param list<string> $enabled
     */
    private function givenOnlyThesePermissions(array $enabled): void
    {
        $permissions = $this->currentUserService->getPermissions();
        $permissions->initPermissions($this->user);
        $permissions->disablePermissions(self::PERMISSIONS_UNDER_TEST);
        $permissions->enablePermissions($enabled);
    }
}
