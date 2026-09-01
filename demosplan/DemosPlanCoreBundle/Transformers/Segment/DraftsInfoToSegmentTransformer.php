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

use DateTime;
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
use demosplan\DemosPlanCoreBundle\Logic\Statement\SegmentMarkParser;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Logic\Workflow\PlaceService;
use demosplan\DemosPlanCoreBundle\Validator\DraftsInfoValidator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use InvalidArgumentException;
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
        private readonly SegmentMarkParser $segmentMarkParser,
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
     * - Order-based format with contentBlocks (segments + text sections)
     * - Segment-mark format, where the text comes from the <segment-mark> elements
     *   in textualReference and `segments` only carries the metadata
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
        $attributes = $draftsInfoArray['data']['attributes'] ?? [];

        // Detect format: order-based (contentBlocks) vs segment-mark based (textualReference)
        if (isset($attributes['contentBlocks'])) {
            [$drafts, $textSectionBlocks] = $this->extractContentBlockDrafts($attributes);
        } else {
            $drafts = $this->extractSegmentMarkDrafts($attributes);
            $textSectionBlocks = [];
        }

        // Temporarily change ID generator to AssignedGenerator so Doctrine handles manually-assigned IDs properly
        $segmentMetadata = $this->entityManager->getClassMetadata(Segment::class);
        $originalIdGenerator = $segmentMetadata->idGenerator;
        $segmentMetadata->setIdGenerator(new AssignedGenerator());

        $counter = 1;
        $internId = $this->segmentHandler->getNextSegmentOrderNumber($procedure->getId());
        foreach ($drafts as $draft) {
            $metadata = $draft['metadata'];

            $segment = new Segment();
            $segment->setId($draft['id']);
            $segment->setParentStatementOfSegment($statement);
            $segment->setText($draft['text']);
            $segment->setExternId($statement->getExternId().'-'.$counter);
            $segment->setOrderInProcedure($draft['order'] ?? $internId);
            // @todo DPLAN-16766 Is it necessary to determine the audience from the statement (isCreatedByCitizen vs isCreatedByInvitableInstitution) to use the public participation phase object instead?
            $segment->setPhaseDefinition($procedure->getPhaseObject()->getPhaseDefinition());
            $segment->setProcedure($procedure);

            /** @var Segment $segment */
            $segment = $this->statementService->setPublicVerified(
                $segment,
                Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED
            );
            $segment = $this->setAssigneeIfGiven($segment, $metadata);
            $segment = $this->setPlace($segment, $metadata);
            $segment = $this->setDeadlineIfGiven($segment, $metadata);

            $this->entityManager->persist($segment);

            $segments[] = $segment;
            ++$counter;
            ++$internId;
        }

        // Restore the original ID generator (done with manually-assigned segment IDs)
        $segmentMetadata->setIdGenerator($originalIdGenerator);

        // Set tags after persist (junction table entries will be flushed by controller)
        foreach ($segments as $index => $segment) {
            $tags = $this->getTags($drafts[$index]['metadata']['tags'] ?? [], $procedure);
            $segment->setTags($tags);
        }

        $statement->setSegmentsOfStatement(new ArrayCollection($segments));

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
     * Normalizes the order-based `contentBlocks` format into drafts plus the raw
     * text section blocks. Segment blocks carry their own metadata (tags, place).
     *
     * @param array<mixed> $attributes
     *
     * @return array{0: list<array{id: string, text: string, order: int|null, metadata: array<mixed>}>, 1: list<array<mixed>>}
     */
    private function extractContentBlockDrafts(array $attributes): array
    {
        $segmentBlocks = array_values(array_filter(
            $attributes['contentBlocks'],
            static fn (array $block): bool => 'segment' === ($block['type'] ?? '')
        ));
        usort($segmentBlocks, static fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        $textSectionBlocks = array_values(array_filter(
            $attributes['contentBlocks'],
            static fn (array $block): bool => 'textSection' === ($block['type'] ?? '')
        ));

        $drafts = array_map(
            static fn (array $block): array => [
                'id'       => $block['id'],
                'text'     => $block['text'],
                'order'    => $block['order'] ?? null,
                'metadata' => $block,
            ],
            $segmentBlocks
        );

        return [$drafts, $textSectionBlocks];
    }

    /**
     * Normalizes the segment-mark format: the text comes from the `<segment-mark>`
     * elements in `textualReference`, while `segments` only carries the metadata
     * (tags, assignee, place, deadline) looked up by segment ID.
     *
     * @param array<mixed> $attributes
     *
     * @return list<array{id: string, text: string, order: null, metadata: array<mixed>}>
     */
    private function extractSegmentMarkDrafts(array $attributes): array
    {
        $parsedMarks = $this->segmentMarkParser->parse($attributes['textualReference']);

        $metadataById = [];
        foreach ($attributes['segments'] as $segmentMetaData) {
            $metadataById[$segmentMetaData['id']] = $segmentMetaData;
        }

        return array_map(
            static fn (array $mark): array => [
                'id'       => $mark['segmentId'],
                'text'     => $mark['text'],
                'order'    => null,
                'metadata' => $metadataById[$mark['segmentId']] ?? [],
            ],
            $parsedMarks
        );
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws NoResultException
     */
    private function setAssigneeIfGiven(Segment $segment, array $metadata): Segment
    {
        if (null !== data_get($metadata, 'assigneeId')) {
            $segment->setAssignee($this->userService->findWithCertainty($metadata['assigneeId']));
        }

        return $segment;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws NoResultException
     */
    private function setPlace(Segment $segment, array $metadata): Segment
    {
        $placeId = $metadata['place']['id'] ?? null;
        $place = null !== $placeId && '' !== $placeId
            ? $this->placeService->findWithCertainty($placeId)
            : $this->placeService->findFirstOrderedBySortIndex($segment->getProcedure()->getId());

        if (null !== $place) {
            $segment->setPlace($place);
        }

        return $segment;
    }

    /**
     * Sets the deadline (Bearbeitungsfrist) from the split metadata, if provided.
     * The frontend sends it as an ISO date string ("Y-m-d"); a malformed value is
     * rejected as a client error rather than silently producing a wrong date.
     *
     * @param array<string, mixed> $metadata
     *
     * @throws InvalidArgumentException on a non-empty value that is not a valid "Y-m-d" date
     */
    private function setDeadlineIfGiven(Segment $segment, array $metadata): Segment
    {
        $deadline = data_get($metadata, 'deadline');
        if (!is_string($deadline) || '' === trim($deadline)) {
            return $segment;
        }

        $date = DateTime::createFromFormat('!Y-m-d', trim($deadline));
        $errors = DateTime::getLastErrors();
        if (!$date instanceof DateTime
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('Invalid deadline provided; expected format YYYY-MM-DD.');
        }

        $segment->setDeadline($date);

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
