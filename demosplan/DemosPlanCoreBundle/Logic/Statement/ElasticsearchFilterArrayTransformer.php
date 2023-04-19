<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use Psr\Log\LoggerInterface;

class ElasticsearchFilterArrayTransformer
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Transform Elasticsearch Buckets info existing Filterstructure.
     *
     * @param array  $bucket
     * @param array  $labelMap array with labels for bucket keys
     * @param string $labelKey Key used to get the label from each entry in the given bucket. Defaults to 'key'.
     * @param string $valueKey Key used to get the value from each entry in the given bucket. Defaults to 'key'.
     * @param string $countKey Key used to get the count from each entry in the given bucket. Defaults to 'doc_count'.
     *
     * @return array
     */
    public function generateFilterArrayFromEsBucket($bucket, $labelMap = [], $labelKey = 'key', $valueKey = 'key', $countKey = 'doc_count')
    {
        $filter = [];
        if ((!\is_array($bucket) || 0 === \count($bucket)) && 0 === \count($labelMap)) {
            return $filter;
        }

        foreach ($bucket as $entry) {
            $filterEntry = [
                'count' => $entry[$countKey],
                'label' => \array_key_exists($entry[$labelKey], $labelMap) ? $labelMap[$entry[$labelKey]] : $entry[$labelKey],
                'value' => $entry[$valueKey],
            ];
            // Setze einen Stadardwert, wenn kein Label angegeben ist
            if ('' === $filterEntry['label']) {
                $filterEntry['label'] = 'Keine Zuordnung';
                $filterEntry['value'] = ElasticSearchService::EMPTY_FIELD;
            }
            $filter[] = $filterEntry;
        }

        // sortiere nach Label
        // Sortierung nach 7, 7.1, 7.1.1 funktioniert noch nicht
        \usort(
            $filter,
            function ($a, $b) {
                // Missing has to be on top
                if ('Keine Zuordnung' === $a['label']) {
                    return -1;
                }
                if ('Keine Zuordnung' === $b['label']) {
                    return 1;
                }

                // Fallback if there are some more cases where arrays or objects are passed
                if (is_array($a['label']) || is_array($b['label'])) {
                    $this->logger->warning('Could not compare arrays',
                        ['a' => $a, 'b' => $b]
                    );
                    // what should be returned in that case?
                    return 0;
                }

                return \strnatcasecmp($a['label'], $b['label']);
            }
        );

        return $filter;
    }

    /**
     * @param array  $bucket
     * @param string $key
     */
    public function generateFilterArrayFromEsFragmentsMissing($bucket, $key): array
    {
        $aggregationCount = count($bucket[$key]['statements']['buckets']);
        // aggregations only are top 10, add other doc count to sum
        $aggregationCount = array_key_exists('sum_other_doc_count', $bucket[$key]['statements']) ?
            $aggregationCount + $bucket[$key]['statements']['sum_other_doc_count'] : $aggregationCount;

        return [
            'count' => $aggregationCount,
            'label' => 'Keine Zuordnung',
            'value' => ElasticSearchService::KEINE_ZUORDNUNG,
        ];
    }

    /**
     * @param array $bucket
     */
    public function generateFilterArrayFromEsMissing($bucket): array
    {
        return [
            'count' => $bucket['doc_count'],
            'label' => 'Keine Zuordnung',
            'value' => ElasticSearchService::KEINE_ZUORDNUNG,
        ];
    }
}
