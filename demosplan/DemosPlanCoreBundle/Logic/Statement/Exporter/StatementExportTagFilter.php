<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter;

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\Common\Collections\ArrayCollection;

use function in_array;

class StatementExportTagFilter
{
    private const TAG_IDS_FILTER_KEY = 'tagIds';
    private const TAG_TITLES_FILTER_KEY = 'tagTitles';
    private const TAG_TOPIC_IDS_FILTER_KEY = 'tagTopicIds';
    private const TAG_TOPIC_TITLES_FILTER_KEY = 'tagTopicTitles';

    /**
     * Filters statements and their segments based on tag criteria.
     *
     * This method modifies each statement's segment collection in-memory (not persisted) to include
     * only segments with tags matching the filter criteria. Statements with no matching segments
     * are excluded entirely from the result.
     *
     * The filter accepts:
     * - tagIds: array of tag IDs
     * - tagTitles: array of tag titles
     * - tagTopicIds: array of tag topic IDs
     * - tagTopicTitles: array of tag topic titles
     *
     * Filter logic: OR-based - a segment is included if it has ANY tag matching ANY filter criterion.
     *
     * @param Statement[] $statements
     * @param array       $tagsFilter associative array with filter criteria
     *
     * @return Statement[] statements with filtered segment collections
     */
    public function filterStatementsByTags(array $statements, array $tagsFilter): array
    {
        $tagIds = $tagsFilter[self::TAG_IDS_FILTER_KEY] ?? [];
        $tagTitles = $tagsFilter[self::TAG_TITLES_FILTER_KEY] ?? [];
        $tagTopicIds = $tagsFilter[self::TAG_TOPIC_IDS_FILTER_KEY] ?? [];
        $tagTopicTitles = $tagsFilter[self::TAG_TOPIC_TITLES_FILTER_KEY] ?? [];

        $noSupportedFilter = empty($tagIds) && empty($tagTitles) && empty($tagTopicIds) && empty($tagTopicTitles);

        // If no filter criteria provided, return all statements
        if ($noSupportedFilter) {
            return $statements;
        }

        // the goal is to exclude all Segments from the payload that do not match the filter criteria
        // if all Segments from a parentStatement get excluded - the whole statement gets excluded as well.
        return $this->applyTagFilter($statements, $tagIds, $tagTitles, $tagTopicIds, $tagTopicTitles);
    }

    private function applyTagFilter(array $statements, array $tagIds, array $tagTitles, array $tagTopicIds, array $tagTopicTitles): array
    {
        $statementsCollection = new ArrayCollection($statements);

        return $statementsCollection->filter(
            function (StatementInterface $statement) use ($tagIds, $tagTitles, $tagTopicIds, $tagTopicTitles): bool {
                // filter out non-matching segments from statement
                $filteredSegmentsList = $statement->getSegmentsOfStatement()->filter(
                    function (SegmentInterface $segment) use ($tagIds, $tagTitles, $tagTopicIds, $tagTopicTitles): bool {
                        /** @var TagInterface $tag */
                        foreach ($segment->getTags() as $tag) {
                            // Check if tag matches any of the filter criteria to include the segment
                            $matchByTagId = !empty($tagIds)
                                && in_array($tag->getId(), $tagIds, true);
                            $matchByTagTitle = !empty($tagTitles)
                                && in_array($tag->getTitle(), $tagTitles, true);

                            $tagTopic = $tag->getTopic();
                            $matchByTagTopicId = null !== $tagTopic
                                && !empty($tagTopicIds)
                                && in_array($tagTopic->getId(), $tagTopicIds, true);
                            $matchByTagTopicTitle = null !== $tagTopic
                                && !empty($tagTopicTitles)
                                && in_array($tagTopic->getTitle(), $tagTopicTitles, true);

                            if ($matchByTagId || $matchByTagTitle || $matchByTagTopicId || $matchByTagTopicTitle) {
                                return true;
                            }
                        }

                        // exclude this segment from the payload
                        return false;
                    }
                ); // SEGMENT FILTER END
                // meet any segments of this statement the filter criteria?
                if ($filteredSegmentsList->isEmpty()) {
                    // if not exclude the whole statement
                    return false;
                }
                // set the filtered segmentList at the statement to replace the old one.
                // this is not meant to be persisted and just build for export purposes!
                $statement->setSegmentsOfStatement($filteredSegmentsList);

                return true;
            } // STATEMENT FILTER END
        )->toArray();
    }
}
