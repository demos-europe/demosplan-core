<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\EntityValidator\TagValidator;
use demosplan\DemosPlanCoreBundle\Exception\OcxEntryFailedException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\Workflow\PlaceService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationInterface;

use function array_map;
use function iterator_to_array;
use function mb_strtolower;
use function preg_match;
use function trim;
use function usort;

/**
 * Splits the assessable copy of an OCX `statement` entry into segments and assigns the tags, both
 * on the statement and on its segments.
 *
 * Tag titles are unique per procedure in dplan. A tag is therefore looked up by title alone; only a
 * tag that does not exist yet needs its category, which becomes the tag topic.
 */
class OcxSectionWriter
{
    private const SECTION_NUMBER = '/^[\p{L}\p{N}._]{1,50}$/u';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OcxRichText $richText,
        private readonly PlaceService $placeService,
        private readonly SegmentService $segmentService,
        private readonly SegmentValidator $segmentValidator,
        private readonly TagService $tagService,
        private readonly TagValidator $tagValidator,
    ) {
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @throws OcxEntryFailedException
     */
    public function write(Statement $copy, array $entry, OcxEntryContext $context): void
    {
        $procedure = $copy->getProcedure();
        // per entry: an entry that fails is rolled back together with the tags it created
        $tags = [];
        $topics = [];

        foreach ($entry['tags'] as $i => $tag) {
            $resolved = $this->resolveTag($procedure, $tag, "/tags/{$i}", $tags, $topics, $context);
            if (null !== $resolved) {
                $copy->addTag($resolved);
            }
        }

        $sections = $entry['sections'];
        if ([] === $sections) {
            return;
        }
        $indexes = array_keys($sections);
        usort($indexes, static fn (int $a, int $b): int => $sections[$a]['order'] <=> $sections[$b]['order']);

        $place = $this->placeService->findFirstOrderedBySortIndex($procedure->getId())
            ?? throw new RuntimeException('The procedure has no workflow place for segments');
        $orderInProcedure = $this->segmentService->getNextSegmentOrderNumber($procedure->getId());
        $externIds = [];

        foreach ($indexes as $position => $i) {
            $section = $sections[$i];
            $segment = $this->createSegment($copy, $place, $orderInProcedure++);
            $segment->setExternId($this->segmentExternId($copy, $section['number'], $position + 1, "/sections/{$i}/number", $externIds, $context));
            $segment->setText($this->richText->filter($section['text'], "/sections/{$i}/text", $context));
            $segment->setRecommendation($this->richText->filter($section['response'], "/sections/{$i}/response", $context));

            foreach ($section['tags'] as $j => $tag) {
                $resolved = $this->resolveTag($procedure, $tag, "/sections/{$i}/tags/{$j}", $tags, $topics, $context);
                if (null !== $resolved) {
                    $segment->addTag($resolved);
                }
            }

            $violations = $this->segmentValidator->validate($segment, Segment::VALIDATION_GROUP_IMPORT);
            if (0 !== $violations->count()) {
                throw OcxEntryFailedException::invalidEntry(array_map(
                    static fn (ConstraintViolationInterface $violation): string => "sections/{$i}/".$violation->getPropertyPath(),
                    iterator_to_array($violations)
                ));
            }
            $this->entityManager->persist($segment);
        }
    }

    private function createSegment(Statement $copy, Place $place, int $orderInProcedure): Segment
    {
        $segment = new Segment();
        $segment->setParentStatementOfSegment($copy);
        $segment->setProcedure($copy->getProcedure());
        $segment->setPhaseDefinition($copy->getPhaseDefinition());
        $segment->setPublicVerified(Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED);
        $segment->setPlace($place);
        $segment->setOrderInProcedure($orderInProcedure);

        return $segment;
    }

    /**
     * `<statement externId>-<number>` as the sender numbered the section, else by position.
     *
     * @param array<string, true> $externIds externIds used in this statement
     */
    private function segmentExternId(Statement $copy, ?string $number, int $position, string $pointer, array &$externIds, OcxEntryContext $context): string
    {
        $prefix = $copy->getExternId().'-';
        $number = null === $number ? null : trim($number);
        if (null !== $number && 1 === preg_match(self::SECTION_NUMBER, $number) && !isset($externIds[$prefix.$number])) {
            return $this->claim($prefix.$number, $externIds);
        }

        $suffix = $position;
        while (isset($externIds[$prefix.$suffix])) {
            ++$suffix;
        }
        if (null !== $number) {
            $context->warn('section_number_changed', $pointer, "Section number replaced by {$suffix}.");
        }

        return $this->claim($prefix.$suffix, $externIds);
    }

    /**
     * @param array<string, true> $externIds
     */
    private function claim(string $externId, array &$externIds): string
    {
        $externIds[$externId] = true;

        return $externId;
    }

    /**
     * @param array{title: string, category: ?string, centralId: ?string} $tag
     * @param array<string, Tag>                                          $tags   created or found in this entry, by lower-case title
     * @param array<string, TagTopic>                                     $topics by lower-case title
     */
    private function resolveTag(Procedure $procedure, array $tag, string $pointer, array &$tags, array &$topics, OcxEntryContext $context): ?Tag
    {
        $title = trim($tag['title']);
        $key = mb_strtolower($title);
        if (isset($tags[$key])) {
            return $tags[$key];
        }

        $existing = '' === $title ? null : $this->tagService->findUniqueByTitle($title, $procedure->getId());
        if ($existing instanceof Tag) {
            return $tags[$key] = $existing;
        }

        $category = null === $tag['category'] ? '' : trim($tag['category']);
        if ('' === $title || '' === $category) {
            $context->warn('tag_dropped', $pointer, "Tag '{$title}' has no category and matches no existing tag; left out.");

            return null;
        }

        $created = $this->tagService->createTag($title, $this->findOrCreateTopic($procedure, $category, $topics), false);
        if (!$created instanceof Tag || 0 !== $this->tagValidator->validate($created, ['segments_import'])->count()) {
            $context->warn('tag_dropped', $pointer, "Tag '{$title}' is not accepted by the receiver; left out.");

            return null;
        }
        $this->entityManager->persist($created);

        return $tags[$key] = $created;
    }

    /**
     * @param array<string, TagTopic> $topics
     */
    private function findOrCreateTopic(Procedure $procedure, string $title, array &$topics): TagTopic
    {
        $key = mb_strtolower($title);
        if (!isset($topics[$key])) {
            $topic = $this->tagService->findOneTopicByTitle($title, $procedure->getId());
            if (!$topic instanceof TagTopic) {
                $topic = new TagTopic($title, $procedure);
                $this->entityManager->persist($topic);
            }
            $topics[$key] = $topic;
        }

        return $topics[$key];
    }
}
