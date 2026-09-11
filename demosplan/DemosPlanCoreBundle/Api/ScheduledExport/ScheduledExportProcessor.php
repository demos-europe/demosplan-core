<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\ScheduledExport;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\DemosPlanResponseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Exception\DeletionFailedException;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\ScheduledExportRepository;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

/**
 */
class ScheduledExportProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ScheduledExportAccessChecker $accessChecker,
        private readonly ScheduledExportRepository $bookmarkRepository,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserInterface $currentUser,
        private readonly HashedQueryService $hashedQueryService,
        private readonly MessageBagInterface $messageBag,
    ) {
    }

    /**
     * @throws PersistResourceException
     * @throws DeletionFailedException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ScheduledExportResource|Response|null
    {
        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        // DELETE carries no body, so $data is null: handle it before asserting on the payload.
        if ($operation instanceof Delete) {
            $this->delete((string) ($uriVariables['id'] ?? ''));

            return $this->createDeletionResponse();
        }

        Assert::isInstanceOf($data, ScheduledExportResource::class);

        if ($operation instanceof Patch) {
            return $this->update((string) $uriVariables['id'], $data);
        }

        if ($operation instanceof Post) {
            return $this->create($data);
        }

        throw new LogicException(sprintf('%s is wired as the processor for unsupported operation "%s"; only Post, Patch, and Delete are handled.', self::class, $operation::class));
    }

    /**
     * @throws PersistResourceException
     */
    private function create(ScheduledExportResource $data): ScheduledExportResource
    {
        // Presence is enforced by the scheduledExport:create validation group; narrow for static analysis.
        Assert::stringNotEmpty($data->name);
        Assert::stringNotEmpty($data->queryHash);

        $procedure = $this->getCurrentProcedure();
        $hashedQuery = $this->resolveHashedQuery($data->queryHash, $procedure);

        $this->assertNameIsFree($data->name, null);

        /** @var User $user */
        $user = $this->currentUser->getUser();

        $scheduledExport = new ScheduledExport();
        $scheduledExport->setName($data->name);
        $scheduledExport->setUser($user);
        $scheduledExport->setProcedure($procedure);
        $scheduledExport->setFilterSet($hashedQuery);

        if (!$this->scheduledExportRepository->addObject($scheduledExport)) {
            throw new PersistResourceException('Could not persist the bookmark.');
        }

        return ScheduledExportResource::fromEntity($scheduledExport);
    }

    /**
     *
     * @throws PersistResourceException
     */
    private function update(string $scheduledExportId, ScheduledExportResource $data): ScheduledExportResource
    {
        $scheduledExport = $this->findScheduledExport($scheduledExportId);

        if (null !== $data->name) {
            Assert::stringNotEmpty($data->name);

            $this->assertNameIsFree($data->name, $scheduledExportId);
            $scheduledExport->setName($data->name);
        }

        if (null !== $data->queryHash) {
            Assert::stringNotEmpty($data->queryHash);
            $scheduledExport->setFilterSet($this->resolveHashedQuery($data->queryHash, $this->getCurrentProcedure()));
        }

        if (!$this->bookmarkRepository->updateObject($scheduledExport)) {
            throw new PersistResourceException('Could not update the bookmark.');
        }

        return ScheduledExportResource::fromEntity($scheduledExport);
    }

    /**
     * The repository catches its own failures and answers with false, so the result has to be checked:
     * without it a failed deletion would still be confirmed and the frontend would drop an entry that is
     * still stored.
     * @throws DeletionFailedException rendered as a 500, since a deletion that fails is not the
     * client's mistake. The cause is already in the repository's log
     */
    private function delete(string $scheduledExportId): void
    {
        if (!$this->scheduledExportRepository->deleteObject($this->findBookmark($scheduledExportId))) {
            throw new DeletionFailedException();
        }

        $this->messageBag->add('confirm', 'confirm.scheduledExport.deleted');
    }

    /**
     * Answered as an empty JSON document rather than by letting API Platform build the response from
     * the `output: false` declaration: that yields a plain Response, and
     * {@see DemosPlanResponseEventSubscriber} merges the
     * message bag into JsonResponses only - the confirmation would be withheld here and then shown on
     * whatever request comes next. Handing it a JsonResponse also makes it lift the 204 to a 200, which
     * is the same answer the rest of the JSON:API deletions give.
     */
    private function createDeletionResponse(): JsonResponse
    {
        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Looked up through the access conditions, so another user's scheduled Export, another procedure's, or one
     * belonging to the assessment table is indistinguishable from a nonexistent one.
     */
    private function findScheduledExport(string $scheduledExportId): ScheduledExport
    {
        try {
            return $this->scheduledExportRepository->getEntityByIdentifier(
                $scheduledExportId,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            $this->messageBag->add('error', 'error.scheduledExport.not.found');

            throw new NotFoundHttpException(sprintf('Scheduled Export "%s" not found.', $scheduledExportId));
        }
    }

    private function resolveHashedQuery(string $queryHash, Procedure $procedure): HashedQuery
    {
        $hashedQuery = $this->hashedQueryService->findHashedQueryWithHash($queryHash);
        if (!$hashedQuery instanceof HashedQuery) {
            $this->messageBag->add('error', 'error.scheduledExport.query.not.found');

            throw new BadRequestHttpException(sprintf('No stored query was found for the given hash: %s', $queryHash));
        }

        $storedQuery = $hashedQuery->getStoredQuery();
        if (!$storedQuery instanceof SegmentListQuery) {
            $this->messageBag->add('error', 'error.scheduledExport.query.not.found');

            throw new BadRequestHttpException(sprintf('The given hash does not belong to a segment list query: %s', $queryHash));
        }

        if ($storedQuery->getProcedureId() !== $procedure->getId()) {
            $this->messageBag->add('error', 'error.scheduledExport.query.not.found');

            throw new BadRequestHttpException(sprintf('The given hash belongs to another procedure: %s', $queryHash));
        }

        return $hashedQuery;
    }

    private function getCurrentProcedure(): Procedure
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            $this->messageBag->add('error', 'error.scheduledExport.procedure.missing');

            throw new BadRequestHttpException('A procedure context is required for scheduled Export operations.');
        }

        return $procedure;
    }
}
