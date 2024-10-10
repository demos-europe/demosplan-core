<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\News;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use ReflectionException;

class GlobalNewsHandler extends CoreHandler
{
    /**
     * @var ContentService
     */
    protected $contentService;

    public function __construct(ContentService $contentService, MessageBagInterface $messageBag)
    {
        parent::__construct($messageBag);
        $this->contentService = $contentService;
    }

    /**
     * Ruft alle News eines Verfahrens ab
     * Die News müssen freigeschaltet sein (enable = true).
     *
     * @param int|null $limit
     *
     * @throws ReflectionException
     */
    public function getNewsList(User $user, $limit = null): array
    {
        return $this->contentService->getContentList($user, $limit);
    }

    /**
     * Ruft alle News eines Verfahrens ab
     * Die News müssen freigeschaltet sein (enable = true).
     *
     * @param string $categoryName
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getGlobalNewsAdminList($categoryName = null): array
    {
        return $this->contentService->getContentAdminList($categoryName);
    }

    /**
     * Ruft einen einzelnen Newbeitrag auf.
     *
     * @param string $ident
     *
     * @return array
     *
     * @throws Exception
     */
    public function getSingleNews($ident)
    {
        return $this->contentService->getSingleContent($ident);
    }

    /**
     * Fügt einen Newbeitrag hinzu.
     *
     * @param array $data
     *
     * @return array|null
     *
     * @throws Exception
     */
    public function addNews($data)
    {
        $data['type'] = 'news';
        $data['manualSortScope'] = 'global:news';

        return $this->contentService->addContent($data);
    }

    /**
     * Speichert die manuelle Listensortierung.
     *
     * @param string $sortedNewsIds
     *                              (Komma separierte Liste) / leer zum löschen
     *
     * @throws Exception
     */
    public function setManualSortOfGlobalNews($sortedNewsIds): bool
    {
        return $this->contentService->setManualSortForGlobalContent('global:news', $sortedNewsIds, 'news');
    }

    /**
     * Update eines Newsbeitrages.
     *
     * @param array $data
     *
     * @throws Exception
     */
    public function updateNews($data): array
    {
        return $this->contentService->updateContent($data);
    }
}
