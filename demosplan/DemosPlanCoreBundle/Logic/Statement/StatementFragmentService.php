<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragmentVersion;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\NotAssignedException;
use demosplan\DemosPlanCoreBundle\Exception\NullPointerException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\StatementFragmentNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\FragmentElasticsearchRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementFragmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementFragmentVersionRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterDisplay;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryFragment;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResult;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResultSet;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementFragmentUpdate;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Utilities\Reindexer;
use Elastica\Exception\ClientException;
use Elastica\Index;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchAll;
use Elastica\Query\Terms;
use Elastica\ResultSet;
use Exception;
use Pagerfanta\Elastica\ElasticaAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatementFragmentService extends CoreService
{
    use RefreshElasticsearchIndexTrait;

    /** @var PermissionsInterface */
    protected $permissions;
    /**
     * @var UserService
     */
    protected $userService;

    /** @var ElementsService */
    protected $elementService;

    /** @var AssignService */
    protected $assignService;

    /** @var ParagraphService */
    protected $paragraphService;

    /** @var Index */
    protected $esStatementFragmentType;

    /** @var EntityContentChangeService */
    protected $entityContentChangeService;

    /** @var ProcedureService */
    protected $procedureService;
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var array
     */
    protected $paginatorLimits = [25, 50, 100];

    public function __construct(
        AssignService $assignService,
        private readonly CurrentUserInterface $currentUser,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly ElasticsearchFilterArrayTransformer $elasticsearchFilterArrayTransformer,
        private readonly ElasticSearchService $searchService,
        ElementsService $elementService,
        EntityContentChangeService $entityContentChangeService,
        private readonly EntityHelper $entityHelper,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly ManagerRegistry $managerRegistry,
        private readonly MessageBagInterface $messageBag,
        ParagraphService $paragraphService,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        private readonly Reindexer $reindexer,
        private readonly SortMethodFactory $sortMethodFactory,
        private readonly StatementFragmentRepository $statementFragmentRepository,
        private readonly StatementFragmentVersionRepository $statementFragmentVersionRepository,
        private readonly StatementService $statementService,
        TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        UserService $userService,
    ) {
        $this->assignService = $assignService;
        $this->elementService = $elementService;
        $this->entityContentChangeService = $entityContentChangeService;
        $this->paragraphService = $paragraphService;
        $this->permissions = $permissions;
        $this->procedureService = $procedureService;
        $this->translator = $translator;
        $this->userService = $userService;
    }

    public function setEsStatementFragmentType(Index $esStatementFragmentType)
    {
        $this->esStatementFragmentType = $esStatementFragmentType;
    }

    /**
     * Retrieve a StatementFragment.
     *
     * @return StatementFragment|null
     */
    public function getStatementFragment(string $fragmentId)
    {
        try {
            return $this->statementFragmentRepository->get($fragmentId);
        } catch (Exception $e) {
            $this->logger->error('Could not find StatementFragment with id '.DemosPlanTools::varExport($fragmentId, true).': ', [$e]);

            return null;
        }
    }

    /**
     * @throws StatementFragmentNotFoundException
     */
    public function getNonNullStatementFragment(string $fragmentId): StatementFragment
    {
        $fragment = $this->getStatementFragment($fragmentId);
        if (null === $fragment) {
            throw StatementFragmentNotFoundException::createFromId($fragmentId);
        }

        return $fragment;
    }

    /**
     * Returns the current assigned user of the given fragment.
     * Returns null if no user is assigned or the given fragment was not found.
     *
     * @param string $fragmentId
     *
     * @return User|null
     */
    public function getAssigneeOfFragment($fragmentId)
    {
        $fragment = $this->getStatementFragment($fragmentId);

        return null === $fragment ? null : $fragment->getAssignee();
    }

    /**
     * Check if the given Fragment is assigned to the current user.
     *
     * @param StatementFragment|array $fragment
     *
     * @return bool - true if the given Fragment is assigned to the current user, otherwise false
     *
     * @throws UserNotFoundException
     */
    public function isFragmentAssignedToCurrentUser($fragment): bool
    {
        // the Fragment is assigned to current user if:
        //  - there is a assigned user and
        //  - the assigned user is the current user
        $fragmentId = $this->entityHelper->extractId($fragment);
        $assignedUser = $this->getAssigneeOfFragment($fragmentId);
        $assignedUserId = null === $assignedUser ? null : $assignedUser->getId();

        return null !== $this->currentUser->getUser()->getId() && $this->currentUser->getUser()->getId() === $assignedUserId;
    }

    /**
     * Deletes a StatementFragment.
     *
     * @param string $statementFragmentId
     * @param bool   $ignoreAssignment    - Force bypass checks for assignment of fragment to delete
     *
     * @return bool - true if successfully deleted, otherwise false
     *
     * @throws EntityIdNotFoundException
     * @throws LockedByAssignmentException
     */
    public function deleteStatementFragment($statementFragmentId, bool $ignoreAssignment = false): bool
    {
        $fragmentToDelete = $this->statementFragmentRepository->get($statementFragmentId);

        if (null === $fragmentToDelete) {
            $this->getLogger()->warning('Fehler beim Löschen eines Fragments: Fragment '.$statementFragmentId.' nicht gefunden.');
            throw new EntityIdNotFoundException(sprintf('Fragment-ID not found: %s', $statementFragmentId));
        }

        $ignoreAssignment = $ignoreAssignment || (false === $this->permissions->hasPermission(
            'feature_statement_assignment'
        ));
        $noAssignee = null === $fragmentToDelete->getAssignee();
        $assignedToCurrentUser = $this->isFragmentAssignedToCurrentUser($fragmentToDelete);
        $lockedByAssignment = !($ignoreAssignment || $noAssignee || $assignedToCurrentUser);

        if ($lockedByAssignment) {
            throw new LockedByAssignmentException(sprintf('Fragment is locked by assignment: %s', $statementFragmentId));
        }

        try {
            $fragmentToDelete->getStatement()->removeFragment($fragmentToDelete);
            // T12692: version/entityContentChange on createFragment? -> take a look in the history of this method
            $this->statementFragmentRepository->delete($fragmentToDelete);

            $success = true;
        } catch (Exception $e) {
            $this->getLogger()->error('Fehler beim Löschen eines StatementFragments: ', [$e]);
            $success = false;
        }

        return $success;
    }

    /**
     * Filter fragment array and keep only allowed and modified fields.
     *
     * @param array $fragment
     *
     * @return array
     */
    public function getPlannerVersionedFields($fragment)
    {
        $currentValues = [
            'consideration' => null,
            'vote'          => null,
        ];

        $fieldsToReturn = [
            'consideration',
            'counties',
            'created',
            'municipalityNamesAsJson',
            'priorityAreaNamesAsJson',
            'tagAndTopicNames',
            'id',
            'userName',
        ];

        if ($this->permissions->hasPermission('feature_statements_fragment_vote')) {
            $fieldsToReturn[] = 'vote';
        }

        // fragment currently not assigned to department (to set advice)
        // and current user has permission to set vote -> return voteAdvice
        if (null === $fragment['departmentId'] && $this->permissions->hasPermission('feature_statements_fragment_vote')) {
            $fieldsToReturn[] = 'voteAdvice';
        }

        $userService = $this->userService;

        return \collect($fragment['versions'])
            ->filter(
                function ($version) use (&$currentValues) {
                    return $this->hasModifiedValues($version, $currentValues);
                }
            )
            ->transform(static function ($fragment) use ($fieldsToReturn, $userService) {
                $user = $userService->getSingleUser($fragment['modifiedByUserId']);
                if ($user instanceof User) {
                    $fragment['userName'] = $user->getFullname();
                }

                return \collect($fragment)
                    ->only($fieldsToReturn)
                    ->toArray();
            })
            ->values()
            ->toArray();
    }

    /**
     * Filter fragment array and keep only allowed and modified fields.
     *
     * @param array  $fragment
     * @param string $departmentId
     *
     * @return array
     */
    public function getReviewerVersionedFields($fragment, $departmentId)
    {
        $currentValues = [
            'considerationAdvice' => null,
            'voteAdvice'          => null,
        ];
        $fieldsToReturn = ['considerationAdvice', 'voteAdvice', 'created', 'id'];
        // return only versions created by this department
        // modifications within given fields
        // only fields to return
        $versions = \collect($fragment['versions'])
            ->filter(
                fn ($version) => array_key_exists('modifiedByDepartmentId', $version)
                    && $version['modifiedByDepartmentId'] == $departmentId
            )->filter(
                function ($version) use (&$currentValues) {
                    return $this->hasModifiedValues($version, $currentValues);
                }
            )
            ->transform(fn ($fragment) => \collect($fragment)
                ->only($fieldsToReturn)
                ->toArray())
            ->values()
            ->toArray();

        return $versions;
    }

    /**
     * Get StatementFragments for existing Statement.
     *
     * @param string $statementId
     *
     * @return StatementFragment[]|null
     */
    public function getStatementFragmentsStatement($statementId)
    {
        try {
            return $this->statementFragmentRepository->findBy([
                'statement' => $statementId,
            ], [
                'sortIndex' => Criteria::ASC,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Could not get StatementFragment List', [$e]);

            return null;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $fragments
     *
     * @return array<int,array<string,mixed>>
     */
    public function sortFragmentArraysBySortIndex(array $fragments): array
    {
        return \collect($fragments)->sortBy('sortIndex')->values()->all();
    }

    /**
     * Add Fragments as a copy to a Statement.
     *
     * @param Collection<int, StatementFragment> $fragmentsToCopy
     * @param bool                               $ignoreReviewer  true in case of skip check of fragment is assigned to a reviewer and copy fragments anyway
     *
     * @return Statement|null
     */
    public function copyStatementFragments(Collection $fragmentsToCopy, Statement $statementToCopyTo, bool $ignoreReviewer = false)
    {
        try {
            foreach ($fragmentsToCopy as $fragmentToCopy) {
                if (!$fragmentToCopy instanceof StatementFragment) {
                    $this->getLogger()->error('Fragment to copy should be a fragment');
                }

                $permissionEnabled = $this->permissions->hasPermission('feature_statement_assignment');
                $statement = $fragmentToCopy->getStatement();

                // T5140: unable to create Fragment if permission is enabled and Statement is not assigned to current user
                $statementAssignedToCurrentUser = $this->assignService->isStatementObjectAssignedToCurrentUser($statement);
                if ($permissionEnabled && !$statementAssignedToCurrentUser) {
                    $this->getLogger()->error(
                        'Tried to copy fragments of an unassigned Statement.'
                    );
                    throw NotAssignedException::mustBeAssignedException();
                }

                // T5505 do not copy fragment if assigned ro reviewer
                if (!$ignoreReviewer && $permissionEnabled && null !== $fragmentToCopy->getDepartmentId()) {
                    $this->getLogger()->error('Tried to copy an fragment that is assigned to a reviewer.');
                    $this->messageBag->add(
                        'warning',
                        'warning.statement.copy.fragment.assigned.to.reviewer',
                        ['statementId' => $statement->getExternId()]
                    );

                    return $statementToCopyTo;
                }

                $newFragment = clone $fragmentToCopy;
                $newFragment->setId(null);
                $newFragment->setStatement($statementToCopyTo);
                $newFragment->setProcedure($statementToCopyTo->getProcedure());

                // copy to another procedure?
                if ($fragmentToCopy->getProcedureId() !== $statementToCopyTo->getProcedureId()) {
                    $this->moveFragmentIntoProcedure($newFragment, $fragmentToCopy);
                }

                $this->statementFragmentRepository->addObject($newFragment);
                $this->getLogger()->debug('Cluster single fragment copied');
            }
        } catch (NotAssignedException) {
            throw NotAssignedException::mustBeAssignedException();
        } catch (Exception $e) {
            $this->getLogger()->error('Could not copy StatementFragment', [$e]);

            return null;
        }

        return $statementToCopyTo;
    }

    /**
     * T13109: enable moving/coping StatementFragments into another procedures.
     *
     * @throws StatementElementNotFoundException
     */
    public function moveFragmentIntoProcedure(StatementFragment $newFragment, StatementFragment $fragmentToCopy): StatementFragment
    {
        // todo write test
        $newFragment->setCreated(new DateTime());
        $newFragment->setModified(new DateTime());
        $newFragment->setDisplayId(null);
        $newFragment->setLastClaimed(null);
        $newFragment->setAssignee(null);
        // needed because on check if statement is allowd to copy, check for department is disabled by assuming
        // department will be set to null
        $newFragment->setDepartment(null);

        $newFragment->setElement(null);
        // if "Gesamtstellungnahme", is it possible to create association:
        if (null !== $fragmentToCopy->getElement() && $this->elementService->isStatementElement($fragmentToCopy->getElement())) {
            $statementElement = $this->elementService->getStatementElement($fragmentToCopy->getProcedureId());
            $newFragment->setElement($statementElement);
        }

        // procedure specific -> impossible to keep:
        $newFragment->setParagraph(null);
        $newFragment->setDocument(null);
        $newFragment->setTags([]);

        // could be a new user-story, but this is may not reasonable, because new procedure means new context:
        // T14366: set to null to avoid determine one of these fields as already modified
        $newFragment->setConsideration(null);
        $newFragment->setConsiderationAdvice(null);
        $newFragment->setVoteAdvice(null);
        $newFragment->setVote(null);

        // related fields are overwritten, therefore no need to store the authors:
        $newFragment->setArchivedOrgaName(null);
        $newFragment->setArchivedDepartmentName(null);
        $newFragment->setArchivedDepartment(null);
        $newFragment->setArchivedVoteUserName(null);
        $newFragment->setVersions([]);
        $newFragment->setModifiedByUser(null);
        $newFragment->setModifiedByDepartment(null);

        $newFragment->setCounties([]);
        $newFragment->setMunicipalities([]);
        $newFragment->setPriorityAreas([]);

        /** @var County $county */
        foreach ($fragmentToCopy->getCounties() as $county) {
            $newFragment->addCounty($county);
        }

        /** @var Municipality $municipality */
        foreach ($fragmentToCopy->getMunicipalities() as $municipality) {
            $newFragment->addMunicipality($municipality);
        }

        /** @var PriorityArea $priorityArea */
        foreach ($fragmentToCopy->getPriorityAreas() as $priorityArea) {
            $newFragment->addPriorityArea($priorityArea);
        }

        return $newFragment;
    }

    /**
     * This method basically uses the fingerprint principle: Whoever touches (claims = assignee) a DS, has his/her fingerprint on it (is lastClaimedId). Unless that person is a reviewer (uses gloves).
     *
     * Use after all other changes have been done to the object, but (of course) before the object is updated in the database.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/claim/ wiki: claiming
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function updateLastClaimedIdInCurrentObjectAfterAllOtherChanges(StatementFragment $fragmentObject): StatementFragment
    {
        // @improve T12392

        // get variables
        $assignee = $fragmentObject->getAssignee();
        $currentUserLayerObject = $this->currentUser->getUser();
        if (null === $currentUserLayerObject) {
            throw new NullPointerException('Current user is null.');
        }
        $currentUserUserObject = $this->userRepository->get($currentUserLayerObject->getId());
        if (!$currentUserUserObject) {
            throw new NullPointerException('Current user is null.');
        }

        // case 1 - claiming:
        // case 1a - anyone except Fachplaner becomes lastClaimed when claiming
        if (null !== $assignee && Role::PLANNING_SUPPORTING_DEPARTMENT !== $assignee->getDplanRolesString()) {
            $fragmentObject->setLastClaimed($assignee);

            return $fragmentObject;
        }
        // case 2a: when unclaiming or when a fragment is created, always ensure there is a lastClaimed as long as there is an organization
        if (null === $assignee && null !== $fragmentObject->getDepartment()) {
            $fragmentObject->setLastClaimed($currentUserUserObject);

            return $fragmentObject;
        }

        // case 2 - unclaiming: since assignee is null but only current users can unclaim, we can take that
        if (null === $assignee
            && null === $fragmentObject->getDepartment()
            && Role::PLANNING_SUPPORTING_DEPARTMENT !== $currentUserLayerObject->getRole()
        ) {
            // case 2a - the DS is being unclaimed by a reviewer. those are never set as lastClaimedId, so no need to reset
            // case 2b - the current user is not a reviewer, but the DS has a department. then keep the lastClaimedId
            // case 2c - current user is a reviewer and there is no department set. then reset lastClaimedId
            $fragmentObject->setLastClaimed();

            return $fragmentObject;
        }

        // if no changes occurred, return original object
        return $fragmentObject;
    }

    /**
     * Returns StatementFragments, related to a specific Department.
     *
     * @param QueryFragment $esQuery
     * @param array         $requestValues
     *
     * @return array|StatementFragment[]|null
     *
     * @throws Exception
     */
    public function getStatementFragmentsDepartment($esQuery, $requestValues): ?array
    {
        try {
            $repos = $this->getFragmentElasticsearchRepository();
            $availableFilters = $esQuery->getAvailableFilters();
            // Remember this we replace '.' with '_' stuff when getting request params ...
            /** @var FilterDisplay $filterDisplay */
            foreach ($availableFilters as $filterDisplay) {
                if (array_key_exists($filterDisplay->getName(), $requestValues)) {
                    $filterValues = $requestValues[$filterDisplay->getName()];
                    if (in_array('', $filterValues)) {
                        $esQuery->addFilterMustMissing($filterDisplay->getAggregationField());
                        unset($filterValues[array_search('', $filterValues)]);
                    }
                    if ((is_countable($filterValues) ? count($filterValues) : 0) > 0) {
                        $esQuery->addFilterMust($filterDisplay->getAggregationField(), $filterValues);
                    }
                }
            }
            $this->profilerStart('ES');
            $fragmentList = $repos->searchFragments($esQuery);
            $this->profilerStop('ES');
            $resultList = [];
            foreach ($fragmentList['hits']['hits'] as $fragment) {
                $resultList[] = $fragment['_source'];
            }

            return $resultList;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der getStatementFragmentsDepartment: ', [$e]);

            return null;
        }
    }

    /**
     * Returns StatementFragment versions of a fragment.
     *
     * @param string $fragmentId
     * @param string $departmentId
     * @param bool   $isReviewer
     */
    public function getStatementFragmentVersions($fragmentId, $departmentId, $isReviewer = true): ?array
    {
        $filters = [];
        try {
            $filters['id'] = $fragmentId;
            // reviewers should only see versions made by their own department
            if ($isReviewer) {
                $filters['modifiedOrArchivedByDepartmentId'] = $departmentId;
            }
            $filters['includeVersions'] = true;
            $esResult = $this->getElasticsearchStatementFragmentResult($filters);
            $esResult = $this->searchService->simplifyEsStructure($esResult, '', [], null, 'result');
            $fieldVersions = [];
            // compute versioned Fields
            foreach ($esResult->getResult() as $fragment) {
                if ($fragment['id'] != $fragmentId) {
                    continue;
                }

                if ($isReviewer) {
                    $versions = $this->getReviewerVersionedFields($fragment, $departmentId);
                } else {
                    $versions = $this->getPlannerVersionedFields($fragment);
                }

                // check whether versions are left
                if (0 === count($versions)) {
                    continue;
                }
                // Only one Fragment should be left, so there is no need to use multidimensional array
                $fieldVersions = $versions;
                break;
            }
        } catch (Exception $e) {
            $this->logger->error('Could not get StatementFragment Versions ', [$e]);

            return null;
        }

        return $fieldVersions;
    }

    /**
     * Get all StatementFragments for a procedur.
     *
     * @param int $limit
     * @param int $page
     */
    public function getStatementFragmentsProcedure(string $procedureId, $limit = 0, $page = 1): ElasticsearchResultSet
    {
        $filters = [];
        try {
            $filters['procedureId'] = $procedureId;
            $esResult = $this->getElasticsearchStatementFragmentResult($filters, '', null, $limit, $page);
            $esResult = $this->searchService->simplifyEsStructure($esResult, '', [], null, 'result');
        } catch (Exception $e) {
            $this->logger->error('Could not get StatementFragment Procedure List', [$e]);

            return (new ElasticsearchResultSet())->lock();
        }

        return $esResult;
    }

    /**
     * Returns StatementFragments, related to a specific Statement.
     *
     * @param string|string[] $statementId
     * @param array           $filters
     * @param string          $search
     * @param int             $limit
     * @param int             $page
     */
    public function getStatementFragmentsStatementES($statementId, $filters, $search = '', $limit = 10000, $page = 1): ElasticsearchResultSet
    {
        $userFilters = [];
        try {
            $userFilters['statementId'] = $statementId;

            // incoming => userFilters
            // please check for possible override logic before sorting this alphabetically
            $filterMapArrays = [
                'priorityAreaKeys'  => 'priorityAreaKeys',
                'municipalityNames' => 'municipalityNames',
                'countyNames'       => 'countyNames',
                'tagNames'          => 'tagNames',
                'fragments_status'  => 'status',
                'status'            => 'status',
            ];

            foreach ($filterMapArrays as $incomingKey => $userFilterKey) {
                if (array_key_exists($incomingKey, $filters) && 0 < (is_countable($filters[$incomingKey]) ? count($filters[$incomingKey]) : 0)) {
                    $userFilters[$userFilterKey] = $filters[$incomingKey];
                }
            }

            // please check for possible override logic before sorting this alphabetically
            $filterMapStrings = [
                'fragments_lastClaimed_id'    => 'lastClaimedUserId',
                'lastClaimedUserId'           => 'lastClaimedUserId',
                'fragments_vote'              => 'vote',
                'vote'                        => 'vote',
                'fragments_voteAdvice'        => 'voteAdvice',
                'fragments_element'           => 'elementId',
                'element'                     => 'elementId',
                'fragments_paragraphParentId' => 'paragraphParentId',
                'paragraphParentId'           => 'paragraphParentId',
                'fragments.paragraphParentId' => 'paragraphParentId',
                'departmentId'                => 'departmentId',
                'fragments_documentParentId'  => 'documentParentId',
                'documentParentId'            => 'documentParentId',
                'fragments_countyNames'       => 'countyNames',
                'countyNames'                 => 'countyNames',
                'fragments_municipalityNames' => 'municipalityNames',
                'municipalityNames'           => 'municipalityNames',
                'fragments_tagNames'          => 'tagNames',
                'fragments.priorityAreaKeys'  => 'priorityAreaKeys',
            ];

            foreach ($filterMapStrings as $incomingKeyString => $userFilterKeyString) {
                if (array_key_exists($incomingKeyString, $filters)) {
                    $userFilters[$userFilterKeyString] = $filters[$incomingKeyString];
                }
            }

            $esResult = $this->getElasticsearchStatementFragmentResult($userFilters, $search, null, $limit, $page, [], false);
            $esResult = $this->searchService->simplifyEsStructure($esResult, '', [], null, 'result');
        } catch (Exception $e) {
            $this->logger->error('Could not get StatementFragment Statement List', [$e]);

            return (new ElasticsearchResultSet())->lock();
        }

        return $esResult;
    }

    /**
     * Determines if every Fragment are assigned to the current user.
     *
     * @param string $statementId
     *
     * @return bool - true if every Fragment of the given Statement is assigned to the current user, otherwise false
     *
     * @throws UserNotFoundException
     */
    public function areAllFragmentsClaimedByCurrentUser($statementId): bool
    {
        $fragments = $this->getStatementFragmentsStatement($statementId);
        foreach ($fragments as $fragment) {
            if (!$this->isFragmentAssignedToCurrentUser($fragment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines if no Fragment is assigned reviewer.
     *
     * @param string $statementId
     *
     * @return bool - true if no Fragment of the given Statement is assigned to any reviewer, otherwise false
     */
    public function isNoFragmentAssignedToReviewer($statementId): bool
    {
        $fragments = $this->getStatementFragmentsStatement($statementId);
        foreach ($fragments as $fragment) {
            if (null !== $fragment->getDepartmentId()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return StatementFragmentVersion|null
     */
    public function createStatementFragmentVersion(StatementFragmentVersion $fragmentVersion)
    {
        try {
            $result = $this->statementFragmentVersionRepository->addObject($fragmentVersion);
        } catch (Exception $e) {
            $this->logger->error('Could not create StatementFragmentVersion', [$e]);

            return null;
        }

        return $result;
    }

    /**
     * @param array $data
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws Exception
     */
    public function createStatementFragmentIgnoreAssignment($data): StatementFragment
    {
        $statementFragment = $this->statementFragmentRepository->generateObjectValues(
            new StatementFragment(),
            $data
        );
        $statementFragment = $this->updateLastClaimedIdInCurrentObjectAfterAllOtherChanges($statementFragment);
        $statementFragment = $this->statementFragmentRepository->addObject($statementFragment);
        $statementFragment->getStatement()->addFragment($statementFragment);
        // T12692: version/entityContentChange on createFragment? -> take a look in the history of this method

        $version = new StatementFragmentVersion($statementFragment);
        $this->statementFragmentVersionRepository->addObject($version);

        return $statementFragment;
    }

    /**
     * Update StatementFragment Object.
     *
     * @return StatementFragment|false|null
     *
     * @throws Exception
     */
    public function updateStatementFragmentObject(StatementFragment $fragment)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $user = $this->currentUser->getUser();

            if ($user instanceof User) {
                $fragment->setModifiedByUser(
                    $em->find(User::class, $user->getId())
                );
                $fragment->setModifiedByDepartment(
                    $em->find(Department::class, $user->getDepartmentId())
                );

                if (null === $user->getDepartmentId()) {
                    $this->getLogger()->error('Current User does not have a department!');
                }
            }

            $fragment = $this->updateLastClaimedIdInCurrentObjectAfterAllOtherChanges($fragment);
            $result = $this->statementFragmentRepository->updateObject($fragment);

            // only create version on update of Fragment:
            if ($result instanceof StatementFragment) {
                $version = new StatementFragmentVersion($result);

                if (null === $version->getModifiedByDepartment()) {
                    $this->getLogger()->error('A StatementFragmentVersion was created without a modifiedByDepartment');
                }

                if (null === $version->getModifiedByUser()) {
                    $this->getLogger()->error('A StatementFragmentVersion was created without a modifiedByUser');
                }

                $this->createStatementFragmentVersion($version);
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Could not update StatementFragment', [$e]);

            return null;
        }

        return $result;
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    public function getParagraphVersionsForFragmentArray(array $fragmentArray)
    {
        $em = $this->getDoctrine()->getManager();
        $fragmentId = $this->entityHelper->extractId($fragmentArray);
        $currentFragment = $this->getStatementFragment($fragmentId);

        if (array_key_exists('paragraph', $fragmentArray)
            && $fragmentArray['paragraph'] instanceof Paragraph
            && $fragmentArray['paragraph']->getId() != $currentFragment->getParagraphId()) {
            $fragmentArray['paragraph'] = $this->paragraphService->createParagraphVersion($fragmentArray['paragraph']);
        }

        // Wenn das Fragment einen Absatz hat lege eine Version an, wenn sich der Absatz verändert hat
        if (array_key_exists('paragraphId', $fragmentArray)
            && 0 < \strlen((string) $fragmentArray['paragraphId'])
            && $fragmentArray['paragraphId'] != $currentFragment->getParagraphId()) {
            $paragraphVersion = $em->find(
                Paragraph::class,
                $fragmentArray['paragraphId']);
            $fragmentArray['paragraph'] = $this->paragraphService->createParagraphVersion($paragraphVersion);
        }

        return $fragmentArray;
    }

    /**
     * Update StatementFragment with an fragmentID and a array of data to update.
     *
     * @param string $fragmentId
     * @param array  $data
     *
     * @return StatementFragment|false|null
     *
     * @throws Exception
     */
    public function updateStatementFragmentArray($fragmentId, $data)
    {
        try {
            $fragment = $this->getStatementFragment($fragmentId);

            if (null === $fragment) {
                throw new Exception('Fragment with ID '.$fragmentId.' not found.');
            }

            $user = $this->currentUser->getUser();
            if ($user instanceof User) {
                $data['modifiedByDepartmentId'] = $user->getDepartmentId();
                $data['modifiedByUserId'] = $user->getId();
            }

            $result = $this->statementFragmentRepository->update($fragmentId, $data);

            if (!$result instanceof StatementFragment) {
                return false;
            }

            // It's suboptimal to update the same object twice, but since this method will be deleted anyway
            // once we refactor the code and only work with objects, I consider this an acceptable compromise.
            // Imho still more elegant than creating an array clone for the method
            // updateLastClaimedIdInCurrentObjectAfterAllOtherChanges
            $result = $this->updateLastClaimedIdInCurrentObjectAfterAllOtherChanges($result);
            $result = $this->statementFragmentRepository->updateObject($result);

            // only create version on update of Fragment:
            if ($result instanceof StatementFragment) {
                $version = new StatementFragmentVersion($result);

                if (null === $version->getModifiedByDepartment()) {
                    $this->getLogger()->error('A StatementFragmentVersion was created without a modifiedByDepartment');
                }

                if (null === $version->getModifiedByUser()) {
                    $this->getLogger()->error('A StatementFragmentVersion was created without a modifiedByUser');
                }

                $this->createStatementFragmentVersion($version);
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Could not update StatementFragment', [$e]);

            return null;
        }

        return $result;
    }

    /**
     * Returns latest versions of archived StatementFragments, related to a specific Department.
     *
     * @param QueryFragment $esQuery
     * @param array         $requestValues
     * @param string        $departmentId
     *
     * @return array|StatementFragment[]|null
     */
    public function getStatementFragmentsDepartmentArchive($esQuery, $requestValues, $departmentId)
    {
        $result = [];
        try {
            foreach ($requestValues as $filterName => $filterValue) {
                if (!str_starts_with($filterName, '_raw')) {
                    unset($requestValues[$filterName]);
                    $requestValues[\str_replace('_raw', '.raw', $filterName)] = $filterValue;
                }
            }
            $fragmentList = $this->getStatementFragmentsDepartment($esQuery, $requestValues);

            if (0 === count((array) $fragmentList)) {
                return $result;
            }

            // $fragmentList contains current StatementFragment. We do only need latest Version
            // created by departmentId to display them in the archive list
            $result = [];
            foreach ($fragmentList as $fragment) {
                foreach ($fragment['versions'] as $version) {
                    if ($version['modifiedByDepartmentId'] == $departmentId) {
                        $version['statementFragment'] = $fragment;
                        unset($version['statementFragment']['versions']);
                        $result[] = $version;
                        break;
                    }
                }
            }
            // bring structure of each fragmentVersion into line with structure of fragment
            foreach ($result as $key => $latestVersion) {
                // copy static fields:
                $result[$key]['id'] = $latestVersion['statementFragment']['id'];
                $result[$key]['statement'] = $latestVersion['statementFragment']['statement'];
                $result[$key]['statementId'] = $latestVersion['statementFragment']['statementId'];
                $result[$key]['procedureId'] = $latestVersion['statementFragment']['procedureId'];
                $result[$key]['procedureName'] = $latestVersion['statementFragment']['procedureName'];
                $result[$key]['displayId'] = $latestVersion['statementFragment']['displayId'];
                $result[$key]['elementId'] = $latestVersion['statementFragment']['elementId'];

                // unset because reviewer only:
                unset($result[$key]['vote'], $result[$key]['consideration']);

                // special format:
                // tags:
                $decoded = Json::decodeToMatchingType($latestVersion['tagAndTopicNames']);
                $topics = is_array($decoded) ? $decoded : [];
                $tagsOfVersion = \collect([]);
                foreach ($topics as $topicTitle => $tags) {
                    $newTag = \collect([]);
                    foreach ($tags as $tagTitle) {
                        $newTag->put('topicTitle', $topicTitle);
                        $newTag->put('title', $tagTitle);
                        $result[$key]['tagNames'][] = $topicTitle;
                    }
                    $tagsOfVersion->push($newTag->toArray());
                }
                $result[$key]['tags'] = $tagsOfVersion->toArray();

                // counties:
                $result[$key]['counties'] = [];
                $decoded = Json::decodeToMatchingType($latestVersion['countyNamesAsJson']);
                $countyNames = is_array($decoded) ? $decoded : [];
                foreach ($countyNames as $countyName) {
                    $result[$key]['counties'][]['name'] = $countyName;
                    $result[$key]['countyNames'][] = $countyName;
                }

                // municipalities:
                $result[$key]['municipalities'] = [];
                $decoded = Json::decodeToMatchingType($latestVersion['municipalityNamesAsJson']);
                $municipalityNames = is_array($decoded) ? $decoded : [];
                foreach ($municipalityNames as $municipalityName) {
                    $result[$key]['municipalities'][]['name'] = $municipalityName;
                    $result[$key]['municipalityNames'][] = $municipalityName;
                }

                // priorityAreas:
                $result[$key]['priorityAreas'] = [];
                $decoded = Json::decodeToMatchingType($latestVersion['priorityAreaNamesAsJson']);
                $priorityAreaKeys = is_array($decoded) ? $decoded : [];
                foreach ($priorityAreaKeys as $priorityAreaKey) {
                    $result[$key]['priorityAreas'][]['key'] = $priorityAreaKey;
                    $result[$key]['priorityAreaKeys'][] = $priorityAreaKey;
                }
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Could not get StatementFragment Department Archive List', [$e]);

            return null;
        }
    }

    public function getPaginatorLimits(): array
    {
        return $this->paginatorLimits;
    }

    public function getVoteLabelMap(): array
    {
        return [
            'full'           => $this->translator->trans('fragment.vote.full'),
            'partial'        => $this->translator->trans('fragment.vote.partial'),
            'acknowledge'    => $this->translator->trans('fragment.vote.acknowledge'),
            'no'             => $this->translator->trans('fragment.vote.no'),
            'none'           => $this->translator->trans('fragment.vote.none'),
            'keinezuordnung' => $this->translator->trans('keinezuordnung'),
        ];
    }

    /**
     * Gets Aggegations from Elasticsearch to use as facetted filters.
     *
     * @param array  $userFilters
     * @param string $search
     * @param null   $sort
     * @param int    $limit
     * @param int    $page
     * @param array  $searchFields
     * @param bool   $addAllAggregations - If true all aggregations will be used. Otherwise only those fields in $userFilters
     */
    public function getElasticsearchStatementFragmentResult(
        $userFilters,
        $search = '',
        $sort = null,
        $limit = 0,
        $page = 1,
        $searchFields = [],
        $addAllAggregations = true,
    ): ElasticsearchResult {
        $elasticsearchResultStatement = new ElasticsearchResult();
        $boolQuery = new BoolQuery();
        $aggregation = [];
        $result = [];
        try {
            $this->profilerStart('ES');

            // if a Searchterm is set use it
            if (is_string($search) && 0 < \strlen($search)) {
                $availableSearchfields = [
                    'fragment_text'           => 'text.text',
                    'municipalityNames'       => 'municipalityNames.raw',
                    'displayId'               => 'displayId',
                    'statement.externId'      => 'statement.externId',
                    'priorityAreaKeys'        => 'priorityAreaKeys',
                    'countyNames'             => 'countyNames.raw',
                    'tagNames'                => 'tagNames.text',
                    'consideration'           => 'consideration.text',
                    'fragments_consideration' => 'consideration.text',
                    'elementTitle'            => 'elementTitle.text',
                    'paragraphTitle'          => 'paragraphTitle.text',
                ];
                $usedSearchfields = [];
                if ([] === $searchFields) {
                    $usedSearchfields = array_values($availableSearchfields);
                } else {
                    foreach ($searchFields as $field) {
                        if (array_key_exists($field, $availableSearchfields)) {
                            $usedSearchfields[] = $availableSearchfields[$field];
                        }
                    }
                    // if no searchfields match User does not want to search in fragments
                    if (0 === count($usedSearchfields)) {
                        return $this->searchService->getESEmptyResult()->lock();
                    }
                }

                $baseQuery = $this->searchService->createSearchQuery(
                    $search,
                    $usedSearchfields
                );
                $boolQuery->addMust($baseQuery);
            } else {
                $baseQuery = new MatchAll();
            }

            if (array_key_exists('element', $userFilters)) {
                $userFilters['elementId'] = $userFilters['element'];
            }
            if (array_key_exists('fragments.paragraphParentId', $userFilters)) {
                $userFilters['paragraphParentId'] = $userFilters['fragments.paragraphParentId'];
            }
            if (array_key_exists('fragments_documentParentId', $userFilters)) {
                $userFilters['documentParentId'] = $userFilters['fragments_documentParentId'];
            }

            // fragments on original Statements should never be displayed
            // they can remain if a cluster with fragments is deleted
            $boolMustFilter = [
                $this->searchService->getElasticaExistsInstance(
                    'statement.originalId'
                ),
            ];
            $boolMustNotFilter = [
                // exclude clustered Statements
                $this->searchService->getElasticaExistsInstance(
                    'statement.headStatementId'
                ),
            ];

            if (array_key_exists('modifiedOrArchivedByDepartmentId', $userFilters)) {
                // custom query to find fragments with the departmentId
                $boolQuery->addShould(
                    $this->searchService->getElasticaTermsInstance(
                        'departmentId',
                        $userFilters['modifiedOrArchivedByDepartmentId']
                    ));
                $boolQuery->addShould(
                    $this->searchService->getElasticaTermsInstance(
                        'archivedDepartmentId',
                        $userFilters['modifiedOrArchivedByDepartmentId']
                    ));
                $boolQuery = $this->searchService->setMinimumShouldMatch(
                    $boolQuery,
                    1
                );
            }

            // Filters to skip in foreach
            $unhandledFilters = ['includeVersions', 'element', 'paragraph', 'fragments.paragraphParentId',
                'modifiedOrArchivedByDepartmentId',
                'fragments_documentParentId',
            ];

            // Values that are === NULL instead of "" (empty string) if they are missing
            $nullValues = ['voteAdvice', 'documentParentId'];
            $rawFields = ['tagNames', 'countyNames', 'municipalityNames'];

            foreach ($userFilters as $filterName => $filterKeys) {
                if (in_array($filterName, $unhandledFilters)) {
                    continue;
                }
                if (\is_array($filterKeys) && 1 < count($filterKeys)) {
                    if ('statementId' === $filterName) {
                        // special handling for the statement IDs to avoid errors regarding max clause count
                        // and query length problems for many statement
                        $boolMustFilter[] = new Terms($filterName, $filterKeys);
                    } else {
                        // for each filter with multiple options we need a distinct should
                        // query as filters should only be ORed within one field
                        $shouldQuery = new BoolQuery();
                        $shouldFilter = [];
                        $shouldNotFilter = [];
                        foreach ($filterKeys as $key) {
                            if (null === $key || (in_array($filterName, $nullValues) && '' === $key)) {
                                $shouldNotFilter[] = $this->searchService->getElasticaExistsInstance(
                                    $filterName
                                );
                            } else {
                                if (in_array($filterName, $rawFields)) {
                                    $filterName .= '.raw';
                                }
                                $shouldFilter[] = $this->searchService->getElasticaTermsInstance(
                                    $filterName,
                                    $key
                                );
                            }
                        }
                        // user wants to see not existent query as well as some filter
                        if (0 < count($shouldNotFilter)) {
                            $shouldNotBool = new BoolQuery();
                            array_map($shouldNotBool->addMustNot(...), $shouldNotFilter);
                            $shouldQuery->addShould($shouldNotBool);
                        }
                        array_map($shouldQuery->addShould(...), $shouldFilter);
                        $shouldQuery = $this->searchService->setMinimumShouldMatch(
                            $shouldQuery,
                            1
                        );
                        // add as an ordinary bool Query
                        $boolMustFilter[] = $shouldQuery;
                    }
                } elseif (in_array($filterName, $nullValues) && '' === $filterKeys[0]) {
                    $boolMustNotFilter[] = $this->searchService->getElasticaExistsInstance(
                        $filterName
                    );
                } else {
                    [$boolMustFilter, $boolMustNotFilter] = $this->searchService->addUserFilter(
                        $filterName,
                        $userFilters,
                        $boolMustFilter,
                        $boolMustNotFilter,
                        null,
                        $rawFields,
                        $addAllAggregations
                    );
                }
            }

            if (0 < (is_countable($boolMustFilter) ? count($boolMustFilter) : 0)) {
                array_map($boolQuery->addMust(...), $boolMustFilter);
            }

            // do not include procedures in configuration
            if (0 < (is_countable($boolMustNotFilter) ? count($boolMustNotFilter) : 0)) {
                array_map($boolQuery->addMustNot(...), $boolMustNotFilter);
            }

            // generate Query
            $query = new Query();
            $query->setQuery($boolQuery);

            // Exclude Versions by default
            if (!array_key_exists('includeVersions', $userFilters) || false === $userFilters['includeVersions']) {
                $query->setSource(['exclude' => 'versions']);
            }

            $query = $this->searchService->addEsAggregation($query, 'procedureId');

            if ($addAllAggregations || array_key_exists('planningDocument', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'statement.elementTitle.raw', null, null, 'planningDocument');
            }
            if ($addAllAggregations || array_key_exists('reasonParagraph', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'statement.paragraphTitle.raw', null, null, 'reasonParagraph');
            }
            if ($addAllAggregations || array_key_exists('priorityAreaKeys', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'priorityAreaKeys');
            }
            if ($addAllAggregations || array_key_exists('tagNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'tagNames.raw', null, null, 'tagNames');
            }
            if ($addAllAggregations || array_key_exists('topicNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'topicNames.raw', null, null, 'topicNames');
            }
            if ($addAllAggregations || array_key_exists('name.raw', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'name', null, null, 'name.raw');
            }
            if ($addAllAggregations || array_key_exists('voteAdvice', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'voteAdvice');
                $query = $this->searchService->addEsMissingAggregation($query, 'voteAdvice');
            }
            if ($addAllAggregations || array_key_exists('vote', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'vote');
                $query = $this->searchService->addEsMissingAggregation($query, 'vote');
            }
            if ($addAllAggregations || array_key_exists('fragments_status', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'status', null, null, 'fragments_status');
            }
            if ($addAllAggregations || array_key_exists('fragments_document', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'elementId', null, null, 'fragments_document');
                $query = $this->searchService->addEsMissingAggregation($query, 'elementId');
            }
            if ($addAllAggregations || array_key_exists('fragments_paragraphParentId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'paragraphParentId', null, null, 'fragments_paragraphParentId');
                $query = $this->searchService->addEsMissingAggregation($query, 'paragraphParentId');
            }
            if ($addAllAggregations || array_key_exists('archivedDepartmentId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'archivedDepartmentId');
                $query = $this->searchService->addEsMissingAggregation($query, 'archivedDepartmentId');
            }
            if ($addAllAggregations || array_key_exists('departmentId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'departmentId');
                $query = $this->searchService->addEsMissingAggregation($query, 'departmentId');
            }

            // Sorting
            $sortObject = $this->statementService->addMissingSortKeys($sort, 'displayId.sort', 'desc');
            $sortDirection = $sortObject->getDirection();
            $sortProperty = $sortObject->getPropertyName();

            // use given or default sorting
            $esSort = [$sortProperty => $sortDirection];

            if ('institution' === $sortProperty) {
                $esSort = ['oName.raw' => $sortDirection];
            }
            if ('versionCreated' === $sortProperty) {
                $esSort = ['versions.created' => $sortDirection];
            }

            // add default sort, additionally to primary sort
            if (!array_key_exists('displayId', $esSort)
                || (array_key_exists('submit', $esSort)
                    && 'asc' !== $esSort['submit'])) {
                $esSort['displayId.sort'] = 'desc';
            }

            // sort by score if something has been searched for
            if (is_string($search) && 0 < mb_strlen($search)) {
                $esSort = ['_score' => 'desc'];
            }

            $query->addSort($esSort);
            $query->setSize(0);
            $this->logger->debug('Elasticsearch StatementFragmentList Query: '.DemosPlanTools::varExport($query->getQuery(), true));

            $search = $this->esStatementFragmentType;
            $elasticaAdapter = new ElasticaAdapter($search, $query);
            $paginator = new DemosPlanPaginator($elasticaAdapter);
            $paginator->setLimits($this->getPaginatorLimits());

            // setze einen Defaultwert
            if (0 == $limit) {
                $limit = 25;
            }

            $paginator->setMaxPerPage((int) $limit);
            // try to paginate Result, check for validity
            try {
                $paginator->setCurrentPage($page);
            } catch (NotValidCurrentPageException $e) {
                $paginator->setCurrentPage(1);
            }
            try {
                // When we click on a dropdown filter (just to open it) we come here and get statement ids
                /** @var ResultSet $resultSet */
                $resultSet = $paginator->getCurrentPageResults();
                $result = $resultSet->getResponse()->getData();
                $elasticsearchResultStatement->setHits($result['hits']);
            } catch (ClientException $e) {
                $this->logger->warning('Elasticsearch probably hit a timeout: ', [$e]);
                throw $e;
            }

            $aggregations = $resultSet->getAggregations();

            $voteAdviceLabelMap = $this->getVoteLabelMap();

            $procedureLabelMap = [];
            foreach ($aggregations['procedureId']['buckets'] as $bucket) {
                if (!array_key_exists($bucket['key'], $procedureLabelMap) && 0 < $bucket['doc_count']) {
                    $procedureNames = $this->procedureService->getProcedureNames($bucket['key']);
                    $procedureLabelMap[$bucket['key']] = $procedureNames['name'];
                }
            }

            if (isset($aggregations['planningDocument'])) {
                $aggregation['planningDocument'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['planningDocument']['buckets']
                );
            }
            if (isset($aggregations['reasonParagraph'])) {
                $aggregation['reasonParagraph'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['reasonParagraph']['buckets']
                );
            }
            if (isset($aggregations['priorityAreaKeys'])) {
                $aggregation['priorityAreaKeys'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['priorityAreaKeys']['buckets']
                );
            }
            if (isset($aggregations['tagNames'])) {
                $aggregation['tagNames'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['tagNames']['buckets']
                );
            }
            if (isset($aggregations['fragments_status'])) {
                $aggregation['fragments_status'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['fragments_status']['buckets']
                );
            }
            if (isset($aggregations['departmentId'])) {
                $aggregation['departmentId'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['departmentId']['buckets']
                );
            }
            if (isset($aggregations['procedureId'])) {
                $aggregation['procedureId'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['procedureId']['buckets'],
                    $procedureLabelMap
                );
            }
            if (isset($aggregations['voteAdvice'])) {
                $aggregation['voteAdvice'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['voteAdvice']['buckets'],
                    $voteAdviceLabelMap
                );
            }
            if (isset($aggregations['voteAdvice_missing'])) {
                $aggregation['voteAdvice'][] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsMissing(
                    $aggregations['voteAdvice_missing']
                );
            }
            if (isset($aggregations['vote'])) {
                $aggregation['vote'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['vote']['buckets'],
                    $voteAdviceLabelMap
                );
            }
            if (isset($aggregations['vote_missing'])) {
                $aggregation['vote'][] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsMissing(
                    $aggregations['vote_missing']
                );
            }
            if (isset($aggregations['archivedDepartmentId'])) {
                $aggregation['archivedDepartmentId'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['archivedDepartmentId']['buckets']
                );
            }
            if (isset($aggregations['elementId'])) {
                $aggregation['elementId_missing'][] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsMissing(
                    $aggregations['elementId']
                );
            }
            if (isset($aggregations['fragments_paragraphParentIdParentId_missing'])) {
                $aggregation['fragments_paragraph'][] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsMissing(
                    $aggregations['paragraphId_missing']
                );
            }
            if (isset($aggregations['archivedDepartmentId_missing'])) {
                $aggregation['archivedDepartmentId'][] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsMissing(
                    $aggregations['archivedDepartmentId_missing']
                );
            }
            if (isset($aggregations['fragments_tagNames'])) {
                $aggregation['fragments_tagNames'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['fragments_tagNames']['buckets']
                );
            }
            if (isset($aggregations['priorityAreaKeys'])) {
                $aggregation['fragments.priorityAreaKeys'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['priorityAreaKeys']['buckets']
                );
            }

            // add modified Aggregations to Result
            $elasticsearchResultStatement->setAggregations($aggregation);
            $elasticsearchResultStatement->setPager($paginator);

            $this->profilerStop('ES');
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getStatementAggregation failed. ', [$e]);
            $elasticsearchResultStatement = $this->searchService->getESEmptyResult();
        }

        return $elasticsearchResultStatement->lock();
    }

    /**
     * Create a StatementFragment and
     * copy counties, municipalities, priorityAreas into new created Fragment.
     *
     * @param array $data
     */
    public function createStatementFragment($data): ?StatementFragment
    {
        try {
            if (!array_key_exists('statementId', $data)) {
                return null;
            }

            $permissionEnabled = $this->permissions->hasPermission('feature_statement_assignment');
            $statement = $this->statementService->getStatement($data['statementId']);

            if (null === $statement) {
                $this->getLogger()->error('Create StatementFragment failed: Related Statement not found.', ['id' => $data['statementId']]);
                throw new EntityNotFoundException('Create StatementFragment failed: Statement not found.');
            }

            // T5140: unable to creating Fragment if permission is enabled and Statement is not assigned to current user
            if ($permissionEnabled && false === $this->assignService->isStatementObjectAssignedToCurrentUser($statement)) {
                $this->getLogger()->error('Tried to fragment an unassigned Statement.');
                throw NotAssignedException::mustBeAssignedException();
            }

            $statementFragment = $this->createStatementFragmentIgnoreAssignment($data);
        } catch (Exception $e) {
            $this->logger->error('Could not add StatementFragment', [$e]);

            return null;
        }

        return $statementFragment;
    }

    protected function getFragmentElasticsearchRepository(): FragmentElasticsearchRepository
    {
        return new FragmentElasticsearchRepository(
            $this->conditionFactory,
            $this->esStatementFragmentType,
            $this->managerRegistry,
            $this->globalConfig,
            $this->getLogger(),
            $this->reindexer,
            $this->translator,
            $this->sortMethodFactory,
            $this->elementService,
            $this->paragraphService,
            StatementFragment::class
        );
    }

    /**
     * Updates a StatementFragment.
     * Also checks if the fragment to update are assigned to another user.
     *
     * @param StatementFragment|array $fragment
     *
     * @return bool|StatementFragment|false|null
     *
     * @throws MessageBagException
     */
    public function updateStatementFragment($fragment, bool $ignoreLocked = false, bool $isReviewer = false)
    {
        try {
            $fragmentId = $this->entityHelper->extractId($fragment);
            $changedContent = [];

            if ($this->permissions->hasPermission('feature_statement_fragment_content_changes_save')) {
                $changedContent = $this->entityContentChangeService->calculateChanges($fragment, StatementFragment::class);
            }

            if ($this->areAllStatementFragmentsClaimedByCurrentUser([$fragment], $ignoreLocked)) {
                $updatedFragment = false;
                if (\is_array($fragment)) {
                    $fragment = $this->getParagraphVersionsForFragmentArray($fragment);
                    $updatedFragment = $this->updateStatementFragmentArray($fragmentId, $fragment);
                }
                if ($fragment instanceof StatementFragment) {
                    $updatedFragment = $this->updateStatementFragmentObject($fragment);
                }

                if ($updatedFragment instanceof StatementFragment
                    && $this->permissions->hasPermission('feature_statement_fragment_content_changes_save')) {
                    $this->entityContentChangeService->addEntityContentChangeEntries($updatedFragment, $changedContent, $isReviewer);
                }

                return $updatedFragment;
            }
        } catch (InvalidArgumentException $e) {
            $this->getLogger()->error('Update StatementFragment failed:', [$e]);

            return false;
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws Exception                Thrown in case of a problem during the transaction. To be save to not introduce bugs
     *                                  <strong>DO NOT USE DOCTRINE AFTER THIS POINT</strong> except if the possible problems (see the
     *                                  exception handling at the bottom of this method) are well understood.
     */
    public function updateStatementFragmentsFromStatementFragmentUpdate(StatementFragmentUpdate $statementFragmentUpdate)
    {
        $procedureId = $statementFragmentUpdate->getProcedureId();
        $statementFragmentIds = $statementFragmentUpdate->getStatementFragmentIds();

        // transaction is needed here, because we want both the StatementFragment changes and the
        // ContentChange creations inside a single transaction
        /** @var Connection $connection */
        $connection = $this->getDoctrine()->getConnection();

        try {
            $connection->beginTransaction();
            /** @var StatementFragment[] $statementFragments */
            $statementFragments = $this->statementFragmentRepository->findBy(
                ['id' => $statementFragmentIds, 'procedure' => $procedureId]
            );
            if (!$this->areAllStatementFragmentsClaimedByCurrentUser($statementFragments)) {
                throw new InvalidArgumentException('not all statementFragments are claimed by the current user');
            }
            if (count($statementFragments) !== (is_countable($statementFragmentIds) ? count($statementFragmentIds) : 0)) {
                throw new InvalidArgumentException('Not all given statementFragments IDs could be found for the given procedure ID');
            }
            foreach ($statementFragments as $statementFragment) {
                $claimedByCurrentUser = $this->isFragmentAssignedToCurrentUser($statementFragment);
                if (!$claimedByCurrentUser) {
                    throw new InvalidArgumentException('at least one StatementFragment is not claimed by the current user');
                }

                // update all fields in the StatementFragment except the assignee
                $this->updateStatementFragmentFromStatementFragmentUpdate(
                    $statementFragment,
                    $statementFragmentUpdate
                );

                $updateResult = $this->updateStatementFragment($statementFragment);
                if (!($updateResult instanceof StatementFragment)) {
                    throw new InvalidArgumentException(sprintf('could not update statementFragment wtih ID %s', $statementFragment->getId()));
                }

                // update the assignee of the StatementFragment
                $this->updateClaimingForStatementFragmentFromStatementFragmentUpdate(
                    $updateResult,
                    $statementFragmentUpdate
                );

                $updateResult = $this->updateStatementFragment($updateResult, true);
                if (!($updateResult instanceof StatementFragment)) {
                    throw new InvalidArgumentException(sprintf('could not update statementFragment wtih ID %s', $statementFragment->getId()));
                }
            }
            $connection->commit();
        } catch (Exception $e) {
            $this->getLogger()->log('warning', 'Could not complete transaction; rolling back the relational database; be aware about the possible danger of desynchronizations to Elasticsearch', [$e]);
            $connection->rollBack();
            $this->refreshElasticsearchIndexes();
            // The Elasticsearch and relational database may be desynchronized at this point, because
            // the this->updateStatementFragment($statementFragment); method updates the Elasticsearch
            // before the database transaction has been finished and rolled back.
            // The Elasticsearch update is (partially) done by an event that (currently) can not be disabled
            // easily.
            // The following code tries to overwrite the invalid state in the ES. However to get the correct
            // state we need to throw away the cached statement fragments first. This could lead to problems
            // if any previous StatementFragment instances are used with doctrine afterwards during this HTTP
            // request, even implicitly, eg. when persisting a Statement referencing StatementFragments.
            $this->doctrine->getManager()->clear(StatementFragment::class);
            $statementFragments = $this->statementFragmentRepository->findBy(
                ['id' => $statementFragmentIds, 'procedure' => $procedureId]
            );

            $this->refreshElasticsearchIndexes();
            throw $e;
        }
    }

    /**
     * @throws InvalidDataException
     * @throws Exception
     */
    public function updateStatementFragmentFromStatementFragmentUpdate(StatementFragment $statementFragment, StatementFragmentUpdate $statementFragmentUpdate)
    {
        /** @var string $property */
        foreach ($statementFragmentUpdate->getPropertiesSet() as $property) {
            // keep the switch; additional properties will be added probably
            switch ($property) {
                case 'considerationAddition':
                    $considerationAddition = $statementFragmentUpdate->getConsiderationAddition();
                    $statementFragment->addConsiderationParagraph($considerationAddition);
                    break;
                    // add additionally supported fields here as more case statements
                case 'assigneeId':
                    // DO NOTHING! assignee will be handled by updateClaimingForStatementFragmentFromStatementFragmentUpdate
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('property not supported: %s', $property));
            }
        }
    }

    /**
     * @throws InvalidDataException
     * @throws Exception
     */
    public function updateClaimingForStatementFragmentFromStatementFragmentUpdate(
        StatementFragment $statementFragment,
        StatementFragmentUpdate $statementFragmentUpdate,
    ) {
        if (in_array('assigneeId', $statementFragmentUpdate->getPropertiesSet(), true)) {
            $newAssigneeId = $statementFragmentUpdate->getAssigneeId();
            if (!is_string($newAssigneeId)) {
                throw new InvalidDataException("expected string as assigneeId value, got $newAssigneeId");
            }
            $procedureId = $statementFragmentUpdate->getProcedureId();
            if (!is_string($procedureId)) {
                throw new InvalidDataException("expected string as procedureId value, got $procedureId");
            }
            $newAssignee = $this->userRepository->get($newAssigneeId);
            if (!$newAssignee instanceof User
                || !$this->procedureService->isUserAuthorized($procedureId, $newAssignee)) {
                throw new InvalidDataException("User $newAssigneeId is not authorized for procedure");
            }
            $statementFragment->setAssignee($newAssignee);
        }
    }

    /**
     * @param StatementFragment[]|array[] $statementFragments
     *
     * @throws MessageBagException
     */
    public function areAllStatementFragmentsClaimedByCurrentUser($statementFragments, bool $ignoreLocked = false): bool
    {
        // if the corresponding permission is disabled, the Statement can be updated anyway
        if ($ignoreLocked || (false === $this->permissions->hasPermission('feature_statement_assignment'))) {
            return true;
        }

        // loop through each fragment
        foreach ($statementFragments as $statementFragment) {
            // check if at least one is not assigned to current user
            if (!$this->isFragmentAssignedToCurrentUser($statementFragment)) {
                $fragmentId = $this->entityHelper->extractId($statementFragment);
                $assignedUser = $this->getAssigneeOfFragment($fragmentId);

                // if there is only one fragment, give a detailed error message
                if (1 === count($statementFragments)) {
                    if (null === $assignedUser) {
                        $this->messageBag->add('warning', 'warning.fragment.needLock');
                    } else {
                        $this->messageBag->add(
                            'warning',
                            'warning.fragment.locked.By',
                            ['name' => $assignedUser->getName(), 'organisation' => $assignedUser->getName()]
                        );
                    }
                } else {
                    // if there are several fragments, give a general error message
                    $this->messageBag->add(
                        'warning',
                        'warning.fragment.needLock.group'
                    );
                }

                return false;
            }
        }

        return true;
    }

    /**
     * @param string $fragmentId
     *
     * @return StatementFragmentVersion[]|null
     */
    public function getStatementFragmentVersionsOfFragment($fragmentId)
    {
        try {
            $relatedVersions = $this->statementFragmentVersionRepository->findBy(['statementFragment' => $fragmentId]);
        } catch (Exception $e) {
            $this->logger->error('Could not find related Versions of StatementFragment with id '.$fragmentId.': ', [$e]);

            return null;
        }

        return $relatedVersions;
    }

    /**
     * Check whether Fields of version has been modified and return only those.
     *
     * @param array $version
     * @param array $currentValues
     */
    protected function hasModifiedValues($version, &$currentValues): bool
    {
        foreach ($currentValues as $key => $currentValue) {
            // Values should differ. not !==, as null and "" should be treated as equal
            if ($version[$key] != $currentValues[$key]) {
                $currentValues[$key] = $version[$key];

                return true;
            }
        }

        return false;
    }

    /**
     * Returns all fragments related to the given tag-ID.
     *
     * @param string $tagId
     *
     * @return array|StatementFragment[]|null
     */
    public function getStatementFragmentsTag($tagId)
    {
        try {
            $statementFragment = $this->statementFragmentRepository->getListByTag($tagId);
        } catch (Exception $e) {
            $this->logger->warning('Get List of StatementFragment by Tag failed Message: ', [$e]);

            return null;
        }

        return $statementFragment;
    }
}
