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
        string $entityClass
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
            if (0 < count($boolMustNotFilter)) {
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
            $esSortFields = [];

            $esQuery->setSort($esQuery->getAvailableSorts());
            foreach ($esQuery->getSort() as $esQuerySort) {
                foreach ($esQuerySort->getFields() as $sortField) {
                    $esSortFields[$sortField->getName()] = $sortField->getDirection();
                }
            }
            $query->addSort($esSortFields);

            $this->logger->debug('Elasticsearch Fragment Query: '.DemosPlanTools::varExport($query->getQuery(), true));

            $search = $this->getIndex();
            $fragments = $search->search($query);
            $result = $fragments->getResponse()->getData();
            $aggregations = $fragments->getAggregations();

            // transform Buckets info existing Filterstructure
            if (0 < count($aggregations)) {
                $this->generateLabelMaps($aggregations);
                $this->prepareEsQueryDisplayFilters($esQuery, $aggregations, $this->labelMaps);
            }
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getFragments failed: ', [$e]);
        }

        return $result;
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
