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
use Exception;
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
            throw new BadRequestHttpException('A procedure context is required for statement group operations.');
        }

        // POST has no {id}; PATCH carries the group id in the URL.
        if (isset($uriVariables['id'])) {
            return $this->update((string) $uriVariables['id'], $data, $procedure->getId());
        }

        return $this->create($data, $procedure->getId());
    }

    private function create(StatementGroupResource $data, string $procedureId): StatementGroupResource
    {
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
                $procedureId,
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
     * Reconciles cluster membership to exactly the statements present in $data->statements.
     *
     * The resource is loaded by the state provider before deserialization, so omitted
     * properties are merged from the current state: omitting "statements" leaves membership
     * untouched, while a provided list replaces it (members no longer in the list are detached).
     */
    private function update(string $groupId, StatementGroupResource $data, string $procedureId): StatementGroupResource
    {
        $group = $this->statementHandler->getStatement($groupId);
        if (!$group instanceof Statement || !$group->isClusterStatement()) {
            throw new BadRequestHttpException(sprintf('Statement group "%s" not found.', $groupId));
        }

        // Apply the group name first, before touching membership; both can be changed independently.
        if (null !== $data->groupName) {
            $group->setName($data->groupName);
            $this->statementHandler->updateStatementObject($group, true, true);
        }

        $desiredIds = array_map(static fn (StatementResource $s): string => $s->id, $data->statements);
        $currentIds = array_map(static fn (Statement $s): string => $s->getId(), $group->getCluster()->toArray());

        $toDetach = array_values(array_diff($currentIds, $desiredIds));
        $toAdd = array_values(array_diff($desiredIds, $currentIds));

        foreach ($toDetach as $memberId) {
            $member = $this->statementHandler->getStatement($memberId);
            if ($member instanceof Statement) {
                $this->statementHandler->detachStatementFromCluster($member);
            }
        }

        if ([] !== $toAdd) {
            try {
                $this->statementHandler->updateStatementCluster($procedureId, $toAdd, $groupId);
            } catch (Exception $e) {
                throw new BadRequestHttpException($e->getMessage(), $e);
            }
        }

        $updatedGroup = $this->statementHandler->getStatement($groupId);
        Assert::isInstanceOf($updatedGroup, Statement::class);

        return StatementGroupResource::fromStatement($updatedGroup);
    }
}
