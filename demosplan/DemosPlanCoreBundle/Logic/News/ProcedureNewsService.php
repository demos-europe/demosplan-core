<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\News;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\Services\ProcedureNewsServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\NoDesignatedStateException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\ManualListSorter;
use demosplan\DemosPlanCoreBundle\Repository\NewsRepository;
use Doctrine\Common\Collections\Criteria;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\PathException;
use Exception;
use InvalidArgumentException;

class ProcedureNewsService extends CoreService implements ProcedureNewsServiceInterface
{
    /**
     * @var FileService
     */
    protected $fileService;

    public function __construct(
        private readonly DateHelper $dateHelper,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly EntityHelper $entityHelper,
        FileService $fileService,
        private readonly ManualListSorter $manualListSorter,
        private readonly NewsRepository $newsRepository,
        private readonly SortMethodFactory $sortMethodFactory,
    ) {
        $this->fileService = $fileService;
    }

    /**
     * Ruft alle News eines Verfahrens ab
     * Die News müssen freigeschaltet sein (enable = true).
     *
     * @param string      $procedureId
     * @param User|null   $user
     * @param string|null $manualSortScope
     * @param int|null    $limit
     * @param array       $roles           Rollenbezeichnung
     *
     * @return array
     *
     * @throws PathException
     */
    public function getNewsList($procedureId, $user, $manualSortScope = null, $limit = null, $roles = [])
    {
        $conditions = [
            $this->conditionFactory->propertyHasValue(false, ['deleted']),
            $this->conditionFactory->propertyHasValue(true, ['enabled']),
            $this->conditionFactory->propertyHasValue($procedureId, ['pId']),
        ];

        $roles = $this->determineRoles($roles, $user);
        if (isset($roles) && 0 < count($roles)) {
            $conditions[] = [] === $roles
                ? $this->conditionFactory->false()
                : $this->conditionFactory->propertyHasAnyOfValues($roles, ['roles', 'code']);
        }

        $sortMethod = $this->sortMethodFactory->propertyDescending(['createDate']);

        $news = $this->newsRepository->getEntities($conditions, [$sortMethod]);

        // Legacy Arrays
        $result = [];
        foreach ($news as $singleNews) {
            $result[] = $this->convertToLegacy($singleNews);
        }
        // Is the list manual sorted?
        if (is_string($manualSortScope) && 0 < strlen($manualSortScope)) {
            $sorted = $this->manualListSorter->orderByManualListSort($manualSortScope, $procedureId, 'news', $result);
            $result = $sorted['list'];
        }

        // Is a limit given?
        if (isset($limit) && 0 < $limit) {
            // shorten the list of entries to the given limit
            $result = array_slice($result, 0, $limit);
        }

        return ['result' => $result];
    }

