<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\CreatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use demosplan\DemosPlanStatementBundle\Exception\DuplicatedTagTopicTitleException;
use demosplan\DemosPlanStatementBundle\Logic\TagService;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\FunctionInterface;

/**
 * @template-implements CreatableDqlResourceTypeInterface<Tag>
 * @template-extends DplanResourceType<Tag>
 *
 * @property-read TagTopicResourceType $topic
 * @property-read End $title
 */
final class TagResourceType extends DplanResourceType implements CreatableDqlResourceTypeInterface
{
    /**
     * @var TagService
     */
    private $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
    }

    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public static function getName(): string
    {
        return 'Tag';
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_json_api_tag',
            'area_statement_segmentation',
            'feature_statements_tag'
        );
    }

    public function getAccessCondition(): FunctionInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            // there is currently no use case in which all tags for all procedures need to be requested
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            ...$this->topic->procedure->id
        );
    }

    public function isCreatable(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_tag_create');
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @throws DuplicatedTagTopicTitleException
     * @throws UserNotFoundException
     */
    public function createObject(array $properties): ResourceChange
    {
        $tagTopic = $this->getTagTopic($properties);
        $createTagTopic = null === $tagTopic;
        $procedure = $this->currentProcedureService->getProcedureWithCertainty();
        if ($createTagTopic) {
            $tagTopic = $this->tagService->createTagTopic(
                $this->translator->trans('tag_topic.name.default'),
                $procedure
            );
        }
        $tagEntity = new Tag($properties[$this->title->getAsNamesInDotNotation()], $tagTopic);
        $this->resourceTypeService->validateObject($tagEntity);
        $resourceChange = new ResourceChange($tagEntity, $this, $properties);
        $resourceChange->addEntityToPersist($tagEntity);
        if ($createTagTopic) {
            $resourceChange->setUnrequestedChangesToTargetResource();
        }

        return $resourceChange;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->title)->readable(true)->sortable()
                ->filterable()->initializable(),
            $this->createToOneRelationship($this->topic, true)
                ->readable(true)->sortable()->filterable()->initializable(true),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function getTagTopic(array $properties): ?TagTopic
    {
        $topicKey = $this->topic->getAsNamesInDotNotation();
        if (isset($properties[$topicKey])) {
            if (!$properties[$topicKey] instanceof TagTopic) {
                $this->logger->error('Received property tagTopic is no instance of TagTopic');

                throw new InvalidArgumentException('Invalid fields received for create request');
            }

            return $properties[$topicKey];
        }

        $procedure = $this->currentProcedureService->getProcedureWithCertainty();
        $defaultTagTopicTitle = $this->translator->trans('tag_topic.name.default');
        $topics = $this->tagService->getTagTopicsByTitle($procedure, $defaultTagTopicTitle);
        $defaultTagTopic = array_shift($topics);
        if (null !== $defaultTagTopic && 0 < count($topics)) {
            $defaultTagTopicId = $defaultTagTopic->getId();
            $this->logger->warning(
                "Found multiple matches usable as default tagTopic in procedure {$procedure->getId()}. Using the first one: {$defaultTagTopicId}"
            );
        }

        return $defaultTagTopic;
    }
}
