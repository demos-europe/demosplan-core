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
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Exception;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Adds (POST) or removes (DELETE) statements from a group's membership via the
 * JSON:API relationship-linkage endpoints:
 *
 *   POST   /StatementGroup/{id}/relationships/statements
 *   DELETE /StatementGroup/{id}/relationships/statements
 *
 * The body is a JSON:API to-many relationship document — an array of resource
 * identifiers — so the client sends only the statements being changed:
 *
 *   { "data": [ { "type": "Statement", "id": "…" } ] }
 *
 * Applying only a delta keeps payloads small and makes concurrent edits safe:
 * adding an existing member or removing a non-member is a no-op rather than
 * clobbering the membership another request just changed.
 */
class StatementGroupRelationshipProcessor implements ProcessorInterface
{
    /**
     * The resource identifier type expected in the linkage document; members of
     * a statement group are themselves statements.
     */
    private const MEMBER_TYPE = 'Statement';

    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserInterface $currentUser,
        private readonly StatementHandler $statementHandler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StatementGroupResource
    {
        if (!$this->currentUser->hasPermission('feature_statement_cluster')) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statement groups');
        }

        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            throw new BadRequestHttpException('A procedure context is required for statement group operations.');
        }

        $groupId = (string) ($uriVariables['id'] ?? '');
        $group = $this->statementHandler->getStatement($groupId);
        if (!$group instanceof Statement || !$group->isClusterStatement()) {
            throw new BadRequestHttpException(sprintf('Statement group "%s" not found.', $groupId));
        }

        $memberIds = $this->readMemberIds($context, $groupId);
        $currentIds = array_map(static fn (Statement $s): string => $s->getId(), $group->getCluster()->toArray());

        // Reduce to the effective delta so the operation is idempotent and concurrency-safe.
        if ($this->isRemoval($operation)) {
            $this->detachMembers(array_values(array_intersect($memberIds, $currentIds)));
        } else {
            $this->addMembers($procedure->getId(), $groupId, array_values(array_diff($memberIds, $currentIds)));
        }

        $updatedGroup = $this->statementHandler->getStatement($groupId);
        if (!$updatedGroup instanceof Statement) {
            // Detaching the last member dissolves the cluster: the group is deleted
            // (see StatementHandler::detachStatementFromCluster). Only reachable on
            // removal; the DELETE response is 204 (output: false), so this resource is
            // never serialized — return a minimal representation to stay type-safe.
            $resource = new StatementGroupResource();
            $resource->id = $groupId;
            $resource->statements = [];
            $resource->statementsCount = 0;

            return $resource;
        }

        return StatementGroupResource::fromStatement($updatedGroup);
    }

    private function isRemoval(Operation $operation): bool
    {
        return $operation instanceof HttpOperation && Request::METHOD_DELETE === $operation->getMethod();
    }

    /**
     * Parses the JSON:API relationship-linkage document from the request body into
     * a de-duplicated list of member statement ids.
     *
     * @return string[]
     */
    private function readMemberIds(array $context, string $groupId): array
    {
        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            throw new BadRequestHttpException('Request body is required.');
        }

        try {
            $decoded = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BadRequestHttpException('Request body is not valid JSON.', $e);
        }

        if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
            throw new BadRequestHttpException('Request body must be a JSON:API relationship document with a "data" array.');
        }

        $ids = array_values(array_unique(array_map($this->extractMemberId(...), $decoded['data'])));

        if ([] === $ids) {
            throw new BadRequestHttpException('At least one statement resource identifier must be provided in "data".');
        }
        if (in_array($groupId, $ids, true)) {
            throw new BadRequestHttpException('A group cannot be added to or removed from itself.');
        }

        return $ids;
    }

    private function extractMemberId(mixed $identifier): string
    {
        if (!is_array($identifier) || !isset($identifier['type'], $identifier['id'])) {
            throw new BadRequestHttpException('Each entry in "data" must be a resource identifier object with "type" and "id".');
        }
        if (self::MEMBER_TYPE !== $identifier['type']) {
            throw new BadRequestHttpException(sprintf('Expected resource identifier of type "%s", got "%s".', self::MEMBER_TYPE, (string) $identifier['type']));
        }

        return (string) $identifier['id'];
    }

    /**
     * @param string[] $memberIds
     */
    private function detachMembers(array $memberIds): void
    {
        foreach ($memberIds as $memberId) {
            $member = $this->statementHandler->getStatement($memberId);
            if ($member instanceof Statement) {
                $this->statementHandler->detachStatementFromCluster($member);
            }
        }
    }

    /**
     * @param string[] $memberIds
     */
    private function addMembers(string $procedureId, string $groupId, array $memberIds): void
    {
        if ([] === $memberIds) {
            return;
        }

        try {
            $this->statementHandler->updateStatementCluster($procedureId, $memberIds, $groupId);
        } catch (Exception $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }
}
