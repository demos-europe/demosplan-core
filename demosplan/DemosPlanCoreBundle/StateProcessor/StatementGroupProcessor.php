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

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\NotAllStatementsGroupableException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\ResourceAccess\StatementClusterAccessChecker;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

class StatementGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly StatementClusterAccessChecker $clusterAccessChecker,
        private readonly StatementHandler $statementHandler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StatementGroupResource|Response|null
    {
        $this->clusterAccessChecker->checkClusterAccess();

        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            throw new BadRequestHttpException('A procedure context is required for statement group operations.');
        }

        // DELETE has no body, so $data is null: handle it before the assert.
        if ($operation instanceof HttpOperation && Request::METHOD_DELETE === $operation->getMethod()) {
            return $this->delete((string) ($uriVariables['id'] ?? ''), $procedure->getId());
        }

        Assert::isInstanceOf($data, StatementGroupResource::class);

        // POST has no {id}; PATCH carries it in the URL.
        if (isset($uriVariables['id'])) {
            return $this->update((string) $uriVariables['id'], $data);
        }

        return $this->create($data, $procedure->getId());
    }

    /**
     * Dissolves the group by detaching every member from the cluster.
     *
     * Detaching the last member deletes the (cloned) head statement automatically
     * (see StatementHandler::detachStatementFromCluster), so the group ceases to exist.
     * Returns null: the operation is declared output: false, so API Platform responds 204.
     */
    private function delete(string $groupId, string $procedureId): ?Response
    {
        $group = $this->statementHandler->getStatement($groupId);
        // Scope to the current procedure; foreign group reported as "not found".
        if (!$group instanceof Statement
            || !$group->isClusterStatement()
            || $group->getProcedureId() !== $procedureId) {
            throw new BadRequestHttpException(sprintf('Statement group "%s" not found.', $groupId));
        }

        // Snapshot: detaching mutates the collection.
        foreach ($group->getCluster()->toArray() as $member) {
            $this->statementHandler->detachStatementFromCluster($member);
        }

        return null;
    }

    private function create(StatementGroupResource $data, string $procedureId): StatementGroupResource|Response
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
        } catch (NotAllStatementsGroupableException $e) {
            return $this->notGroupableResponse($e);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
        Assert::isInstanceOf($cluster, Statement::class);

        $createdGroup = $this->statementHandler->getStatement($cluster->getId());
        Assert::isInstanceOf($createdGroup, Statement::class);

        return StatementGroupResource::fromStatement($createdGroup);
    }

    /**
     * Updates the group's scalar fields only — currently the group name.
     *
     * Membership is NOT changed here: statements are added/removed exclusively via the
     * JSON:API relationship endpoints (POST/DELETE /StatementGroup/{id}/relationships/statements),
     * which apply an idempotent delta. Any "statements" sent in a PATCH body is ignored.
     */
    private function update(string $groupId, StatementGroupResource $data): StatementGroupResource
    {
        $group = $this->statementHandler->getStatement($groupId);
        if (!$group instanceof Statement || !$group->isClusterStatement()) {
            throw new BadRequestHttpException(sprintf('Statement group "%s" not found.', $groupId));
        }

        if (null !== $data->groupName) {
            $group->setName($data->groupName);
            $this->statementHandler->updateStatementObject($group, true, true);
        }

        return StatementGroupResource::fromStatement($group);
    }

    /**
     * Turns a "not groupable" failure into a JSON:API 422 error document instead of an
     * uncaught 500. Returning a Response bypasses API Platform serialization, so the
     * client gets JSON regardless of the environment's error rendering (in dev, a thrown
     * exception would render as an HTML debug page).
     */
    private function notGroupableResponse(NotAllStatementsGroupableException $e): JsonResponse
    {
        $statementId = $e->getStatementId();
        $detail = null !== $statementId
            ? sprintf('Statement "%s" cannot be grouped: it or its fragments must be claimed by (assigned to) you first.', $statementId)
            : $e->getMessage();

        $error = [
            'status' => (string) Response::HTTP_UNPROCESSABLE_ENTITY,
            'title'  => 'Statement cannot be grouped',
            'detail' => $detail,
            'source' => ['pointer' => '/data'],
        ];
        if (null !== $statementId) {
            $error['meta'] = ['statementId' => $statementId];
        }

        $response = new JsonResponse(['errors' => [$error]], Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->headers->set('Content-Type', 'application/vnd.api+json');

        return $response;
    }
}
