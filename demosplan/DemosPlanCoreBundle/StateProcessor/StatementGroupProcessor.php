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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

class StatementGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly StatementHandler $statementHandler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StatementGroupResource
    {
        Assert::isInstanceOf($data, StatementGroupResource::class);

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

        $this->getAccessConditions($headStatement, $procedure);

        $statementIds = array_map(static fn (StatementResource $s): string => $s->id, $data->statements);

        try {
            $cluster = $this->statementHandler->createStatementCluster(
                $procedure->getId(),
                $statementIds,
                $data->headStatementId,
                $data->groupName
            );
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
        Assert::isInstanceOf($cluster, Statement::class);

        $createdGroup = $this->statementHandler->getStatement($cluster->getId());
        Assert::isInstanceOf($createdGroup, Statement::class);

        return StatementGroupResource::fromStatement($createdGroup);
    }

    /**
     * Translates ClusterStatementResourceType::getAccessConditions() into PHP entity
     * checks on the head Statement. Throws BadRequestHttpException when a condition fails.
     *
     * The clusterStatement condition is intentionally omitted: the head statement will
     * become a cluster statement after creation, so requiring it beforehand would
     * always reject valid input.
     */
    private function getAccessConditions(Statement $headStatement, Procedure $procedure): void
    {
        // Mirrors: propertyHasValue($procedure->getId(), $this->procedure->id)
        if ($headStatement->getProcedureId() !== $procedure->getId()) {
            throw new BadRequestHttpException(sprintf('Statement "%s" does not belong to the current procedure.', $headStatement->getId()));
        }

        // Mirrors: propertyIsNotNull($this->original)
        // Statements without an original are themselves originals and cannot head a cluster.
        if (null === $headStatement->getOriginal()) {
            throw new BadRequestHttpException(sprintf('Statement "%s" is an original statement and cannot serve as cluster head.', $headStatement->getId()));
        }

        // Mirrors: propertyHasValue(false, $this->deleted)
        if ($headStatement->isDeleted()) {
            throw new BadRequestHttpException(sprintf('Statement "%s" is deleted and cannot serve as cluster head.', $headStatement->getId()));
        }

        // Mirrors: propertyIsNull($this->headStatement)
        // A statement that is already a cluster member cannot itself become a cluster head.
        if (null !== $headStatement->getHeadStatement()) {
            throw new BadRequestHttpException(sprintf('Statement "%s" is already a cluster member.', $headStatement->getId()));
        }

        // Mirrors: propertyIsNull($this->movedStatement)
        // Placeholder statements left behind by a move are not real statement resources.
        if (null !== $headStatement->getMovedStatement()) {
            throw new BadRequestHttpException(sprintf('Statement "%s" is a moved-statement placeholder and cannot serve as cluster head.', $headStatement->getId()));
        }
    }
}
