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
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;

class StatementExportTagFilter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }
    private const TAG_IDS_FILTER_KEY = 'tagIds';
    private const TAG_TITLES_FILTER_KEY = 'tagTitles';
    private const TAG_TOPIC_IDS_FILTER_KEY = 'tagTopicIds';
    private const TAG_TOPIC_TITLES_FILTER_KEY = 'tagTopicTitles';

    private array $tagsFilter = [];
    private array $tagNamesFound = [];
    private array $topicNamesFound = [];
    private array $filteredTagsWithTitle = [];

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
        // Store filter for later use in helper methods
        $this->tagsFilter = $tagsFilter;

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

    /**
     * Translates the {@see filterStatementsByTags()} criteria into query conditions on the
     * Statement resource, so the tag filter can be pushed into the DB/Elasticsearch query
     * instead of loading every statement of the procedure and discarding non-matching ones
     * in PHP.
     *
     * The conditions target the already-filterable path `segments.tags.*`. Multiple criteria
     * are OR-combined, mirroring the OR logic of {@see applyTagFilter()}: a statement matches
     * if any of its segments carries a tag (or tag topic) matching any criterion.
     *
     * Returns an empty array when no supported criteria are present, leaving the query
     * unchanged (full export).
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function buildStatementTagConditions(array $tagsFilter, StatementResourceType $statementResourceType): array
    {
        $tagIds = $tagsFilter[self::TAG_IDS_FILTER_KEY] ?? [];
        $tagTitles = $tagsFilter[self::TAG_TITLES_FILTER_KEY] ?? [];
        $tagTopicIds = $tagsFilter[self::TAG_TOPIC_IDS_FILTER_KEY] ?? [];
        $tagTopicTitles = $tagsFilter[self::TAG_TOPIC_TITLES_FILTER_KEY] ?? [];

        $noSupportedFilter = [] === $tagIds && [] === $tagTitles && [] === $tagTopicIds && [] === $tagTopicTitles;
        if ($noSupportedFilter) {
            return [];
        }

        // Resolve the IDs of the matching statements with a lean scalar query that selects ONLY the
        // parent statement ID, then narrow the statement load with a plain `id IN (...)` condition.
        //
        // Expressing the criteria directly as a to-many condition on the Statement resource
        // (segments.tags.*) makes the main statement query fetch-join through segments AND tags, so
        // the buffered result explodes to statements x segments x tags rows, each repeating the wide
        // Statement columns. On large procedures that exhausts memory during loading (the export dies
        // before it even starts). A scalar id query cannot explode that way - one short UUID per row -
        // and the `id IN (...)` condition loads the statements as leanly as the unfiltered export does.
        $matchingStatementIds = $this->findStatementIdsMatchingTags($tagIds, $tagTitles, $tagTopicIds, $tagTopicTitles);

        if ([] === $matchingStatementIds) {
            // Criteria were given but nothing matched: force an empty result instead of
            // silently falling back to an unfiltered full export.
            return [$this->conditionFactory->false()];
        }

        return [$this->conditionFactory->propertyHasAnyOfValues($matchingStatementIds, $statementResourceType->id)];
    }

    /**
     * Runs a lean scalar query returning the IDs of all statements owning at least one segment that
     * carries a tag (or tag topic) matching any of the given criteria. Selecting only the parent
     * statement ID keeps memory bounded even when the segment/tag join multiplies rows.
     *
     * @param list<string> $tagIds
     * @param list<string> $tagTitles
     * @param list<string> $tagTopicIds
     * @param list<string> $tagTopicTitles
     *
     * @return list<string>
     */
    private function findStatementIdsMatchingTags(
        array $tagIds,
        array $tagTitles,
        array $tagTopicIds,
        array $tagTopicTitles,
    ): array {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT IDENTITY(seg.parentStatementOfSegment) AS statementId')
            ->from(Segment::class, 'seg')
            ->join('seg.tags', 't')
            ->leftJoin('t.topic', 'topic');

        $orConditions = $queryBuilder->expr()->orX();
        if ([] !== $tagIds) {
            $orConditions->add('t.id IN (:tagIds)');
            $queryBuilder->setParameter('tagIds', $tagIds);
        }
        if ([] !== $tagTitles) {
            $orConditions->add('t.title IN (:tagTitles)');
            $queryBuilder->setParameter('tagTitles', $tagTitles);
        }
        if ([] !== $tagTopicIds) {
            $orConditions->add('topic.id IN (:tagTopicIds)');
            $queryBuilder->setParameter('tagTopicIds', $tagTopicIds);
        }
        if ([] !== $tagTopicTitles) {
            $orConditions->add('topic.title IN (:tagTopicTitles)');
            $queryBuilder->setParameter('tagTopicTitles', $tagTopicTitles);
        }
        $queryBuilder->where($orConditions);

        $rows = $queryBuilder->getQuery()->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['statementId'], $rows);
    }

    public function isTagIdFilterActive(): bool
    {
        return !empty($this->tagsFilter[self::TAG_IDS_FILTER_KEY] ?? []);
    }

    public function isTagTitleFilterActive(): bool
    {
        return !empty($this->tagsFilter[self::TAG_TITLES_FILTER_KEY] ?? []);
    }

    public function isTagTopicIdFilterActive(): bool
    {
        return !empty($this->tagsFilter[self::TAG_TOPIC_IDS_FILTER_KEY] ?? []);
    }

    public function isTagTopicTitleFilterActive(): bool
    {
        return !empty($this->tagsFilter[self::TAG_TOPIC_TITLES_FILTER_KEY] ?? []);
    }

    /**
     * Returns a human-readable description of tag names that were matched during filtering.
     * This includes both tags filtered by ID and by title.
     *
     * @return string Formatted description of tag names
     */
    public function getTagFiltersHumanReadable(): string
    {
        if ([] === $this->tagNamesFound) {
            return $this->translator->trans('export.filter.tags.none');
        }

        return $this->translator->trans('export.filter.tags.names', ['names' => implode(', ', $this->tagNamesFound)]);
    }

    /**
     * Returns a human-readable description of topic names that were matched during filtering.
     * This includes both topics filtered by ID and by title.
     *
     * @return string Formatted description of topic names
     */
    public function getTopicFiltersHumanReadable(): string
    {
        if ([] === $this->topicNamesFound) {
            return $this->translator->trans('export.filter.topics.none');
        }

        return $this->translator->trans('export.filter.topics.names', ['names' => implode(', ', $this->topicNamesFound)]);
    }

    public function getFilteredTagsWithTitles(): array
    {
        return $this->filteredTagsWithTitle;
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
                            $tagTopic = $tag->getTopic();

                            $matchByTag = $this->evaluateTag($tag, $tagIds, $tagTitles);
                            $matchByTopic = $this->evaluateTopic($tagTopic, $tagTopicIds, $tagTopicTitles);

                            if ($matchByTag || $matchByTopic) {
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

    private function evaluateTag(TagInterface $tag, array $tagIds, array $tagTitles): bool
    {
        $matchByTagId = $this->checkExistence($tag->getId(), $tagIds);
        $matchByTagTitle = $this->checkExistence($tag->getTitle(), $tagTitles);

        $matchByTag = $matchByTagId || $matchByTagTitle;
        if ($matchByTag) {
            $this->tagNamesFound[$tag->getId()] = $tag->getTitle();
            $this->filteredTagsWithTitle[$tag->getId()] = [$tag->getTitle(), $tag->getTopic()->getTitle()];
        }

        return $matchByTag;
    }

    private function evaluateTopic(?TagTopicInterface $tagTopic, array $tagTopicIds, array $tagTopicTitles): bool
    {
        $matchByTagTopicId = $this->checkExistence($tagTopic?->getId(), $tagTopicIds);
        $matchByTagTopicTitle = $this->checkExistence($tagTopic?->getTitle(), $tagTopicTitles);

        $matchByTagTopic = $matchByTagTopicId || $matchByTagTopicTitle;
        if ($matchByTagTopic) {
            $this->topicNamesFound[$tagTopic->getId()] = $tagTopic->getTitle();
        }

        return $matchByTagTopic;
    }

    private function checkExistence(?string $needle, array $haystack): bool
    {
        return null !== $needle && [] !== $haystack && in_array($needle, $haystack, true);
    }
}
