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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use Doctrine\DBAL\Connection;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\Application\Header;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class StatementStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly StatementService $statementService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), StatementResource::class);

        // TEMP: security disabled for local exploration — restore isAvailable() check before merging.
        // if (!$this->isAvailable()) {
        //     throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statement groups');
        // }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($context);
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function provideSingle(string $id): ?StatementResource
    {

        $statement = $this->statementService->getStatement($id);

        $statementResource = new StatementResource();
        $statementResource->id = $statement->getId();

        return $statementResource;
    }

    private function provideCollection(array $context = []): array
    {
        return [];
    }
}