    /**
     * Ruft alle News eines Verfahrens ab
     * Die News müssen freigeschaltet sein (enable = true).
     *
     * @param string      $procedureId
     * @param string|null $manualSortScope
     *
     * @return array
     */
    public function getProcedureNewsAdminList($procedureId, $manualSortScope = null)
    {
        $news = $this->newsRepository->findBy([
            'deleted' => false,
            'pId'     => $procedureId,
        ], [
            'createDate' => Criteria::DESC,
        ]);

        // Legacy Arrays
        $result = [];
        foreach ($news as $singleNews) {
            $result[] = $this->convertToLegacy($singleNews);
        }
        // Is the list manual sorted?
        if (isset($manualSortScope) && 0 < strlen($manualSortScope)) {
            $sorted = $this->manualListSorter->orderByManualListSort($manualSortScope, $procedureId, 'news', $result);
            $result = $sorted['list'];
        }

        return ['result' => $result];
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
        try {
            $singleNews = $this->newsRepository->get($ident);

            return $this->convertToLegacy($singleNews);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines NewsEntry: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add a news-entry to the DB.
     *
     * @param array $data - Includes the values, which will be used to create a object and map these object to the DB
     *
     * @return array - The added Object as an array
     *
     * @throws Exception
     */
    public function addNews($data)
    {
        try {
            $singleNews = $this->newsRepository->add($data);

            // convert to Legacy Array
            return $this->convertToLegacy($singleNews);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Anlegen eines Newsbeitrags: ', [$e]);
            throw $e;
        }
    }

    /**
     * Saves the order of the given idents.
     *
     * @param string $procedureId - ID of the manualSort-entry
     * @param string $sortIds     - comma separated list of ID to sort. (empty to delete the order)
     *
     * @return bool - true, if the new order was saved, otherwise false
     *
     * @throws Exception
     */
    public function setManualSortOfNews($procedureId, $sortIds): bool
    {
        // keine leerzeichen zwischen den Ids
        $sortIds = str_replace(' ', '', $sortIds);

        $data = [
            'ident'     => $procedureId,
            'namespace' => 'news',
            'context'   => 'procedure:'.$procedureId, // Der Bezug unter dem die manuelle Sortierung gespeichert wurde. z.B. orga:{ident} oder user:{ident} / ident = ID ohne Klammer
            'sortIdent' => $sortIds,
        ];

        return $this->manualListSorter->setManualSort($data['context'], $data);
    }

    /**
     * Update eines Newsbeitrages.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function updateNews($data)
    {
        try {
            if (!isset($data['ident']) || '' === $data['ident']) {
                throw new InvalidArgumentException('Ident is missing');
            }
            $singleNews = $this->newsRepository->update($data['ident'], $data);

            return $this->convertToLegacy($singleNews);
        } catch (Exception $e) {
            $this->logger->warning('Update SingleNews failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     *  Convert Doctrine Result into legacyformat as pure array without Classes and right names.
     *
     * @param News $singleNews
     *
     * @return array|mixed
     */
    protected function convertToLegacy($singleNews)
    {
        // returnValue, if newsId doesn't exist
        if (!$singleNews instanceof News) {
            // Legacy returnvalues if no singlenews found
            return [
                'enabled' => false,
                'deleted' => false,
            ];
        }

        $roles = $singleNews->getRoles()->getValues();
        $rolesAsArray = [];
        foreach ($roles as $role) {
            $rolesAsArray[] = $this->entityHelper->toArray($role);
        }
        // Transform News into an array
        $singleNews = $this->entityHelper->toArray($singleNews);
        $singleNews['roles'] = $rolesAsArray;

        return $this->dateHelper->convertDatesToLegacy($singleNews);
    }

    /**
     * Returns all News, which designated date to switch state is today.
     *
     * @return News[]
     *
     * @throws Exception
     */
    public function getNewsToSetStateToday(): array
    {
        $today = Carbon::now();
        $newsList = $this->newsRepository->getNewsToAutoSetState();

        return array_filter($newsList, static function (News $news) use ($today) {
            $date = $news->getDesignatedSwitchDate();

            return null !== $date && $today->isSameDay($date);
        });
    }

    /**
     * @param News $news News which state will be switched
     *
     * @throws NoDesignatedStateException
     * @throws Exception
     */
    public function setState(News $news): void
    {
        $designatedState = $news->getDesignatedState();
        if (null === $designatedState) {
            throw new NoDesignatedStateException("designated state of news is null: {$news->getId()}");
        }

        $news->setEnabled($designatedState);
        $news->setDeterminedToSwitch(false);
        $this->newsRepository->updateObject($news);
    }

    private function determineRoles(?array $roles, ?User $user): ?array
    {
        // if no roles are given, take the user roles from session
        if (is_array($roles) && 0 === count($roles)) {
            $roles = [Role::GUEST];
            if (null !== $user) {
                $roles = $user->getRoles();
            }
        }

        // Citizens should see all public news
        if (null === $user || $user->hasRole(Role::CITIZEN)) {
            $roles[] = Role::GUEST;
        }

        return $roles;
    }
}
