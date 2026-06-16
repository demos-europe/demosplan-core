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
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class StatementStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly StatementService $statementService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), StatementResource::class);

        if (!$this->isAvailable()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statements');
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return $this->hasAssessmentPermission()
            || $this->currentUser->hasPermission('area_search_submitter_in_procedures');
    }

    private function provideSingle(string $id): ?StatementResource
    {
        $statement = $this->statementService->getStatement($id);
        if (null === $statement) {
            return null;
        }

        $statementResource = new StatementResource();
        $statementResource->id = $statement->getId();

        return $statementResource;
    }

    private function hasAssessmentPermission(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'feature_json_api_statement',
            // allow access for the consultation token admin list
            'area_admin_consultations'
        );
    }
}
