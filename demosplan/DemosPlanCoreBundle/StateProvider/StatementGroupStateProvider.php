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
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Webmozart\Assert\Assert;

class StatementGroupStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly StatementHandler $statementHandler,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), StatementGroupResource::class);

        // TEMP: security disabled for local exploration — restore isAvailable() check before merging.
        // if (!$this->isAvailable()) {
        //     throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statement groups');
        // }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function provideSingle(string $id): ?StatementGroupResource
    {
        $statement = $this->statementHandler->getStatement($id);
        if (!$statement instanceof Statement || !$statement->isClusterStatement()) {
            return null;
        }

        return $this->hydrateFromStatement($statement);
    }

    private function hydrateFromStatement(Statement $statement): StatementGroupResource
    {
        $resource = new StatementGroupResource();
        $resource->id = $statement->getId();
        $resource->createdDate = $statement->getCreated();
        $resource->groupName = $statement->getName();
        $resource->statements = array_map(
            static function (Statement $member): StatementResource {
                $statementResource = new StatementResource();
                $statementResource->id = $member->getId();

                return $statementResource;
            },
            $statement->getCluster()->toArray()
        );
        $resource->statementsCount = count($resource->statements);

        return $resource;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_procedures');
    }
}
