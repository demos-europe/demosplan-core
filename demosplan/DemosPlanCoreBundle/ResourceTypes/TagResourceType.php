<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseTagResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\Contracts\ContentField;
use EDT\Wrapping\CreationDataInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\CallbackToOneRelationshipSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\ToOneRelationshipConstructorBehavior;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<Tag>
 *
 * @property-read TagTopicResourceType $topic
 * @property-read End $title
 */
final class TagResourceType extends DplanResourceType
{
    public function __construct(
        private readonly TagService $tagService,
        private readonly TagRepository $tagRepository,
    ) {
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

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            // there is currently no use case in which all tags for all procedures need to be requested
            return [$this->conditionFactory->false()];
        }

        return [$this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            $this->topic->procedure->id
        )];
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_tag_create');
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(BaseTagResourceConfigBuilder::class);
        $configBuilder->id->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable();
        $configBuilder->title->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable()
            ->addPathCreationBehavior()
            ->addConstructorBehavior(
                AttributeConstructorBehavior::createFactory(null, OptionalField::NO, null)
            );
        $configBuilder->topic
            ->setRelationshipType($this->resourceTypeStore->getTagTopicResourceType())
            ->readable(true, null, true)
            ->sortable()
            ->filterable()
           ->addConstructorBehavior(
                ToOneRelationshipConstructorBehavior::createFactory(null, [], function (CreationDataInterface $entityData): array {
                    $tagTopic = $this->getTagTopic($entityData);
                    return [$tagTopic, [Paths::tag()->topic->getAsNamesInDotNotation()]];
                }, OptionalField::NO)
            );


        $configBuilder->addPostConstructorBehavior(new FixedSetBehavior(function (Tag $tag, EntityDataInterface $entityData): array {
            $this->tagRepository->persistEntities([$tag]);

            return [];
        }));

        return $configBuilder;
    }


    private function getTagTopic($entityData): ?TagTopic
    {
        $procedure = $this->currentProcedureService->getProcedureWithCertainty();
        $toOneRelationships = $entityData->getToOneRelationships();
        $topicRef = $toOneRelationships[$this->topic->getAsNamesInDotNotation()];
        $topicId = $topicRef[ContentField::ID];

        $tagTopics = $this->tagService->getTagTopicsById($procedure, $topicId);
        $topic = array_shift($tagTopics);
        if (null !== $topic) {
           return $topic;
        }

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
