<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryFragment;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\SortField;
use demosplan\DemosPlanCoreBundle\Traits\DI\ElasticsearchQueryTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Utilities\Reindexer;
use Elastica\Index;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FragmentElasticsearchRepository extends CoreRepository
{
    use ElasticsearchQueryTrait;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    /** @var ElementsService */
    protected $elementsService;

    /** @var ParagraphService */
    protected $paragraphService;

    /** @var DepartmentRepository */
    protected $departmentRepository;

    /** @var TranslatorInterface */
    protected $translator;

    public function __construct(
        DqlConditionFactory $conditionFactory,
        Index $fragmentSearchType,
        ManagerRegistry $registry,
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        Reindexer $reindexer,
        TranslatorInterface $translator,
        SortMethodFactory $sortMethodFactory,
        ElementsService $elementsService,
        ParagraphService $paragraphService,
        string $entityClass,
    ) {
        $this->index = $fragmentSearchType;
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->translator = $translator;
        $this->elementsService = $elementsService;
        $this->paragraphService = $paragraphService;

        parent::__construct($conditionFactory, $registry, $reindexer, $sortMethodFactory, $entityClass);
    }

    /**
     * Search for Fragments.
     *
     * @param QueryFragment $esQuery
     *
     * @return array
     */
    public function searchFragments($esQuery)
    {
        return $this->getResult($esQuery);
    }

    /**
     * Do actual Elasticsearch Query.
     *
     * @param QueryFragment $esQuery
     *
     * @return array
     */
    public function getResult($esQuery)
    {
        $result = [];
        $boolMustFilter = [];
        $boolMustNotFilter = [];
        try {
            $boolQuery = new BoolQuery();

            // The parent should not be in cluster or an original statement
            $boolMustNotFilter[] = new Exists('statement.headStatementId');
            $boolMustFilter[] = new Exists('statement.originalId');

            $boolQuery = $this->buildFilterMust($boolQuery, $esQuery, $boolMustFilter, $boolMustNotFilter);

            foreach ($esQuery->getFiltersMustNot() as $filter) {
                $boolMustNotFilter[] = $this->getTermsQuery($filter);
            }
            if ([] !== $boolMustNotFilter) {
                array_map($boolQuery->addMustNot(...), $boolMustNotFilter);
            }

            $query = new Query();
            $query->setQuery($boolQuery);

            // Exclude Versions by default
            if (!$esQuery->shouldIncludeVersions()) {
                $query->setSource(['exclude' => 'versions']);
            }

            // generate Aggregation
            $query = $this->buildAggregation($esQuery, $query);

            $query->setSize(3000);

            // Sorting
            // default
            $esQuery->setSort($esQuery->getAvailableSorts());
            $query->addSort($this->buildSortFields($esQuery, $this->findQueryDepartmentId($esQuery)));

            $this->logger->debug('Elasticsearch Fragment Query: '.DemosPlanTools::varExport($query->getQuery(), true));

            $search = $this->getIndex();
            $fragments = $search->search($query);
            $result = $fragments->getResponse()->getData();
            $aggregations = $fragments->getAggregations();

            // transform Buckets info existing Filterstructure
            if ([] !== $aggregations) {
                $this->generateLabelMaps($aggregations);
                $this->prepareEsQueryDisplayFilters($esQuery, $aggregations, $this->labelMaps);
            }
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getFragments failed: ', [$e]);
        }

        return $result;
    }

    /**
     * A fragment's versions can belong to different departments; the sort on a nested version field
     * needs to be scoped to the department already being queried, to avoid sorting by another
     * department's edit timestamp.
     */
    private function findQueryDepartmentId(QueryFragment $esQuery): ?string
    {
        foreach ($esQuery->getFiltersMust() as $filter) {
            if (in_array($filter->getField(), ['departmentId', 'versions.modifiedByDepartmentId'], true)) {
                return $filter->getValue();
            }
        }

        return null;
    }

    /**
     * @return array<string, string|array{order: string, nested: array}>
     */
    private function buildSortFields(QueryFragment $esQuery, ?string $departmentId): array
    {
        $esSortFields = [];
        foreach ($esQuery->getSort() as $esQuerySort) {
            foreach ($esQuerySort->getFields() as $sortField) {
                $esSortFields[$sortField->getName()] = $this->buildSortFieldValue($sortField, $departmentId);
            }
        }

        return $esSortFields;
    }

    /**
     * @return string|array{order: string, nested: array}
     */
    private function buildSortFieldValue(SortField $sortField, ?string $departmentId): array|string
    {
        // Sorting on a field inside the nested 'versions' mapping requires an explicit nested context
        if (!str_starts_with($sortField->getName(), 'versions.')) {
            return $sortField->getDirection();
        }

        $nested = ['path' => 'versions'];
        if (null !== $departmentId) {
            $nested['filter'] = ['term' => ['versions.modifiedByDepartmentId' => $departmentId]];
        }

        return [
            'order'  => $sortField->getDirection(),
            'nested' => $nested,
        ];
    }

    public function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->globalConfig;
    }

    /**
     * This method is generating labelMaps like:
     * $this->labelMaps[filterName] = ['someId' => 'something user understand'].
     *
     * Oh man. This is so damn ugly. But thats what its like
     * with all these special cases...
     *
     * @param array $aggregations
     */
    protected function generateLabelMaps($aggregations)
    {
        $this->labelMaps = [];
        foreach ($aggregations as $name => $result) {
            if ('voteAdvice' === $name) {
                $this->labelMaps[$name] = $this->getVoteAdviceLabelMap($result['buckets']);
            } elseif ('elementId' === $name) {
                $this->labelMaps[$name] = $this->getElementLabelMap($result['buckets']);
            } elseif ('paragraphId' === $name) {
                $this->labelMaps[$name] = $this->getParagraphLabelMap($result['buckets']);
            } else {
                $this->labelMaps[$name] = [];
            }
        }
    }

    /**
     * @param array $buckets
     *
     * @return array
     */
    protected function getVoteAdviceLabelMap($buckets)
    {
        $labelMap = [];
        foreach ($buckets as $bucket) {
            $labelMap[$bucket['key']] = $this->translator->trans('fragment.vote.'.$bucket['key']);
        }

        return $labelMap;
    }

    /**
     * @param array $buckets
     *
     * @return array
     */
    protected function getElementLabelMap($buckets)
    {
        $labelMap = [];
        foreach ($buckets as $bucket) {
            try {
                $labelMap[$bucket['key']] = $this->elementsService->getElementObject($bucket['key'])->getTitle();
            } catch (Exception $e) {
                $this->logger->error('Could not get ElementsName to generate labelMap: ', [$e]);
            }
        }

        return $labelMap;
    }

    /**
     * @param array $buckets
     *
     * @return array
     */
    protected function getParagraphLabelMap($buckets)
    {
        $labelMap = [];
        foreach ($buckets as $bucket) {
            $paragraphVersion = $this->paragraphService->getParaDocumentVersion($bucket['key']);
            $labelMap[$bucket['key']] = $paragraphVersion['title'];
        }

        return $labelMap;
    }
}
