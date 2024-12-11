<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResult;
use Elastica\Aggregation\GlobalAggregation;
use Elastica\Exception\ClientException;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Exception;
use Pagerfanta\Elastica\ElasticaAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;

class ElasticsearchResultCreator extends CoreService
{
    // Values that are === NULL instead of "" (empty string) if they are missing
    private const NULL_VALUES = [
        'oName.raw', 'priorityAreaKeys', 'countyNames',
        'municipalityNames', 'topicNames', 'tagNames',
        'elementId', 'paragraphParentId', 'voteStk', 'votePla',
        'assignee.id', 'fragments.vote', 'fragments.voteAdvice',
        'fragments.lastClaimedUserId', 'fragments.element', 'fragments.paragraph',
        'fragments.countyNames', 'fragments.municipalityNames',
        'documentParentId', 'fragments_documentParentId', 'fragments.priorityAreaKeys',
    ];
    private const RAW_FIELDS = [
        'dName',
        'uName',
        'documentTitle',
        'paragraphTitle',
        'topicNames',
        'meta.submitName',
        'meta.caseWorkerName',
        'name',
        'countyNames',
        'municipalityNames',
        'tagNames',
    ];
    private const AVAILABLE_SEARCH_FIELDS = [
        'text'                    => 'text.text',
        'oName'                   => 'oName^0.2',
        'dName'                   => 'dName^0.2',
        'uName'                   => 'uName^0.2',
        'elementTitle'            => 'elementTitle.text',
        'documentTitle'           => 'documentTitle.text',
        'paragraphTitle'          => 'paragraphTitle.text',
        'recommendation'          => 'recommendation.text',
        'municipalityNames'       => 'municipalityNames',
        'internId'                => 'internId',
        'externId'                => 'externId',
        'priorityAreaKeys'        => 'priorityAreaKeys',
        'countyNames'             => 'countyNames.raw',
        'tagNames'                => 'tagNames.text',
        'topicNames'              => 'topicNames.text',
        'meta_submitLastName'     => 'meta.submitLastName^0.2',
        'meta_caseWorkerLastName' => 'meta.caseWorkerLastName^0.2',
        'cluster_externId'        => 'cluster.externId',
        'clusterName'             => 'name.text',
        'cluster_uName'           => 'cluster.uName^0.1',
        'fragments.documentTitle.text',
        'fragments.paragraphTitle.text',
        'votes.firstName'         => 'votes.firstName',
        'votes.lastName'          => 'votes.lastName',
        'votes.name'              => 'votes.name',
        'filename'                => 'files',
        // after refactoring in T20362:
        'authorName'              => 'uName^0.2',
        'consideration'           => 'recommendation.text',
        'department'              => 'dName^0.2',
        'orgaCity'                => 'meta.orgaCity',
        'organisationName'        => 'oName^0.2',
        'orgaPostalCode'          => 'meta.orgaPostalCode',
        'planDocument'            => ['documentTitle.text', 'elementTitle.text', 'paragraphTitle.text'],
        'statementId'             => 'externId',
        'statementText'           => 'text.text',
        'topics'                  => 'topicNames.text',
    ];

    public function __construct(
        private readonly StatementService $statementService,
        private readonly StatementFragmentService $statementFragmentService,
        private readonly ElasticSearchService $elasticSearchService,
        private readonly ElementsService $elementsService,
        private readonly ProcedureRepository $procedureRepository,
        private readonly TranslatorInterface $translator,
        private readonly UserService $userService,
        private readonly ParagraphService $paragraphService,
        private readonly SingleDocumentRepository $singleDocumentRepository,
        private readonly DepartmentRepository $departmentRepository
    ) {
    }

