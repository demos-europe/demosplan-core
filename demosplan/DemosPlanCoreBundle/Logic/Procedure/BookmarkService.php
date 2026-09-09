<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Bookmark;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Repository\BookmarkRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Psr\Log\LoggerInterface;

class BookmarkService
{
    public function __construct(
        private readonly ProcedureRepository $procedureRepository,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string $procedureId
     * @param string $userId
     * @param string $name
     *
     * @return bool
     */
    public function saveBookmark($procedureId, $userId, $name, HashedQuery $filterSet)
    {
        $user = $this->userRepository->find($userId);
        $procedure = $this->procedureRepository->find($procedureId);

        $bookmark = new Bookmark();

        $bookmark->setName($name);
        $bookmark->setUser($user);
        $bookmark->setFilterSet($filterSet);
        $bookmark->setProcedure($procedure);

        $this->logger->debug('saveBookmark()', ['procedureId' => $procedureId, 'name' => $name]);

        return $this->bookmarkRepository->addObject($bookmark);
    }

    /**
     * @param string $bookmarkId ID to identifies the Entity to delete
     *
     * @return bool true if successfully deleted the given entity, otherwise false
     */
    public function deleteBookmark($bookmarkId): bool
    {
        // check if bookmark is owned by current user?!
        $bookmark = $this->bookmarkRepository->get($bookmarkId);

        return $this->bookmarkRepository->deleteObject($bookmark);
    }
}
