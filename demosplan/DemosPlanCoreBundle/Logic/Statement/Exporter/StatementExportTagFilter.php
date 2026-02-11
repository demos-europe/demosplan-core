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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;

class StatementExportTagFilter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }
    private const TAG_IDS_FILTER_KEY = 'tagIds';
    private const TAG_TITLES_FILTER_KEY = 'tagTitles';
    private const TAG_TOPIC_IDS_FILTER_KEY = 'tagTopicIds';
    private const TAG_TOPIC_TITLES_FILTER_KEY = 'tagTopicTitles';

    private array $tagsFilter = [];
    private array $tagNamesFound = [];
    private array $topicNamesFound = [];

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

        // Pre-fetch all relationships to avoid N+1 queries
        $this->initializeRelationships($statements);

        // the goal is to exclude all Segments from the payload that do not match the filter criteria
        // if all Segments from a parentStatement get excluded - the whole statement gets excluded as well.
        return $this->applyTagFilter($statements, $tagIds, $tagTitles, $tagTopicIds, $tagTopicTitles);
    }

    public function hasAnySupportedFilterSet(): bool
    {
        return $this->isTagIdFilterActive()
            || $this->isTagTitleFilterActive()
            || $this->isTagTopicIdFilterActive()
            || $this->isTagTopicTitleFilterActive();
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

    public function getTagIds(): array
    {
        return $this->tagsFilter[self::TAG_IDS_FILTER_KEY] ?? [];
    }

    public function getTagTitles(): array
    {
        return $this->tagsFilter[self::TAG_TITLES_FILTER_KEY] ?? [];
    }

    public function getTagTopicIds(): array
    {
        return $this->tagsFilter[self::TAG_TOPIC_IDS_FILTER_KEY] ?? [];
    }

    public function getTagTopicTitles(): array
    {
        return $this->tagsFilter[self::TAG_TOPIC_TITLES_FILTER_KEY] ?? [];
    }

    /**
     * Checks if any tag filters were applied and matched segments during filtering.
     *
     * @return bool True if tag filters were applied and matched, false otherwise
     */
    public function hasTagFiltersApplied(): bool
    {
        return !empty($this->tagNamesFound);
    }

    /**
     * Checks if any topic filters were applied and matched segments during filtering.
     *
     * @return bool True if topic filters were applied and matched, false otherwise
     */
    public function hasTopicFiltersApplied(): bool
    {
        return !empty($this->topicNamesFound);
    }

    /**
     * Returns a human-readable description of tag names that were matched during filtering.
     * This includes both tags filtered by ID and by title.
     *
     * @return string Formatted description of tag names
     */
    public function getTagFiltersHumanReadable(): string
    {
        if (empty($this->tagNamesFound)) {
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
        if (empty($this->topicNamesFound)) {
            return $this->translator->trans('export.filter.topics.none');
        }

        return $this->translator->trans('export.filter.topics.names', ['names' => implode(', ', $this->topicNamesFound)]);
    }

    /**
     * Pre-fetches segments, tags, and tag topics for given statements to avoid N+1 queries.
     *
     * @param Statement[] $statements
     */
    private function initializeRelationships(array $statements): void
    {
        if (empty($statements)) {
            return;
        }

        $statementIds = array_map(
            static fn (StatementInterface $s): string => $s->getId(),
            $statements
        );

        // Fetch all segments with their tags and tag topics in a single query.
        // Doctrine's identity map will cache all hydrated entities, so subsequent
        // access to $segment->getTags() and $tag->getTopic() won't trigger additional queries.
        $this->entityManager->createQueryBuilder()
            ->select('s', 'seg', 't', 'topic')
            ->from(Statement::class, 's')
            ->leftJoin('s.segmentsOfStatement', 'seg')
            ->leftJoin('seg.tags', 't')
            ->leftJoin('t.topic', 'topic')
            ->where('s.id IN (:statementIds)')
            ->setParameter('statementIds', $statementIds)
            ->getQuery()
            ->getResult();
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
        return null !== $needle && !empty($haystack) && in_array($needle, $haystack, true);
    }
}
