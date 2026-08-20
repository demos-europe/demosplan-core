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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Bookmark;
use demosplan\DemosPlanCoreBundle\Repository\BookmarkRepository;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class BookmarkProvider implements ProviderInterface
{
    public function __construct(
        private readonly BookmarkAccessChecker $accessChecker,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly MessageBagInterface $messageBag,
        private readonly SortMethodFactory $sortMethodFactory,
    ) {
    }

    /**
     * @throws Exception
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), BookmarkResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection();
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle((string) $uriVariables['id']);
        }

        return null;
    }

    /**
     * Returning null lets API Platform answer 404. That covers a bookmark of another user, of another
     * procedure, or one belonging to the assessment table, because all three are excluded by the access
     * conditions rather than by a check here.
     */
    private function provideSingle(string $id): ?BookmarkResource
    {
        try {
            $bookmark = $this->bookmarkRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            $this->messageBag->add('error', 'error.bookmark.not.found');

            return null;
        }

        return BookmarkResource::fromEntity($bookmark);
    }

    /**
     * Sorted by name, which is the only stable order available: the entity carries no created or
     * modified timestamp, so offering recency would need a migration on a table the assessment table
     * shares. The list is a handful of entries per user and procedure, hence no pagination either.
     *
     * @return list<BookmarkResource>
     * @throws Exception
     */
    private function provideCollection(): array
    {
        $bookmarks = $this->bookmarkRepository->getEntities(
            $this->accessChecker->getAccessConditions(),
            [$this->sortMethodFactory->propertyAscending(['name'])]
        );

        return array_map(
            static fn (Bookmark $bookmark): BookmarkResource => BookmarkResource::fromEntity($bookmark),
            $bookmarks
        );
    }
}
