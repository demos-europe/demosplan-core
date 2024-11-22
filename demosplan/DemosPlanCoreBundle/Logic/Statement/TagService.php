<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTitleException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTopicTitleException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Contracts\PathException;
use Exception;
use Webmozart\Assert\Assert;

class TagService extends CoreService
{
    public function __construct(
        private readonly BoilerplateRepository $boilerplateRepository,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly TagRepository $tagRepository,
        private readonly TagTopicRepository $tagTopicRepository
    ) {
    }

    /**
     * Returns the topic with the given Id.
     *
     * @param string $id
     */
    public function getTopic($id): ?TagTopic
    {
        try {
            $result = $this->tagTopicRepository
                ->get($id);
        } catch (Exception $e) {
            $this->logger->error('Get Topic with ID: '.$id.' failed: ', [$e]);

            return null;
        }

        return $result;
    }

    /**
     * Returns a single Tag.
     *
     * @param string $id - identifies the Tag
     *
     * @return Tag|null
     */
    public function getTag($id)
    {
        try {
            return $this->tagRepository->get($id);
        } catch (Exception $e) {
            $this->logger->error('Get Tag with ID: '.$id.' failed: ', [$e]);

            return null;
        }
    }

    /**
     * Creates a new Tag with the given title.
     *
     * @throws DuplicatedTagTitleException
     */
    public function createTag(string $title, TagTopic $topic, bool $persistAndFlush = true): Tag
    {
        $procedureId = $topic->getProcedure()->getId();
        if ('' === $title) {
            throw new InvalidArgumentException('Tag title may not be empty.');
        }

        if (!$this->tagRepository->isTagTitleFree($procedureId, $title)) {
            throw DuplicatedTagTitleException::createFromTitleAndProcedureId($topic, $title);
        }

        $toCreate = new Tag($title, $topic);

        if ($persistAndFlush) {
            $this->tagRepository->addObject($toCreate);
        }

        return $toCreate;
    }

    /**
     * Creates a new TagTopic with the given title.
     *
     * @param string $title - Title of the new topic
     *
     * @throws DuplicatedTagTopicTitleException
     * @throws Exception
     */
    public function createTagTopic($title, Procedure $procedure, bool $persistAndFlush = true): TagTopic
    {
        $this->assertTitleNotDuplicated($title, $procedure);
        $toCreate = new TagTopic($title, $procedure);

        if (!$persistAndFlush) {
            return $toCreate;
        }

        return $this->tagTopicRepository->addObject($toCreate);
    }

    /**
     * @param string $title
     *
     * @throws DuplicatedTagTopicTitleException
     */
    public function assertTitleNotDuplicated($title, Procedure $procedure): void
    {
        $titleCount = $this->tagTopicRepository->count(['procedure' => $procedure, 'title' => $title]);
        if (0 !== $titleCount) {
            throw DuplicatedTagTopicTitleException::createFromTitleAndProcedureId($title, $procedure->getId());
        }
    }

    /**
     * Moves a spezific Tag to a specific Topic.
     * Because a Tag can have one Topic only, it is necessary to remove this Tag from the current Topic (if exists).
     *
     * @param Tag      $tag
     * @param TagTopic $newTopic
     */
    public function moveTagToTopic($tag, $newTopic): bool
    {
        // setTopic also removes the tag from the current Topic
        $newTopic->addTag($tag);
        $tag->setTopic($newTopic);

        $tagUpdated = $this->tagRepository->updateObject($tag);

        $topicUpdated = $this->tagTopicRepository->updateObject($newTopic);

        return $tagUpdated instanceof Tag && $topicUpdated instanceof TagTopic;
    }

    /**
     * Attach a BoilerplateText to a tag.
     *
     * @param Tag         $tag
     * @param Boilerplate $boilerplate
     *
     * @throws Exception
     */
    public function attachBoilerplateToTag($tag, $boilerplate)
    {
        $boilerplate->addTag($tag);
        $this->boilerplateRepository->updateObject($boilerplate);
        $this->tagRepository->updateObject($tag);
    }

    /**
     * Detach a BoilerplateText from a tag.
     *
     * @param Tag         $tag
     * @param Boilerplate $boilerplate
     *
     * @throws Exception
     */
    public function detachBoilerplateFromTag($tag, $boilerplate)
    {
        if (null !== $boilerplate) {
            $boilerplate->removeTag($tag);
            $this->boilerplateRepository->updateObject($boilerplate);
            $this->tagRepository->updateObject($tag);
        }
    }

    /**
     * Renames a topic.
     *
     * @param string $id
     * @param string $name
     *
     * @return TagTopic|false
     */
    public function renameTopic($id, $name)
    {
        $topic = $this->getTopic($id);
        $topic->setTitle($name);

        return $this->tagTopicRepository->updateObject($topic);
    }

    /**
     * Renames a tag.
     *
     * @param string $id
     * @param string $name
     *
     * @return Tag|false
     */
    public function renameTag($id, $name)
    {
        $tag = $this->getTag($id);
        $tag->setTitle($name);

        return $this->tagRepository->updateObject($tag);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function updateTagTopicalTag(string $id, bool $isTopicalTag): Tag
    {
        $tag = $this->getTag($id);
        Assert::notNull($tag);
        $tag->setTopicalTag($isTopicalTag);

        $updatedTag = $this->tagRepository->updateObject($tag);
        Assert::isInstanceOf($updatedTag, Tag::class);

        return $updatedTag;
    }

    /**
     * Deletes a Tag.
     *
     * @param Tag $tag
     *
     * @throws InvalidArgumentException
     */
    public function deleteTag($tag): bool
    {
        return $this->tagRepository->delete($tag);
    }

    /**
     * Deletes a Topic.
     *
     * @param TagTopic $topic
     *
     * @throws EntityNotFoundException
     */
    public function deleteTopic($topic): bool
    {
        return $this->tagTopicRepository->delete($topic);
    }

    /**
     * @return array<int,TagTopic>
     */
    public function getTagTopicsByTitle(Procedure $procedure, string $tagTopicTitle): array
    {
        return $this->tagTopicRepository->findBy([
            'procedure' => $procedure->getId(),
            'title'     => $tagTopicTitle,
        ]);
    }

    /**
     * This method will attempt to find a unique {@link Tag} by the given tag title and {@link Procedure}
     * ID, even though multiple tags may exist within different {@link TagTopic}s.
     *
     * @throws NonUniqueResultException thrown if multiple tags were found
     * @throws PathException            thrown if the property names in the entities were changed without adjusting this method
     */
    public function findUniqueByTitle(string $tagTitle, string $procedureId): ?Tag
    {
        $conditions = [
            $this->conditionFactory->propertyHasValue($tagTitle, ['title']),
            $this->conditionFactory->propertyHasValue($procedureId, ['topic', 'procedure', 'id']),
        ];

        $tags = $this->tagRepository->getEntities($conditions, []);

        $count = count($tags);
        if (1 < $count) {
            throw new NonUniqueResultException("Expected no more than one tag matching tag title '$tagTitle' and procedure ID '$procedureId'; got $count.");
        }

        return array_shift($tags);
    }

    public function findOneTopicByTitle(string $title, string $procedureId): ?TagTopic
    {
        return $this->tagTopicRepository->findOneByTitle($title, $procedureId);
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, Tag>
     */
    public function findByIds(array $ids): array
    {
        return $this->tagRepository->findByIds($ids);
    }
}
