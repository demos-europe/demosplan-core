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
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\DraftsInfoHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces\SegmentTransformerInterface;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Logic\Workflow\PlaceService;
use demosplan\DemosPlanCoreBundle\Validator\DraftsInfoValidator;
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
    ) {
    }

    /**
     * Transforms DraftsInfo to Segment Entities.
     *
     * @param string $draftsInfo
     *
     * @return array<Segment>
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
        if (null === $statement) {
            throw StatementNotFoundException::createFromId($statementId);
        }

        return $this->getSegments($draftsInfoArray, $statement);
    }

    /**
     * @param array<mixed> $draftsInfoArray
     *
     * @return array<int, Segment>
     *
     * @throws Exception
     */
    private function getSegments(array $draftsInfoArray, Statement $statement): array
    {
        $segments = [];
        $procedure = $statement->getProcedure();
        $draftsList = $this->draftsInfoHandler->extractDraftsList($draftsInfoArray);
        // The segments are received potentially unsorted. Hence sort them by their position
        // in the text so their $externId is set in the correct order afterwards.
        usort($draftsList, static fn(array $draft1, array $draft2) => $draft1['charEnd'] < $draft2['charEnd'] ? -1 : 1);
        $counter = 1;
        $internId = $this->segmentHandler->getNextSegmentOrderNumber($procedure->getId());
        foreach ($draftsList as $draft) {
            $segment = new Segment();
            $segment->setParentStatementOfSegment($statement);
            $segment->setId($draft['id']);
            $segment->setText($draft['text']);
            $externId = $statement->getExternId().'-'.$counter;
            $segment->setExternId($externId);
            $segment->setOrderInProcedure($internId);
            $segment->setPhase('analysis');
            $segment->setProcedure($statement->getProcedure());
            $tags = $this->getTags($draft['tags'], $procedure);
            $segment->setTags($tags);
            $segment = $this->statementService->setPublicVerified(
                $segment,
                Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED
            );
            $segment = $this->setAssigneeIfGiven($segment, $draft);
            $segment = $this->setPlace($segment, $draft);
            $segments[] = $segment;
            ++$counter;
            ++$internId;
        }

        return $segments;
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
        $place = null !== $placeId
            ? $this->placeService->findWithCertainty($placeId)
            : $this->placeService->findFirstOrderedBySortIndex($segment->getProcedure()->getId());

        $segment->setPlace($place);

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
        if (null !== $defaultTagTopic && 0 < count($topics)) {
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
