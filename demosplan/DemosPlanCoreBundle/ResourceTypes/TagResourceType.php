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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\TagResourceTypeInterface;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseTagResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\Factory\CallbackAttributeSetBehaviorFactory;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use InvalidArgumentException;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;

/**
 * @template-extends DplanResourceType<Tag>
 */
final class TagResourceType extends DplanResourceType implements TagResourceTypeInterface
{
    public function __construct(
        private readonly TagService $tagService,
        private readonly TagRepository $tagRepository,
        private readonly StatementHandler $statementHandler,
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

    public function getTypeName(): string
    {
        return 'Tag';
    }

    public function isListAllowed(): bool
    {
        return $this->isGetAllowed();
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_json_api_tag',
            'area_statement_segmentation',
            'feature_statements_tag',
            'area_admin_statements_tag'
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
            ['topic', 'procedure', 'id']
        )];
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasAnyPermissions('area_admin_statements_tag', 'feature_json_api_tag_create');
    }

    protected function getProperties(): BaseTagResourceConfigBuilder
    {
        $configBuilder = $this->getConfig(BaseTagResourceConfigBuilder::class);
        $configBuilder->id->setReadableByPath()->setSortable()->setFilterable();
        $configBuilder->title->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable()
            ->addPathCreationBehavior(OptionalField::NO)
            ->addUpdateBehavior(
                new CallbackAttributeSetBehaviorFactory(
                    [],
                    function (
                        TagInterface $tag,
                        ?string $title
                    ): array {
                        $this->checkTitle($title);
                        // title must be unique
                        $tagsOfProcedure =
                            $this->currentProcedureService->getProcedure()?->getTags() ?? new ArrayCollection();
                        $this->checkTitleUnique($title, $tagsOfProcedure);
                        $tag->setTitle($title);

                        return [];
                    },
                    OptionalField::NO
                )
            );
        $configBuilder->topic
            ->setRelationshipType($this->resourceTypeStore->getTagTopicResourceType())
            ->setReadableByPath()->setSortable()->setFilterable()->addPathCreationBehavior();

        $configBuilder->addCreationBehavior(
            new FixedSetBehavior(
                function (
                    Tag $tag,
                    EntityDataInterface $entityData
                ): array {
                    // check Tag
                    $this->checkTitle($entityData->getAttributes()['title'] ?? null);
                    // title must be unique
                    $tagsOfProcedure = $tag->getTopic()->getProcedure()->getTags();
                    $this->checkTitleUnique($entityData->getAttributes()['title'], $tagsOfProcedure);
                    // check TagTopic
                    $tagTopicId = ((array) $entityData->getToOneRelationships())['topic']['id'] ?? null;
                    $existingTopicsOfProcedrue =
                        $this->currentProcedureService->getProcedure()?->getTopics() ?? new ArrayCollection();
                    $this->checkTopicIdInProcedure($tagTopicId, $existingTopicsOfProcedrue);

                    $this->tagRepository->persistEntities([$tag]);

                    return [];
                }
            )
        );

        return $configBuilder;
    }

    private function checkTitle(?string $title): void
    {
        if (null === $title || '' === $title) {
            $this->logger->error('TagResourceType tried to set empty title for tag on creation');
            $this->messageBag->add('warning', 'tag.name.empty.error');

            throw new InvalidArgumentException('Tag title must not be empty');
        }
    }

    private function checkTitleUnique(string $title, Collection $tagsOfProcedure): void
    {
        if (0 < count(
                $tagsOfProcedure->filter(fn (TagInterface $t) => $t->getTitle() === $title))
        ) {
            $this->logger->error(
                'TagResourceType tried to set non-unique title for tag on update',
                ['tagName' => $title]
            );
            $this->messageBag->add('error', 'tag.name.unique.error');

            throw new InvalidArgumentException('Tag title must be unique');
        }
    }

    private function checkTopicIdInProcedure(string $topicId, Collection $topicsOfProcedure): void
    {
        if (0 < count(
                $topicsOfProcedure->filter(fn (TagTopic $t) => $t->getId() === $topicId))
        ) {
            $this->logger->error(
                'TagResourceType tried to create a tag for a topic that does not belong to the current procedure',
                ['tagTopicId' => $topicId]
            );
            $this->messageBag->add('error', 'error.api.generic');

            throw new InvalidArgumentException('TagTopic title must be unique');
        }
    }
}