    /**
     * Gets Aggegations from Elasticsearch to use as facetted filters.
     *
     * @param array                   $userFilters
     * @param string                  $procedureId
     * @param string                  $search
     * @param array|null              $sort
     * @param int                     $limit
     * @param int                     $page                         First page is 1
     * @param array                   $searchFields
     * @param bool                    $aggregationsOnly
     * @param int                     $aggregationsMinDocumentCount
     * @param bool                    $addAllAggregations
     * @param list<GlobalAggregation> $customAggregations
     */
    public function getElasticsearchResult(
        $userFilters,
        $procedureId,
        $search = '',
        $sort = null,
        $limit = 0,
        $page = 1,
        $searchFields = [],
        $aggregationsOnly = false,
        $aggregationsMinDocumentCount = 1,
        $addAllAggregations = true,
        array $customAggregations = []
    ): ElasticsearchResult {
        $elasticsearchResultStatement = new ElasticsearchResult();
        try {
            $searchQuery = $this->getSearchQuery(
                $search,
                $searchFields,
                $aggregationsMinDocumentCount
            );
            [$boolMustFilter, $boolMustNotFilter] = $this->getBasicFilters($procedureId, $userFilters);
            $userFilters = $this->getRenamedUserFilters($userFilters);
            $fragmentFilters = $this->getFragmentFilters($userFilters);
            $userFragmentFilters = $this->statementService->mapRequestFiltersToESFragmentFilters($userFilters);
            $fragmentEsResult = (new ElasticsearchResult())->lock();

            if ((null !== $search && '' !== $search) || 0 < count($userFragmentFilters)) {
                $userFragmentFilters['procedureId'] = $procedureId;
                $fragmentEsResult = $this->statementFragmentService->getElasticsearchStatementFragmentResult(
                    $userFragmentFilters,
                    $search,
                    null,
                    10000,
                    1,
                    $searchFields,
                    $addAllAggregations
                );
                $statementMustIds = [];
                $filterStatementsById = is_countable($fragmentEsResult->getHits()['hits'])
                    && 0 < count($fragmentEsResult->getHits()['hits']);
                if ($filterStatementsById) {
                    // use should filter as other filters may be applied as well
                    foreach ($fragmentEsResult->getHits()['hits'] as $fragmentHit) {
                        $statementMustIds[] = $fragmentHit['_source']['statementId'];
                    }
                } else {
                    $statementMustIds[] = 'not_existent';
                }
                $statementMustIds = \array_unique($statementMustIds);
                $shouldQuery = new BoolQuery();
                foreach ($statementMustIds as $statementMustId) {
                    $shouldQuery->addShould(
                        $this->elasticSearchService->getElasticaTermsInstance(
                            'id',
                            $statementMustId
                        ));
                }
                // add search query as a should request as we already found statements
                // that have the searchstring at their fragment
                if ($searchQuery instanceof AbstractQuery) {
                    $shouldQuery->addShould($searchQuery);
                }
                $shouldQuery = $this->elasticSearchService->setMinimumShouldMatch(
                    $shouldQuery,
                    1
                );
                $boolMustFilter[] = $shouldQuery;
            } else {
                if ($searchQuery instanceof Query) {
                    $boolMustFilter[] = $searchQuery;
                }
            }

            foreach ($userFilters as $filterName => $filterValues) {
                if (\in_array($filterName, $fragmentFilters)) {
                    continue;
                }

                $filterValues = \is_array($filterValues) ? \array_unique($filterValues) : $filterValues;

                if (\is_array($filterValues) && 1 < count($filterValues)) {
                    // for each filter with multiple options we need a distinct should
                    // query as filters should only be ORed within one field
                    $shouldQuery = new BoolQuery();
                    $shouldFilter = [];
                    $shouldNotFilter = [];
                    foreach ($filterValues as $filterValue) {
                        if ($filterValue === $this->elasticSearchService::KEINE_ZUORDNUNG
                            || null === $filterValue
                            || (\in_array($filterName, self::NULL_VALUES) && '' === $filterValue)
                        ) {
                            $shouldNotFilter[] = $this->elasticSearchService->getElasticaExistsInstance(
                                $filterName
                            );
                        } else {
                            $filterName = $this->isRawFilteredTerm($filterName) ? $filterName.'.raw' : $filterName;
                            $value = $filterValue === $this->elasticSearchService::EMPTY_FIELD ? '' : $filterValue;
                            $shouldFilter[] = $this->elasticSearchService->getElasticaTermsInstance(
                                $filterName,
                                $value
                            );
                        }
                    }
                    array_map($shouldQuery->addShould(...), $shouldFilter);
                    // user wants to see not existent query as well as some filter
                    if (0 < count($shouldNotFilter)) {
                        $shouldNotBool = new BoolQuery();
                        array_map($shouldNotBool->addMustNot(...), $boolMustNotFilter);
                        $shouldQuery->addShould($shouldNotBool);
                    }
                    $shouldQuery = $this->elasticSearchService->setMinimumShouldMatch(
                        $shouldQuery,
                        1
                    );
                    // add as an ordinary bool Query
                    $boolMustFilter[] = $shouldQuery;
                } else {
                    [$boolMustFilter, $boolMustNotFilter] = $this->elasticSearchService->addUserFilter(
                        $filterName,
                        $userFilters,
                        $boolMustFilter,
                        $boolMustNotFilter,
                        null,
                        self::RAW_FIELDS,
                        $addAllAggregations
                    );
                }
            }
            $boolQuery = new BoolQuery();
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

            if ($aggregationsOnly) {
                $query->setSize(0);
            }

            // GET QUERY (END)

            /********************************** QUERY AGGREGATIONS (INI) *********************************************/

            /****************************************** CUSTOM AGGREGATIONS ******************************************/

            foreach ($customAggregations as $customAggregation) {
                $query->addAggregation($customAggregation);
            }

            /****************************************** EINREICHUNG **************************************************/

            // Öffentlichkeit/Institution - publicStatement - publicStatement
            if ($addAllAggregations || \array_key_exists('publicStatement', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'publicStatement');
            }
            // Institution/Name - institution - oName.raw
            if ($addAllAggregations || \array_key_exists('institution', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'oName.raw',
                    null,
                    null,
                    'oName.raw'
                );
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'oName.raw');
            }
            // Abteilung - department - dName.raw
            if ($addAllAggregations || \array_key_exists('department', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'dName.raw',
                    null,
                    null,
                    'dName.raw'
                );
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'dName.raw');
            }
            // Verfahrensschritt - phase - phase
            if ($addAllAggregations || \array_key_exists('phase', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'phase');
            }
            // Verschobene Stellungnahmen in dieses Verfahren - movedFromProcedureId - movedFromProcedureId
            if ($addAllAggregations || \array_key_exists('movedFromProcedureId', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'movedFromProcedureId');
            }
            // Verschobene Stellungnahmen aus diesem Verfahren - movedToProcedureId - movedToProcedureId
            if ($addAllAggregations || \array_key_exists('movedToProcedureId', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'movedToProcedureId');
            }
            // VeröffentlichungI - publicAllowed - publicAllowed
            if ($addAllAggregations || \array_key_exists('publicAllowed', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'publicAllowed');
            }
            // VeröffentlichungII - publicCheck - publicCheck
            if ($addAllAggregations || \array_key_exists('publicCheck', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'publicCheck');
            }
            // VeröffentlichungIII - publicVerify - publicVerify
            if ($addAllAggregations || \array_key_exists('publicVerified', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'publicVerified');
            }
            // Project specifics
            if ($addAllAggregations || \array_key_exists('meta.userState', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'meta.userState');
            }
            if ($addAllAggregations || \array_key_exists('meta.userGroup', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'meta.userGroup');
            }
            if ($addAllAggregations || \array_key_exists('meta.userOrganisation', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'meta.userOrganisation');
            }
            if ($addAllAggregations || \array_key_exists('meta.userPosition', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'meta.userPosition');
            }

