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
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\ResourceAccess\StatementClusterAccessChecker;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

class StatementGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly StatementClusterAccessChecker $clusterAccessChecker,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly StatementHandler $statementHandler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StatementGroupResource
    {
        Assert::isInstanceOf($data, StatementGroupResource::class);

        $this->clusterAccessChecker->checkClusterAccess();

        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            throw new BadRequestHttpException('A procedure context is required to create a statement group.');
        }

        if (null === $data->headStatementId || '' === $data->headStatementId) {
            throw new BadRequestHttpException('headStatementId is required to create a statement group.');
        }

        $headStatement = $this->statementHandler->getStatement($data->headStatementId);
        if (!$headStatement instanceof Statement) {
            throw new BadRequestHttpException(sprintf('Statement "%s" not found.', $data->headStatementId));
        }

        $statementIds = array_map(static fn (StatementResource $s): string => $s->id, $data->statements);

        try {
            $cluster = $this->statementHandler->createStatementCluster(
                $procedure->getId(),
                $statementIds,
                $data->headStatementId,
                $data->groupName
            );
        } catch (Exception $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
        Assert::isInstanceOf($cluster, Statement::class);

        $createdGroup = $this->statementHandler->getStatement($cluster->getId());
        Assert::isInstanceOf($createdGroup, Statement::class);

        return StatementGroupResource::fromStatement($createdGroup);
    }
}
