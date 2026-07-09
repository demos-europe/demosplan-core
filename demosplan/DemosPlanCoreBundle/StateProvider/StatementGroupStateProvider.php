<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementClusterConditions;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class StatementGroupStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly StatementClusterConditions $clusterConditions,
        private readonly StatementRepository $statementRepository,
        private readonly CurrentProcedureService $currentProcedureService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), StatementGroupResource::class);

        if (!$this->isAvailable()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statement groups');
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return $this->provideCollection();
    }

    /**
     * @return list<StatementGroupResource>
     */
    private function provideCollection(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [];
        }

        $statements = $this->statementRepository->getEntities(
            $this->clusterConditions->forProcedure($procedure->getId()),
            $this->clusterConditions->defaultSortMethods()
        );

        return array_map(
            static fn (Statement $statement): StatementGroupResource => StatementGroupResource::fromStatement($statement),
            $statements
        );
    }

    private function provideSingle(string $id): ?StatementGroupResource
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return null;
        }

        $statements = $this->statementRepository->getEntities(
            $this->clusterConditions->forProcedureById($procedure->getId(), $id),
            []
        );

        return [] === $statements ? null : StatementGroupResource::fromStatement($statements[0]);
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_statement_cluster');
    }
}