            /***************************************** STELLUNGNAHME ************************************************/
            // Sachbearbeiter - assignee_id - assignee.id
            if ($addAllAggregations || \array_key_exists('assignee_id', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'assignee.id',
                    null,
                    null,
                    'assignee_id'
                );
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'assignee.id');
            }
            // Bearbeitungsstatus - status - status
            if ($addAllAggregations
                || \array_key_exists(StatementService::AGGREGATION_STATEMENT_STATUS, $userFilters)
            ) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    StatementService::FIELD_STATEMENT_STATUS,
                    null,
                    null,
                    StatementService::AGGREGATION_STATEMENT_STATUS
                );
            }
            // Votum - votePla - votePla
            if ($addAllAggregations || \array_key_exists('votePla', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'votePla',
                    null,
                    null,
                    'votePla'
                ); // vote
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'votePla');
            }
            // Kreis - countyNames - countyNames.raw
            if ($addAllAggregations || \array_key_exists('countyNames', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'countyNames.raw',
                    null,
                    null,
                    'countyNames'
                );
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'countyNames.raw');
            }
            // Gemeinde - municipalityNames - municipalityNames.raw
            if ($addAllAggregations || \array_key_exists('municipalityNames', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'municipalityNames.raw',
                    null,
                    null,
                    'municipalityNames'
                );
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'municipalityNames.raw');
            }
            // Schlagwort - tagNames - tagNames.raw
            if ($addAllAggregations || \array_key_exists('tagNames', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'tagNames.raw',
                    null,
                    null,
                    'tagNames'
                );
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'tagNames.raw');
            }
            // Potenzialflächen - priorityAreaKeys - priorityAreaKeys
            if ($addAllAggregations || \array_key_exists('priorityAreaKeys', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'priorityAreaKeys');
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'priorityAreaKeys');
            }
            // Dokument - planningDocument - elementId
            if ($addAllAggregations || \array_key_exists('elementId', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'elementId',
                    '_term',
                    'asc',
                    'elementId'
                );
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'elementId');
            }
            // Kapitel - reasonParagraph - paragraphParentId
            if ($addAllAggregations || \array_key_exists('reasonParagraph', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'paragraphParentId');
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'paragraphParentId');
            }
            // Datei - documentParentId - documentParentId
            if ($addAllAggregations || \array_key_exists('documentParentId', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'documentParentId');
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'documentParentId');
            }
            // Thema - topicNames - topicNames.raw
            if ($addAllAggregations || \array_key_exists('topicNames', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'topicNames.raw',
                    null,
                    null,
                    'topicNames'
                );
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'topicNames.raw');
            }
            // ID - externId - externId
            if ($addAllAggregations || \array_key_exists('externId', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'externId');
            }
            // Gruppenname - name - name.raw
            if ($addAllAggregations || \array_key_exists('name', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'name.raw');
            }
            // Art der Stellungnahme - type - type
            if ($addAllAggregations || \array_key_exists('type', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation($query, 'type');
            }
            // Priorität - priority - priority
            if ($addAllAggregations
                || \array_key_exists(StatementService::AGGREGATION_STATEMENT_PRIORITY, $userFilters)
            ) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    StatementService::FIELD_STATEMENT_PRIORITY,
                    null,
                    null,
                    StatementService::AGGREGATION_STATEMENT_PRIORITY
                );
            }
            // Empfehlung - voteStk - voteStk
            if ($addAllAggregations || \array_key_exists('voteStk', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'voteStk',
                    null,
                    null,
                    'voteStk'
                ); // advice for vote
                $query = $this->elasticSearchService->addEsMissingAggregation($query, 'voteStk');
            }

            /*************************************** DATENSATZ / FRAGMENTS *******************************************/

            // Sachbearbeiter - fragments_lastClaimed_id - fragments.lastClaimedUserId
            if ($addAllAggregations || \array_key_exists('fragments_lastClaimed_id', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.lastClaimedUserId',
                    null,
                    null,
                    'fragments_lastClaimed_id'
                );
                $query = $this->elasticSearchService->addEsFragmentsMissingAggregation(
                    'fragments.lastClaimedUserId',
                    $query
                );
            }
            // Bearbeitungsstatus - fragments_status - fragments.status
            if ($addAllAggregations || \array_key_exists('fragments_status', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.status',
                    null,
                    null,
                    'fragments_status'
                );
            }
            // Votum - fragments_vote - fragments.vote
            if ($addAllAggregations || \array_key_exists('fragments_vote', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.vote',
                    null,
                    null,
                    'fragments_vote'
                );
                $query = $this->elasticSearchService->addEsFragmentsMissingAggregation('fragments.vote', $query);
            }
            // Kreis - fragments_countyNames - fragments.countyNames
            if ($addAllAggregations || \array_key_exists('fragments_countyNames', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.countyNames.raw',
                    null,
                    null,
                    'fragments_countyNames'
                );
                $query = $this->elasticSearchService->addEsFragmentsMissingAggregation(
                    'fragments.countyNames.raw',
                    $query
                );
            }
            // Gemeinde - fragments_municipalityNames - fragments.municipalityNames
            if ($addAllAggregations || \array_key_exists('fragments_municipalityNames', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.municipalityNames.raw',
                    null,
                    null,
                    'fragments_municipalityNames'
                );
                $query = $this->elasticSearchService->addEsFragmentsMissingAggregation(
                    'fragments.municipalityNames.raw',
                    $query
                );
            }
            // Schlagwort - fragments_tagNames - fragments.tags.name
            if ($addAllAggregations || \array_key_exists('fragments.tagNames', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.tags.name.raw',
                    null,
                    null,
                    'fragments_tagNames'
                );
            }
            // Potenzialflächen - fragments.priorityAreaKeys - fragments.priorityAreaKeys
            if ($addAllAggregations || \array_key_exists('fragments.priorityAreaKeys', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.priorityAreaKeys',
                    null,
                    null,
                    'fragments.priorityAreaKeys'
                );
            }
            // Dokument - fragments_element - fragments.elementId
            if ($addAllAggregations || \array_key_exists('fragments_element', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.elementId',
                    null,
                    null,
                    'fragments_element'
                );
            }
            // Kapitel - fragments_paragraphParentId - fragments.paragraphParentId
            if ($addAllAggregations || \array_key_exists('fragments_paragraphParentId', $userFilters)) {
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.paragraphParentId',
                    null,
                    null,
                    'fragments_paragraphParentId'
                );
                $query = $this->elasticSearchService->addEsFragmentsMissingAggregation(
                    'fragments.paragraphParentId',
                    $query
                );
            }
            // Datei - fragments_documentParentId - fragments.documentParentId
            if ($addAllAggregations || \array_key_exists('fragments_documentParentId', $userFilters)) {
                $query = $this->elasticSearchService->addEsFragmentsMissingAggregation(
                    'fragments.documentParentId',
                    $query
                );
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.documentParentId',
                    null,
                    null,
                    'fragments.documentParentId'
                );
            }
            // Fachbehörde - fragments_reviewerName - fragments.departmentId
            if ($addAllAggregations || \array_key_exists('fragments_reviewerName', $userFilters)) {
                $query = $this->elasticSearchService->addEsFragmentsMissingAggregation(
                    'fragments.departmentId',
                    $query
                );
                $query = $this->elasticSearchService->addEsAggregation(
                    $query,
                    'fragments.departmentId',
                    null,
                    null,
                    'fragments.departmentId'
                );
            }

            // Sorting
            $esSort = $this->mapSorting($sort, $search);
            $query->addSort($esSort);

            $this->logger->debug(
                'Elasticsearch StatementList Query: '.
                DemosPlanTools::varExport($query->getQuery(), true)
            );

            $search = $this->statementService->getEsStatementType();
            // Don't let yourself be fooled, this basically does the search request, don't
            // go looking for an explicit call to search() anywhere in here. <3
            $elasticaAdapter = new ElasticaAdapter($search, $query);
            $paginator = new DemosPlanPaginator($elasticaAdapter);
            $paginator->setLimits($this->statementService->getPaginatorLimits());

            // setze einen Defaultwert
            if (0 === $limit) {
                $defaultLimits = $this->statementService->getPaginatorLimits();
                $limit = $defaultLimits[0];
            }

            $paginator->setMaxPerPage((int)$limit);
            // try to paginate Result, check for validity
            try {
                $paginator->setCurrentPage($page);
            } catch (NotValidCurrentPageException $e) {
                $this->logger->info('Received invalid Page for pagination', [$e]);
                $paginator->setCurrentPage(1);
            }

            try {
                /** @var array|Traversable $resultSet */
                $resultSet = $paginator->getCurrentPageResults();
                $result = $resultSet->getResponse()->getData();
                $elasticsearchResultStatement->setHits($result['hits']);
            } catch (ClientException $e) {
                $this->logger->error('Elasticsearch probably hit a timeout: ', [$e]);
                throw $e;
            }

            $esResultAggregations = $resultSet->getAggregations();
            $totalHits = $result['hits']['total'];
            if (is_array($totalHits) && array_key_exists('value', $totalHits) && 0 === $totalHits['value']) {
                $esResultAggregations = $this->addFilterToAggregationsWhenCausedResultIsEmpty(
                    $esResultAggregations,
                    $userFilters
                );
            }

            $processedAggregation = [];
            $elementsAdminList = $this->elementsService->getElementsAdminList($procedureId);
            $elementMap = \collect($elementsAdminList)->mapWithKeys(
                static fn (Elements $element): array => [$element->getId() => $element->getTitle()]
            )->all();

            /********************************** QUERY AGGREGATIONS (INI) *********************************************/

            /****************************************** CUSTOM AGGREGATIONS ******************************************/

            foreach ($customAggregations as $customAggregation) {
                $name = $customAggregation->getName();
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    $name,
                    $name,
                    $esResultAggregations,
                    $processedAggregation
                );
            }

            /****************************************** EINREICHUNG **************************************************/
            // Öffentlichkeit/Institution - publicStatement - publicStatement
            if ($addAllAggregations || \array_key_exists('publicStatement', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'publicStatement',
                    'publicStatement',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Institution/Name - institution - oName.raw
            if ($addAllAggregations || \array_key_exists('institution', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'oName.raw',
                    'institution',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'oName.raw',
                    'institution',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Abteilung - department - dName.raw
            if ($addAllAggregations || \array_key_exists('department', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'dName.raw',
                    'department',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Verfahrensschritt - phase - phase
            if ($addAllAggregations || \array_key_exists('phase', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'phase',
                    'phase',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Verschobene Stellungnahmen in dieses Verfahren - movedFromProcedureId - movedFromProcedureId
            if ($addAllAggregations || \array_key_exists('movedFromProcedureId', $userFilters)) {
                $movedStatementCount = 0;
                $processedAggregation['movedFromProcedureId'] = [];
                if (isset($esResultAggregations['movedFromProcedureId'])) {
                    foreach ($esResultAggregations['movedFromProcedureId']['buckets'] as $agg) {
                        $procedure = $this->procedureRepository->get($agg['key']);
                        $label = $procedure instanceof Procedure ? $procedure->getName() : '';
                        $processedAggregation['movedFromProcedureId'][] = [
                            'count' => $agg['doc_count'],
                            'label' => $label,
                            'value' => $agg['key'],
                        ];
                        $movedStatementCount += $agg['doc_count'];
                    }
                }
                array_unshift($processedAggregation['movedFromProcedureId'], [
                    'label' => $this->translator->trans('all'),
                    'value' => $this->elasticSearchService::EXISTING_FIELD_FILTER,
                    'count' => $movedStatementCount,
                ]);
            }
            // Verschobene Stellungnahmen aus diesem Verfahren - movedToProcedureId - movedToProcedureId
            if ($addAllAggregations || \array_key_exists('movedToProcedureId', $userFilters)) {
                $movedStatementCount = 0;
                $processedAggregation['movedToProcedureId'] = [];
                if (isset($esResultAggregations['movedToProcedureId'])) {
                    foreach ($esResultAggregations['movedToProcedureId']['buckets'] as $agg) {
                        $procedure = $this->procedureRepository->get($agg['key']);
                        $label = $procedure instanceof Procedure ? $procedure->getName() : '';
                        $processedAggregation['movedToProcedureId'][] = [
                            'count' => $agg['doc_count'],
                            'label' => $label,
                            'value' => $agg['key'],
                        ];
                        $movedStatementCount += $agg['doc_count'];
                    }
                }
                array_unshift($processedAggregation['movedToProcedureId'], [
                    'label' => $this->translator->trans('all'),
                    'value' => $this->elasticSearchService::EXISTING_FIELD_FILTER,
                    'count' => $movedStatementCount,
                ]);
            }

            if ($addAllAggregations || \array_key_exists('publicCheck', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray('publicCheck', 'publicCheck', $esResultAggregations, $processedAggregation);
            }

            /***************************************** STELLUNGNAHME ************************************************/

            // Sachbearbeiter - assignee_id
            if ($addAllAggregations || \array_key_exists('assignee_id', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'assignee.id',
                    'assignee_id',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if (isset($esResultAggregations['assignee_id'])) {
                foreach ($esResultAggregations['assignee_id']['buckets'] as $agg) {
                    $user = $this->userService->getSingleUser($agg['key']);
                    $userName = $user instanceof User ?
                        $user->getFirstname().' '.$user->getLastname().' -- '.$user->getOrgaName() :
                        '';
                    $processedAggregation['assignee_id'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $userName,
                        'value' => $agg['key'],
                    ];
                }
            }
            // Bearbeitungsstatus - status
            if ($addAllAggregations
                || \array_key_exists(StatementService::AGGREGATION_STATEMENT_STATUS, $userFilters)
            ) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    StatementService::AGGREGATION_STATEMENT_STATUS,
                    StatementService::AGGREGATION_STATEMENT_STATUS,
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Votum - votePla
            if ($addAllAggregations || \array_key_exists('votePla', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'votePla',
                    'votePla',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if (isset($esResultAggregations['votePla'])) {
                foreach ($esResultAggregations['votePla']['buckets'] as $agg) {
                    $processedAggregation['votePla'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $this->translator->trans('fragment.vote.'.$agg['key']),
                        'value' => $agg['key'],
                    ];
                }
            }
            // Kreis - countyNames
            if ($addAllAggregations || \array_key_exists('countyNames', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'countyNames.raw',
                    'countyNames',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'countyNames',
                    'countyNames',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Gemeinde - municipalityNames
            if ($addAllAggregations || \array_key_exists('municipalityNames', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'municipalityNames.raw',
                    'municipalityNames',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'municipalityNames',
                    'municipalityNames',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Schlagwort - tagNames - tagNams.raw
            if ($addAllAggregations || \array_key_exists('tagNames', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'tagNames.raw',
                    'tagNames',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'tagNames',
                    'tagNames',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Potenzialflächen - priorityAreaKeys
            if ($addAllAggregations || \array_key_exists('priorityAreaKeys', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'priorityAreaKeys',
                    'priorityAreaKeys',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'priorityAreaKeys',
                    'priorityAreaKeys',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Dokument - planningDocument - elementId
            if ($addAllAggregations || \array_key_exists('elementId', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'elementId',
                    'planningDocument',
                    $esResultAggregations,
                    $processedAggregation,
                    $elementMap
                );
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'elementId',
                    'planningDocument',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Kapitel - reasonParagraph - paragraphParentId
            if ($addAllAggregations || \array_key_exists('reasonParagraph', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'paragraphParentId',
                    'reasonParagraph',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'paragraphParentId',
                    'reasonParagraph',
                    $esResultAggregations,
                    $processedAggregation,
                    $this->getParagraphMap($esResultAggregations['paragraphParentId']['buckets'])
                );
            }
            // Datei - documentParentId
            if ($addAllAggregations || \array_key_exists('documentParentId', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'documentParentId',
                    'documentParentId',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if (isset($esResultAggregations['documentParentId'])) {
                foreach ($esResultAggregations['documentParentId']['buckets'] as $agg) {
                    $document = $this->singleDocumentRepository->findOneBy(['id' => $agg['key']]);
                    $label = $document instanceof SingleDocument ? $document->getTitle() : '';
                    $processedAggregation['documentParentId'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $label,
                        'value' => $agg['key'],
                    ];
                }
            }
            // Thema - topicNames - topicNames.raw
            if ($addAllAggregations || \array_key_exists('topicNames', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'topicNames.raw',
                    'topicNames',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'topicNames',
                    'topicNames',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // ID - externId - externId
            if ($addAllAggregations || \array_key_exists('externId', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'externId',
                    'externId',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Gruppenname - name - name.raw
            if ($addAllAggregations || \array_key_exists('name', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'name.raw',
                    'name',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Art der Stellungnahme - type
            if (isset($esResultAggregations['type'])) {
                foreach ($esResultAggregations['type']['buckets'] as $agg) {
                    $processedAggregation['type'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $this->translator->trans('statement.type.'.$agg['key']),
                        'value' => $agg['key'],
                    ];
                }
            }
            // Priorität - priority
            if ($addAllAggregations
                || \array_key_exists(StatementService::AGGREGATION_STATEMENT_PRIORITY, $userFilters)
            ) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    StatementService::AGGREGATION_STATEMENT_PRIORITY,
                    StatementService::AGGREGATION_STATEMENT_PRIORITY,
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Empfehlung - voteStk
            if ($addAllAggregations || \array_key_exists('voteStk', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addMissingAggregationResultToArray(
                    'voteStk',
                    'voteStk',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if (isset($esResultAggregations['voteStk'])) {
                foreach ($esResultAggregations['voteStk']['buckets'] as $agg) {
                    $processedAggregation['voteStk'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $this->translator->trans('fragment.vote.'.$agg['key']),
                        'value' => $agg['key'],
                    ];
                }
            }
            // project specifics
            if ($addAllAggregations || \array_key_exists('meta.userState', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'meta.userState',
                    'userState',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if ($addAllAggregations || \array_key_exists('meta.userGroup', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'meta.userGroup',
                    'userGroup',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if ($addAllAggregations || \array_key_exists('meta.userOrganisation', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'meta.userOrganisation',
                    'userOrganisation',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if ($addAllAggregations || \array_key_exists('meta.userPosition', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'meta.userPosition',
                    'userPosition',
                    $esResultAggregations,
                    $processedAggregation
                );
            }

            /*************************************** DATENSATZ / FRAGMENTS *******************************************/

            // We use $fragmentsEsResult for the filters and $aggregations for the Statement List

            // Sachbearbeiter - fragments_lastClaimed_id - fragments.lastClaimedUserId
            if ($addAllAggregations || \array_key_exists('fragments_lastClaimed_id', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addFragmentsMissingAggregationResultToArray(
                    'fragments.lastClaimedUserId',
                    'fragments_lastClaimed_id',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            $fragmentAggregations = $fragmentEsResult->getAggregations();
            if (isset($fragmentAggregations['lastClaimed_id'])) {
                $processedAggregation['fragments_lastClaimed_id'] = \array_merge(
                    $processedAggregation['fragments_lastClaimed_id'],
                    $this->elasticSearchService->generateFilterArrayFromUserAssignEsBucket(
                        $fragmentAggregations['lastClaimed_id'],
                        'value',
                        'value',
                        'count'
                    )
                );
            } elseif (isset($esResultAggregations['fragments_lastClaimed_id'])) {
                $processedAggregation['fragments_lastClaimed_id'] = \array_merge(
                    $processedAggregation['fragments_lastClaimed_id'],
                    $this->elasticSearchService->generateFilterArrayFromUserAssignEsBucket(
                        $esResultAggregations['fragments_lastClaimed_id']['buckets']
                    )
                );
            }
            // Bearbeitungsstatus - fragments_status - fragments.status
            if (isset($fragmentAggregations['status'])) {
                $processedAggregation = $this->elasticSearchService->addFragmentEsResultToArray(
                    'fragments_status',
                    'fragments_status',
                    $fragmentAggregations,
                    $processedAggregation
                );
            } elseif (isset($esResultAggregations['fragments_status'])) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'fragments_status',
                    'fragments_status',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Votum - fragments_vote - fragments.vote
            if (isset($fragmentAggregations['vote'])) {
                $processedAggregation = $this->elasticSearchService->addFragmentEsResultToArray(
                    'vote',
                    'fragments_vote',
                    $fragmentAggregations,
                    $processedAggregation,
                    $this->statementFragmentService->getVoteLabelMap()
                );
            } elseif (isset($esResultAggregations['fragments_vote'])) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'fragments_vote',
                    'fragments_vote',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Kreis - fragments_countyNames - fragments.countyNames
            if ($addAllAggregations || \array_key_exists('fragments_countyNames', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addFragmentsMissingAggregationResultToArray(
                    'fragments.countyNames',
                    'fragments_countyNames',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if (isset($fragmentAggregations['countyNames'])) {
                $processedAggregation['fragments_countyNames'] = \array_merge(
                    $processedAggregation['fragments_countyNames'],
                    $this->elasticSearchService->generateFilterArrayFromUserAssignEsBucket(
                        $fragmentAggregations['countyNames'],
                        'value',
                        'value',
                        'count'
                    )
                );
            } elseif (isset($esResultAggregations['fragments_countyNames'])) {
                $processedAggregation['fragments_countyNames'] = \array_merge(
                    \array_key_exists(
                        'fragments_countyNames',
                        $processedAggregation
                    ) ? $processedAggregation['fragments_countyNames'] : [],
                    $this->elasticSearchService->generateFilterArrayFromUserAssignEsBucket(
                        $esResultAggregations['fragments_countyNames']['buckets']
                    )
                );
            }
            // Gemeinde - fragments_municipalityNames - fragments.municipalityNames
            if ($addAllAggregations || \array_key_exists('fragments_municipalityNames', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addFragmentsMissingAggregationResultToArray(
                    'fragments.municipalityNames',
                    'fragments_municipalityNames',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if (isset($fragmentAggregations['municipalityNames'])) {
                $processedAggregation['fragments_municipalityNames'] = \array_merge(
                    $processedAggregation['fragments_municipalityNames'],
                    $this->elasticSearchService->generateFilterArrayFromUserAssignEsBucket(
                        $fragmentAggregations['municipalityNames'],
                        'value',
                        'value',
                        'count'
                    )
                );
            } elseif (isset($esResultAggregations['fragments_municipalityNames'])) {
                $processedAggregation['fragments_municipalityNames'] = \array_merge(
                    \array_key_exists(
                        'fragments_municipalityNames',
                        $processedAggregation
                    ) ? $processedAggregation['fragments_municipalityNames'] : [],
                    $this->elasticSearchService->generateFilterArrayFromUserAssignEsBucket(
                        $esResultAggregations['fragments_municipalityNames']['buckets']
                    )
                );
            }
            // Schlagwort - fragments_tagNames - fragments.tags.name
            if (isset($fragmentAggregations['tagNames'])) {
                $processedAggregation = $this->elasticSearchService->addFragmentEsResultToArray(
                    'tagNames',
                    'fragments_tagNames',
                    $fragmentAggregations,
                    $processedAggregation
                );
            }
            // Potenzialflächen - fragments.priorityAreaKeys - fragments.priorityAreaKeys
            if ($addAllAggregations || \array_key_exists('fragments.priorityAreaKeys', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addFragmentsMissingAggregationResultToArray(
                    'fragments.priorityAreaKeys',
                    'fragments.priorityAreaKeys',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'fragments.priorityAreaKeys',
                    'fragments.priorityAreaKeys',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Dokument - fragments_element - fragments.elementId
            if ($addAllAggregations || \array_key_exists('fragments_element', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addFragmentsMissingAggregationResultToArray(
                    'fragments.elementId',
                    'fragments_element',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            if (isset($esResultAggregations['fragments_element'])) {
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'fragments_element',
                    'fragments_element',
                    $esResultAggregations,
                    $processedAggregation,
                    $elementMap
                );
            }
            // Kapitel - fragments_paragraphParentId - fragments.paragraphParentId
            if ($addAllAggregations || \array_key_exists('fragments_paragraphParentId', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addFragmentsMissingAggregationResultToArray(
                    'fragments.paragraphParentId',
                    'fragments_paragraphParentId',
                    $esResultAggregations,
                    $processedAggregation
                );
                $processedAggregation = $this->elasticSearchService->addAggregationResultToArray(
                    'fragments_paragraphParentId',
                    'fragments_paragraphParentId',
                    $esResultAggregations,
                    $processedAggregation,
                    $this->getParagraphMap($esResultAggregations['fragments_paragraphParentId']['buckets'])
                );
            }
            if (isset($fragmentAggregations['paragraphParentId'])) {
                $processedAggregation = $this->elasticSearchService->addFragmentEsResultToArray(
                    'fragments_paragraphParentId',
                    'fragments_paragraphParentId',
                    $fragmentAggregations,
                    $processedAggregation,
                    $this->getParagraphMap($fragmentAggregations['fragments_paragraphParentId'], 'value')
                );
            }
            if ($addAllAggregations || \array_key_exists('fragments_documentParentId', $userFilters)) {
                $processedAggregation = $this->elasticSearchService->addFragmentsMissingAggregationResultToArray(
                    'fragments.documentParentId',
                    'fragments_documentParentId',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            // Datei - fragments_documentParentId - fragments.documentParentId
            if (isset($esResultAggregations['fragments.documentParentId'])) {
                foreach ($esResultAggregations['fragments.documentParentId']['buckets'] as $agg) {
                    $document = $this->singleDocumentRepository->findOneBy(['id' => $agg['key']]);
                    $label = $document instanceof SingleDocument ? $document->getTitle() : '';
                    $processedAggregation['fragments_documentParentId'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $label,
                        'value' => $agg['key'],
                    ];
                }
            }
            // Fachbehörde - fragments_reviewerName - fragments.departmentId
            if (isset($fragmentAggregations['departmentId'])) {
                $processedAggregation = $this->elasticSearchService->addFragmentsMissingAggregationResultToArray(
                    'fragments.departmentId',
                    'fragments_reviewerName',
                    $esResultAggregations,
                    $processedAggregation
                );
            }
            $useEsResult2 = isset($fragmentAggregations['departmentId']);
            $useAggregationResult2 = isset($esResultAggregations['fragments_reviewerName']);
            if (true === $useEsResult2 || true === $useAggregationResult2) {
                $countKey2 = true === $useEsResult2 ? 'count' : 'doc_count';
                $valueKey2 = true === $useEsResult2 ? 'value' : 'key';
                $listToUse2 = true === $useEsResult2 ?
                    $fragmentAggregations['departmentId'] : $esResultAggregations['fragments_reviewerName']['buckets'];
                if ($useEsResult2) {
                    foreach ($listToUse2 as $agg) {
                        $department = $this->departmentRepository->get($agg[$valueKey2]);
                        if (null !== $department) {
                            $processedAggregation['fragments_reviewerName'][] =
                                $this->buildReviewerAggregationArray($department, $agg, $countKey2, $valueKey2);
                        } else {
                            $this->logger->warning('$department is null for id `'.$agg[$valueKey2].'`');
                        }
                    }
                }
            }

            /************************************* ADD AGGREGATIONS TO RESULT (END) **********************************/

            // add modified Aggregations to Result
            $elasticsearchResultStatement->setAggregations($processedAggregation);
            $elasticsearchResultStatement->setPager($paginator);
            $elasticsearchResultStatement->setSearchFields($searchFields);

            $this->profilerStop('ES');
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getStatementAggregation failed. ', [$e]);

            $elasticsearchResultStatement = $this->elasticSearchService->getESEmptyResult(
                'warning.search.query.invalid'
            );
        }

        return $elasticsearchResultStatement->lock();
    }

    /**
     * Returns the query based on search field.
     *
     * @param string $search
     */
    private function getSearchQuery($search, array $searchFields, int $aggregationsMinDocumentCount): array|AbstractQuery|null
    {
        $searchQuery = null;

        // store variable in class property to avoid passing it into methods
        $this->elasticSearchService->setAggregationsMinDocumentCount($aggregationsMinDocumentCount);

        // GET QUERY (INI)
        // userFilters may come in in strange formats
        if (\is_array($searchFields) && 1 === count($searchFields) && '' === $searchFields[0]) {
            $searchFields = [];
        }
        $this->profilerStart('ES');
        //
        // if a Searchterm is set use it
        if (\is_string($search) && 0 < \strlen($search)) {
            $usedSearchfields = [];
            if ([] === $searchFields) {
                $usedSearchfields = \array_values(self::AVAILABLE_SEARCH_FIELDS);
            } else {
                foreach ($searchFields as $field) {
                    if (\array_key_exists($field, self::AVAILABLE_SEARCH_FIELDS)) {
                        $usedSearchfields[] = self::AVAILABLE_SEARCH_FIELDS[$field];
                    }
                }
            }
            // do not create search query if only fragment fields are chosen
            if (0 < count($usedSearchfields)) {
                $searchQuery = $this->elasticSearchService->createSearchQuery(
                    $search,
                    $usedSearchfields
                );
            }
        }

        return $searchQuery;
    }

    /**
     * Basic filters to be applied to every query.
     *
     * @throws Exception
     */
    private function getBasicFilters(string $procedureId, array $userFilters): array
    {
        // Base Filters to apply always
        $boolMustFilter = [
            $this->elasticSearchService->getElasticaTermsInstance('pId', [$procedureId]),
            $this->elasticSearchService->getElasticaTermsInstance('deleted', [false]),
        ];

        $boolMustNotFilter = [
            // exclude clustered Statements
            $this->elasticSearchService->getElasticaExistsInstance('headStatementId'),
        ];

        // Ist es die Abwägungstabelle oder die Originalansicht?
        if (\array_key_exists('original', $userFilters) && 'IS NULL' === $userFilters['original']) {
            // Originalstellungnahmen haben null im Feld originalId
            $boolMustNotFilter[] = $this->elasticSearchService->getElasticaExistsInstance(
                'originalId'
            );
            // exclude Cluster
            $boolMustNotFilter[] = $this->elasticSearchService->getElasticaTermsInstance(
                'isClusterStatement',
                [true]
            );
        } else {
            $boolMustFilter[] = $this->elasticSearchService->getElasticaExistsInstance(
                'originalId'
            );
        }

        return [$boolMustFilter, $boolMustNotFilter];
    }

    /**
     * Mapping from filter names frontend / ES.
     */
    private function getRenamedUserFilters(array $userFilters): array
    {
        // map filternames from request to elasticsearch mapping names
        if (\array_key_exists('planningDocument', $userFilters)) {
            $userFilters['elementId'] = $userFilters['planningDocument'];
        }
        if (\array_key_exists('reasonParagraph', $userFilters)) {
            $userFilters['paragraphParentId'] = $userFilters['reasonParagraph'];
        }
        if (\array_key_exists('department', $userFilters)) {
            $userFilters['dName.raw'] = $userFilters['department'];
        }
        if (\array_key_exists('institution', $userFilters)) {
            $userFilters['oName.raw'] = $userFilters['institution'];
        }
        if (\array_key_exists('assignee_id', $userFilters)) {
            $userFilters['assignee.id'] = $userFilters['assignee_id'];
        }
        if (\array_key_exists('userState', $userFilters)) {
            $userFilters['meta.userState'] = $userFilters['userState'];
            unset($userFilters['userState']);
        }
        if (\array_key_exists('userGroup', $userFilters)) {
            $userFilters['meta.userGroup'] = $userFilters['userGroup'];
            unset($userFilters['userGroup']);
        }
        if (\array_key_exists('userOrganisation', $userFilters)) {
            $userFilters['meta.userOrganisation'] = $userFilters['userOrganisation'];
            unset($userFilters['userOrganisation']);
        }
        if (\array_key_exists('userPosition', $userFilters)) {
            $userFilters['meta.userPosition'] = $userFilters['userPosition'];
            unset($userFilters['userPosition']);
        }

        return $userFilters;
    }

    /**
     * Array with the filters to be applied for fragments.
     */
    private function getFragmentFilters(array $userFilters): array
    {
        $fragmentFilters = [
            'original',
            'fragments_element',
            'fragments_lastClaimed_id',
            'fragments_voteAdvice',
            'fragments_vote',
            'fragments_status',
            'fragments_element',
            'fragments_paragraphParentId',
            'fragments_reviewerName',
            'fragments_documentParentId',
            'fragments_municipalityNames',
            'fragments_countyNames',
            'fragments_tagNames',
            'fragments.priorityAreaKeys',
        ];

        // map filternames from request to elasticsearch mapping names
        if (\array_key_exists('planningDocument', $userFilters)) {
            $fragmentFilters[] = 'planningDocument';
        }
        if (\array_key_exists('reasonParagraph', $userFilters)) {
            $fragmentFilters[] = 'reasonParagraph';
        }
        if (\array_key_exists('department', $userFilters)) {
            $fragmentFilters[] = 'department';
        }
        if (\array_key_exists('institution', $userFilters)) {
            $fragmentFilters[] = 'institution';
        }
        if (\array_key_exists('assignee_id', $userFilters)) {
            $fragmentFilters[] = 'assignee_id';
        }

        return $fragmentFilters;
    }

    /**
     * Returns true if the aggregation/filter key must be
     * suffixed with ".raw" in order to function correctly.
     *
     * @param string $key
     */
    private function isRawFilteredTerm($key): bool
    {
        return \in_array($key, self::RAW_FIELDS, true);
    }

    /**
     * Map given sort to es-sort.
     * Also set default sort values.
     *
     * @param array       $sort
     * @param string|null $search
     *
     * @return array - mapped es-sorting
     */
    private function mapSorting($sort, $search = null): array
    {
        // sort by score if something has been searched for
        if (\is_string($search) && '*' !== $search && 0 < mb_strlen($search)) {
            return ['_score' => 'desc'];
        }

        $sortObject = $this->statementService->addMissingSortKeys(
            $sort,
            'submitDate',
            'asc'
        );
        $sortProperty = $sortObject->getPropertyName();
        $sortDirection = $sortObject->getDirection();

        $esSort = [];
        if ('submitDate' === $sortProperty) {
            $esSort = ['submit' => $sortDirection];
        }
        if (StatementService::FIELD_STATEMENT_PRIORITY === $sortProperty) {
            $esSort = [StatementService::FIELD_STATEMENT_PRIORITY => $sortDirection];
        }
        if ('forPoliticians' === $sortProperty) {
            $esSort = [
                'prioritySort'      => 'asc',
                'elementTitle.sort' => 'asc',
                'paragraphOrder'    => 'asc',
            ];
        }
        if ('elementsView' === $sortProperty) {
            $esSort = [
                'elementOrder'   => 'asc',
                'paragraphOrder' => 'asc',
            ];
        }

        // workaround until we can use recent Versions of Elasticsearch & Elastica
        // https://github.com/ruflin/Elastica/issues/717
        // use -1000 instead of -1 as written in ticket referenced above
        // as -1 leads to errors when testing in kopf plugin and it only needs
        // to be a big number
        if ('planningDocument' === $sortProperty) {
            $esSort = [
                'elementTitle.sort' => [
                    'order'   => $sortDirection,
                    'missing' => \PHP_INT_MAX - 1000,
                ],
                'paragraphOrder'    => [
                    'order'   => $sortDirection,
                    'missing' => \PHP_INT_MAX - 1000,
                ],
            ];
        }
        if ('institution' === $sortProperty) {
            // When sorting for institution sort also submitter name
            $esSort = [
                'isClusterStatement' => 'asc',
                'publicStatement'    => 'desc',
                'oName.sort'         => $sortDirection,
                'dName.sort'         => $sortDirection,
                'uName.sort'         => $sortDirection,
                'cluster.oName.sort' => $sortDirection,
                'cluster.dName.sort' => $sortDirection,
                'cluster.uName.sort' => $sortDirection,
            ];
        }

        // add default sort, additionally to primary sort
        if (!\array_key_exists('submit', $esSort) || 'asc' !== strtolower((string) $esSort['submit'])) {
            $esSort['submit'] = 'desc';
        }

        return $esSort;
    }

    private function addFilterToAggregationsWhenCausedResultIsEmpty(array $aggregations, array $userfilters): array
    {
        foreach ($userfilters as $label => $value) {
            if (\array_key_exists($label, $aggregations)
                && \is_array($aggregations[$label])
                && \array_key_exists('buckets', $aggregations[$label])
                && empty($aggregations[$label]['buckets'])
            ) {
                // A filter was set by the user that caused an empty search result - therefore the filter ist not
                // set within the aggregations by default - add those filters manually to let the FE know we used a filter
                $aggregations[$label]['buckets'] = [['key' => $value[0], 'doc_count' => 0]];
            }
        }

        return $aggregations;
    }

    /**
     * Create Label => Value map of paragraphs included in aggregation.
     *
     * @param array  $bucket
     * @param string $idKey
     *
     * @throws Exception
     */
    private function getParagraphMap($bucket, $idKey = 'key'): array
    {
        if (!\is_array($bucket) || 0 === count($bucket)) {
            return [];
        }
        $ids = [];

        foreach ($bucket as $entry) {
            $ids[] = $entry[$idKey];
        }
        $paragraphMap = [];
        $paragraphList = $this->paragraphService->getParaDocumentListByIds($ids);
        if (null === $paragraphList) {
            return $paragraphMap;
        }
        /** @var Paragraph $paragraph */
        foreach ($paragraphList as $paragraph) {
            $paragraphMap[$paragraph->getId()] = $paragraph->getTitle();
        }

        return $paragraphMap;
    }

    /**
     * @param Department $department
     * @param array      $agg
     * @param string     $countKey
     * @param string     $valueKey
     */
    protected function buildReviewerAggregationArray($department, $agg, $countKey, $valueKey): array
    {
        $orgaName = $department->getOrgaName();
        $label = $orgaName.' -- '.$department->getName();

        return [
            'count' => $agg[$countKey],
            'label' => $label,
            'value' => $agg[$valueKey],
        ];
    }
}
