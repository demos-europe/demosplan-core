<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Bookmark;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Bookmark;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\DeletionFailedException;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\BookmarkRepository;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Webmozart\Assert\Assert;

/**
 * Writes go through the repository rather than through {@see \demosplan\DemosPlanCoreBundle\Logic\Procedure\BookmarkService}:
 * its `saveBookmark()` takes raw ids and answers with a bool, so the created entity cannot be returned,
 * and its `deleteBookmark()` performs no ownership check. Both signatures serve the assessment table's
 * legacy form flow and are left untouched.
 */
class BookmarkProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly BookmarkAccessChecker $accessChecker,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserInterface $currentUser,
        private readonly HashedQueryService $hashedQueryService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BookmarkResource|Response|null
    {
        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        // DELETE carries no body, so $data is null: handle it before asserting on the payload.
        if ($operation instanceof Delete) {
            $this->delete((string) ($uriVariables['id'] ?? ''));

            // The operation is declared output: false, so API Platform answers 204.
            return null;
        }

        Assert::isInstanceOf($data, BookmarkResource::class);

        if ($operation instanceof Patch) {
            return $this->update((string) $uriVariables['id'], $data);
        }

        if ($operation instanceof Post) {
            return $this->create($data);
        }

        throw new LogicException(sprintf('%s is wired as the processor for unsupported operation "%s"; only Post, Patch, and Delete are handled.', self::class, $operation::class));
    }

    private function create(BookmarkResource $data): BookmarkResource
    {
        // Presence is enforced by the bookmark:create validation group; narrow for static analysis.
        Assert::stringNotEmpty($data->name);
        Assert::stringNotEmpty($data->queryHash);

        $procedure = $this->getCurrentProcedure();
        $hashedQuery = $this->resolveHashedQuery($data->queryHash, $procedure);

        $this->assertNameIsFree($data->name, null);

        /** @var User $user */
        $user = $this->currentUser->getUser();

        $bookmark = new Bookmark();
        $bookmark->setName($data->name);
        $bookmark->setUser($user);
        $bookmark->setProcedure($procedure);
        $bookmark->setFilterSet($hashedQuery);

        if (!$this->bookmarkRepository->addObject($bookmark)) {
            throw new PersistResourceException('Could not persist the bookmark.');
        }

        return BookmarkResource::fromEntity($bookmark);
    }

    /**
     * Renames a bookmark, repoints it at another stored query, or both. Sending only one of the two
     * fields leaves the other as it is, which is what lets the frontend either rename a view or update
     * it to the one the user is currently looking at.
     */
    private function update(string $bookmarkId, BookmarkResource $data): BookmarkResource
    {
        $bookmark = $this->findBookmark($bookmarkId);

        if (null !== $data->name) {
            Assert::stringNotEmpty($data->name);

            $this->assertNameIsFree($data->name, $bookmarkId);
            $bookmark->setName($data->name);
        }

        if (null !== $data->queryHash) {
            Assert::stringNotEmpty($data->queryHash);
            $bookmark->setFilterSet($this->resolveHashedQuery($data->queryHash, $this->getCurrentProcedure()));
        }

        if (!$this->bookmarkRepository->updateObject($bookmark)) {
            throw new PersistResourceException('Could not update the bookmark.');
        }

        return BookmarkResource::fromEntity($bookmark);
    }

    /**
     * The repository catches its own failures and answers with false, so the result has to be checked:
     * without it a failed deletion would still respond 204 and the frontend would drop an entry that is
     * still stored.
     *
     * @throws DeletionFailedException rendered as a 500, since a deletion that fails is not the
     *                                 client's mistake. The cause is already in the repository's log
     */
    private function delete(string $bookmarkId): void
    {
        if (!$this->bookmarkRepository->deleteObject($this->findBookmark($bookmarkId))) {
            throw new DeletionFailedException();
        }
    }

    /**
     * Looked up through the access conditions, so another user's bookmark, another procedure's, or one
     * belonging to the assessment table is indistinguishable from a nonexistent one.
     */
    private function findBookmark(string $bookmarkId): Bookmark
    {
        try {
            return $this->bookmarkRepository->getEntityByIdentifier(
                $bookmarkId,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Bookmark "%s" not found.', $bookmarkId));
        }
    }

    /**
     * The hash arrives from the client, so it is checked on two axes beyond mere existence: it has to
     * belong to a segment list query rather than to another kind sharing the table, and to the
     * procedure in the request - otherwise a user could bookmark a view of a procedure they are not in.
     */
    private function resolveHashedQuery(string $queryHash, Procedure $procedure): HashedQuery
    {
        $hashedQuery = $this->hashedQueryService->findHashedQueryWithHash($queryHash);
        if (!$hashedQuery instanceof HashedQuery) {
            throw new BadRequestHttpException(sprintf('No stored query was found for the given hash: %s', $queryHash));
        }

        $storedQuery = $hashedQuery->getStoredQuery();
        if (!$storedQuery instanceof SegmentListQuery) {
            throw new BadRequestHttpException(sprintf('The given hash does not belong to a segment list query: %s', $queryHash));
        }

        if ($storedQuery->getProcedureId() !== $procedure->getId()) {
            throw new BadRequestHttpException(sprintf('The given hash belongs to another procedure: %s', $queryHash));
        }

        return $hashedQuery;
    }

    /**
     * Enforced here rather than by a unique index, because the table is shared with the assessment
     * table and an index would reject name pairs that already exist there.
     *
     * Compared in PHP over the accessible bookmarks: the access conditions already narrow that to the
     * current user, procedure and view kind, and a user holds a handful of them.
     *
     * @param string|null $ignoredBookmarkId the bookmark being renamed, so keeping its own name is not
     *                                       reported as a collision
     *
     * @throws UnprocessableEntityHttpException
     */
    private function assertNameIsFree(string $name, ?string $ignoredBookmarkId): void
    {
        $bookmarks = $this->bookmarkRepository->getEntities($this->accessChecker->getAccessConditions(), []);

        foreach ($bookmarks as $bookmark) {
            if ($bookmark->getId() === $ignoredBookmarkId) {
                continue;
            }
            if (0 === strcasecmp($bookmark->getName(), $name)) {
                throw new UnprocessableEntityHttpException(sprintf('A bookmark named "%s" already exists in this procedure.', $name));
            }
        }
    }

    private function getCurrentProcedure(): Procedure
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            throw new BadRequestHttpException('A procedure context is required for bookmark operations.');
        }

        return $procedure;
    }
}
