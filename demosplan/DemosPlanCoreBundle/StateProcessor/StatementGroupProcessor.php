<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTime;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Doctrine\DBAL\Connection;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

class StatementGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly StatementHandler $statementHandler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StatementGroupResource
    {
        Assert::isInstanceOf($data, StatementGroupResource::class);
        $procedureId = $this->currentProcedureService->getProcedure()->getId();
        $groupName = $data->groupName;
        $headStatementId = $data->headStatementId;
        $statementIds = array_map(static fn (StatementResource $s): string => $s->id, $data->statements);

        $cluster = $this->statementHandler->createStatementCluster(
            $procedureId,
            $statementIds,
            $headStatementId,
            $groupName
        );

        if (false === $cluster) {
            throw new NotFoundHttpException(sprintf('StatementGroup "%s" not found', $procedureId));
        }

        $group = new StatementGroupResource();
        $group->id = $cluster->getId();
        $group->groupName = $cluster->getName();

        return $group;
    }
}
