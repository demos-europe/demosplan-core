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

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssignService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceAccess\StatementClusterAccessChecker;
use Exception;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

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
        private readonly CurrentUserInterface $currentUser,
        private readonly StatementClusterAccessChecker $clusterAccessChecker,
        private readonly StatementRepository $statementRepository,
        private readonly StatementHandler $statementHandler,
        private readonly AssignService $assignService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StatementGroupResource|Response
    {
        $this->clusterAccessChecker->checkClusterAccess();

        $groupId = (string) ($uriVariables['id'] ?? '');

        try {
            $group = $this->statementRepository->getEntityByIdentifier(
                $groupId,
                $this->clusterAccessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Statement group "%s" not found.', $groupId));
        }
        Assert::isInstanceOf($group, Statement::class);

        $memberIds = $this->readMemberIds($context, $groupId);
        $currentIds = array_map(static fn (Statement $s): string => $s->getId(), $group->getCluster()->toArray());

        if ($operation instanceof Delete) {
            // Apply only the delta: idempotent and concurrency-safe.
            $this->detachMembers(array_values(array_intersect($memberIds, $currentIds)));
            return $this->buildResponse($groupId);
        }

        if ($operation instanceof Post) {
            $toAdd = array_values(array_diff($memberIds, $currentIds));
            $blockers = $this->findAddBlockers($toAdd, $group->getProcedureId());
            if ([] !== $blockers) {
                // Adding is atomic: reject the whole request, change nothing.
                return $this->unprocessableEntity($blockers);
            }
            $this->addMembers($group->getProcedureId(), $groupId, $toAdd);
            return $this->buildResponse($groupId);
        }

        throw new LogicException(sprintf('%s is wired as the processor for unsupported operation "%s"; only Post and Delete are handled.', self::class, $operation::class));

    }

    private function buildResponse(string $groupId):StatementGroupResource {
        $updatedGroup = $this->statementHandler->getStatement($groupId);
        if (!$updatedGroup instanceof Statement) {
            // Last member detached: cluster dissolved and group deleted. Reachable
            // only on removal (204, output: false), so return a minimal type-safe stub.
            $resource = new StatementGroupResource();
            $resource->id = $groupId;
            $resource->statements = [];
            $resource->statementsCount = 0;

            return $resource;
        }

        return StatementGroupResource::fromStatement($updatedGroup);
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
     * Checks every statement in $toAddIds for eligibility to join the cluster, without
     * mutating anything. Mirrors the guards in StatementHandler::addStatementToCluster,
     * which would otherwise drop ineligible statements silently while returning success.
     *
     * @param string[] $toAddIds
     *
     * @return array<string, string> map of statement id => reason it cannot be added (empty if all eligible)
     */
    private function findAddBlockers(array $toAddIds, string $procedureId): array
    {
        $assignmentEnforced = $this->currentUser->hasPermission('feature_statement_assignment');

        $blockers = [];
        foreach ($toAddIds as $id) {
            $reason = $this->addBlockReason($id, $procedureId, $assignmentEnforced);
            if (null !== $reason) {
                $blockers[$id] = $reason;
            }
        }

        return $blockers;
    }

    /**
     * Builds a JSON:API error document (one error object per rejected statement) and
     * returns it as a 422 response. Returning a Response bypasses API Platform's
     * serialization, so the client gets JSON regardless of the environment's error
     * rendering (in dev, thrown exceptions would render as an HTML debug page).
     *
     * @param array<string, string> $blockers statement id => reason
     */
    private function unprocessableEntity(array $blockers): JsonResponse
    {
        $errors = array_map(
            static fn (string $id, string $reason): array => [
                'status' => (string) Response::HTTP_UNPROCESSABLE_ENTITY,
                'title'  => 'Statement cannot be added to the group',
                'detail' => ucfirst($reason),
                'source' => ['pointer' => '/data'],
                'meta'   => ['statementId' => $id],
            ],
            array_keys($blockers),
            array_values($blockers)
        );

        $response = new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->headers->set('Content-Type', 'application/vnd.api+json');

        return $response;
    }

    /**
     * Returns the reason a statement cannot be added, or null if it is eligible.
     */
    private function addBlockReason(string $id, string $procedureId, bool $assignmentEnforced): ?string
    {
        $statement = $this->statementHandler->getStatement($id);
        if (!$statement instanceof Statement) {
            return 'statement not found.';
        }

        return match (true) {
            $statement->getProcedureId() !== $procedureId    => 'statement belongs to a different procedure.',
            $statement->isPlaceholder()                      => 'statement is a placeholder and cannot be grouped.',
            $statement->isInCluster()                        => sprintf('statement is already a member of group "%s".', $statement->getHeadStatement()?->getExternId() ?? 'another group'),
            $assignmentEnforced
                && !$this->assignService->isStatementObjectAssignedToCurrentUser($statement) => 'statement is not assigned to you.',
            default                                                                          => null,
        };
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
