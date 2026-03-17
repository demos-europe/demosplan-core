<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers\Segment;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\SegmentTransformerInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\DraftsInfoHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Logic\Workflow\PlaceService;
use demosplan\DemosPlanCoreBundle\Validator\DraftsInfoValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Transforms DraftsInfo to Segment Entities.
 *
 * Class DraftsInfoToSegmentTransformer
 */
class DraftsInfoToSegmentTransformer implements SegmentTransformerInterface
{
    public function __construct(
        private readonly DraftsInfoHandler $draftsInfoHandler,
        private readonly DraftsInfoValidator $draftsInfoValidator,
        private readonly LoggerInterface $logger,
        private readonly MessageBagInterface $messageBag,
        private readonly PlaceService $placeService,
        private readonly SegmentHandler $segmentHandler,
        private readonly StatementHandler $statementHandler,
        private readonly StatementService $statementService,
        private readonly TagService $tagService,
        private readonly TranslatorInterface $translator,
        private readonly UserService $userService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Transforms DraftsInfo to Segment and TextSection Entities.
     *
     * Supports two formats:
     * - New order-based format with contentBlocks (segments + text sections)
     * - Legacy position-based format with segments only
     *
     * @param string $draftsInfo
     *
     * @return array{segments: array<Segment>, textSections: array<TextSection>}
     *
     * @throws FileNotFoundException
     * @throws InvalidSchemaException
     * @throws Exception
     * @throws StatementNotFoundException
     */
    public function transform($draftsInfo): array
    {
        $this->draftsInfoValidator->validate($draftsInfo);
        $draftsInfoArray = Json::decodeToArray($draftsInfo);
        $statementId = $this->draftsInfoHandler->extractStatementId($draftsInfoArray);
        $statement = $this->statementHandler->getStatement($statementId);
        if (!$statement instanceof Statement) {
            throw StatementNotFoundException::createFromId($statementId);
        }

        return $this->getSegmentsAndTextSections($draftsInfoArray, $statement);
    }

    /**
     * @param array<mixed> $draftsInfoArray
     *
     * @return array{segments: array<int, Segment>, textSections: array<int, TextSection>}
     *
     * @throws Exception
     */
    private function getSegmentsAndTextSections(array $draftsInfoArray, Statement $statement): array
    {
        $segments = [];
        $textSections = [];
        $procedure = $statement->getProcedure();

        // Detect format: new order-based (contentBlocks) vs legacy position-based (segments)
        $attributes = $draftsInfoArray['data']['attributes'] ?? [];
        $isOrderBased = isset($attributes['contentBlocks']);

        if ($isOrderBased) {
            $segmentBlocks = array_filter(
                $attributes['contentBlocks'],
                static fn (array $block): bool => 'segment' === ($block['type'] ?? '')
            );
            usort($segmentBlocks, static fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

            $textSectionBlocks = array_filter(
                $attributes['contentBlocks'],
                static fn (array $block): bool => 'textSection' === ($block['type'] ?? '')
            );
            $draftsList = $segmentBlocks;
        } else {
            $draftsList = $this->draftsInfoHandler->extractDraftsList($draftsInfoArray);
            // Sort by charEnd position if present
            $hasCharEnd = !empty(array_filter($draftsList, static fn (array $d): bool => ($d['charEnd'] ?? 0) > 0));
            if ($hasCharEnd) {
                usort($draftsList, static fn (array $a, array $b): int => ($a['charEnd'] ?? 0) <=> ($b['charEnd'] ?? 0));
            }
            $textSectionBlocks = [];
        }

        // Temporarily change ID generator to AssignedGenerator so Doctrine handles manually-assigned IDs properly
        $segmentMetadata = $this->entityManager->getClassMetadata(Segment::class);
        $originalIdGenerator = $segmentMetadata->idGenerator;
        $segmentMetadata->setIdGenerator(new \Doctrine\ORM\Id\AssignedGenerator());

        $counter = 1;
        $internId = $this->segmentHandler->getNextSegmentOrderNumber($procedure->getId());
        foreach ($draftsList as $draft) {
            $segment = new Segment();
            $segment->setId($draft['id']);
            $segment->setParentStatementOfSegment($statement);
            $segment->setText($draft['text']);
            $externId = $statement->getExternId().'-'.$counter;
            $segment->setExternId($externId);
            $segment->setOrderInProcedure($isOrderBased ? ($draft['order'] ?? $internId) : $internId);
            $segment->setPhase('analysis');
            $segment->setProcedure($statement->getProcedure());

            /** @var Segment $segment */
            $segment = $this->statementService->setPublicVerified(
                $segment,
                Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED
            );
            $segment = $this->setAssigneeIfGiven($segment, $draft);
            $segment = $this->setPlace($segment, $draft);

            $this->entityManager->persist($segment);

            $segments[] = $segment;
            ++$counter;
            ++$internId;
        }

        // Restore the original ID generator
        $segmentMetadata->setIdGenerator($originalIdGenerator);

        // Set tags (junction table entries will be flushed by controller)
        array_map(
            function (Segment $segment, array $draft) use ($procedure): void {
                $tags = $this->getTags($draft['tags'] ?? [], $procedure);
                $segment->setTags($tags);
            },
            $segments,
            $draftsList
        );

        // Create TextSection entities from contentBlocks
        foreach ($textSectionBlocks as $block) {
            $textSection = new TextSection();
            $textSection->setStatement($statement);
            $textSection->setOrderInStatement($block['order'] ?? 0);
            $textSection->setTextRaw($block['text'] ?? '');
            $textSection->setText($block['text'] ?? '');
            $statement->addTextSection($textSection);
            $textSections[] = $textSection;
        }

        return [
            'segments'     => $segments,
            'textSections' => $textSections,
        ];
    }

    /**
     * @param array<string, string> $draft
     *
     * @throws NoResultException
     */
    private function setAssigneeIfGiven(Segment $segment, array $draft): Segment
    {
        if (null !== data_get($draft, 'assigneeId')) {
            $segment->setAssignee($this->userService->findWithCertainty($draft['assigneeId']));
        }

        return $segment;
    }

    /**
     * @param array<string, string> $draft
     *
     * @throws NoResultException
     */
    private function setPlace(Segment $segment, array $draft): Segment
    {
        $placeId = $draft['place']['id'] ?? null;
        $place = null !== $placeId && '' !== $placeId
            ? $this->placeService->findWithCertainty($placeId)
            : $this->placeService->findFirstOrderedBySortIndex($segment->getProcedure()->getId());

        if (null !== $place) {
            $segment->setPlace($place);
        }

        return $segment;
    }

    /**
     * @param array<string,string>[] $draftInfoTags
     *
     * @return array<int, Tag>
     *
     * @throws Exception
     */
    private function getTags(array $draftInfoTags, Procedure $procedure): array
    {
        $procedureId = $procedure->getId();

        $defaultTagTopicTitle = $this->translator->trans('tag_topic.name.default');
        $topics = $this->tagService->getTagTopicsByTitle($procedure, $defaultTagTopicTitle);
        $defaultTagTopic = array_shift($topics);
        if (null !== $defaultTagTopic && [] !== $topics) {
            $defaultTagTopicId = $defaultTagTopic->getId();
            $this->logger->warning(
                "Found multiple matches usable as default tagTopic in procedure {$procedureId}. Using the first one: {$defaultTagTopicId}"
            );
        }

        $tags = [];
        foreach ($draftInfoTags as $tag) {
            $tagEntity = $this->tagService->getTag($tag['id']);
            if (!$tagEntity instanceof Tag) {
                try {
                    $tagEntity = $this->tagService->findUniqueByTitle($tag['tagName'], $procedureId);
                } catch (NonUniqueResultException) {
                    $this->logger->warning(
                        "Found multiple tags with title '{$tag['tagName']}' in procedure {$procedureId}. Using the first one."
                    );
                    $this->messageBag->add(
                        'warning',
                        $this->translator->trans('warning.tag.multiple.tags.found', ['tagname' => $tag['tagName']]));

                    $tagEntity = $this->tagService->findOneTopicByTitle($tag['tagName'], $procedureId);
                }
            }
            if (null === $tagEntity) {
                if (null === $defaultTagTopic) {
                    $defaultTagTopic = $this->tagService->createTagTopic($defaultTagTopicTitle, $procedure);
                }
                // it is not possible to use remote tagId as "real" id, as tags in dplan are bound to procedures
                // remote service sends ids per tag regardless of procedures.
                $tagEntity = $this->tagService->createTag($tag['tagName'], $defaultTagTopic);
            }

            $tags[] = $tagEntity;
        }

        return $tags;
    }

    /**
     * Returns true for text formats.
     */
    public function supports(string $format): bool
    {
        return self::DRAFTS_INFO === $format;
    }
}
