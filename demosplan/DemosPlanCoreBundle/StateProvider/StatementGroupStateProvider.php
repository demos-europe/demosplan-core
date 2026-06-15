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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
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
        //incorporate getAccessConditions()
        Assert::same($operation->getClass(), StatementGroupResource::class);

        if (!$this->isAvailable()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statement groups');
        }

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

        return StatementGroupResource::fromStatement($statement);
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_statement_group');
    }
}
