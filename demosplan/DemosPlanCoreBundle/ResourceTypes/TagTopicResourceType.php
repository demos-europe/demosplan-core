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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseTagTopicResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\ToOneRelationshipConstructorBehavior;

/**
 * @template-extends DplanResourceType<TagTopic>
 *
 * @property-read End $title
 * @property-read ProcedureResourceType $procedure
 * @property-read TagResourceType $tags
 */
final class TagTopicResourceType extends DplanResourceType
{
    public function __construct(
        private readonly TagService $tagService,
        protected TagTopicRepository $tagTopicRepository,
    ) {
    }

    public function getEntityClass(): string
    {
        return TagTopic::class;
    }

    public static function getName(): string
    {
        return 'TagTopic';
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_json_api_tag_topic',
            'area_statement_segmentation'
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
            $this->procedure->id
        )];
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_tag_topic_create');
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(BaseTagTopicResourceConfigBuilder::class);

        $configBuilder->id->readable()->sortable()->filterable();

        $configBuilder->title->readable(true)->sortable()->filterable()
            ->addConstructorBehavior(
                AttributeConstructorBehavior::createFactory(null, OptionalField::NO, null)
            );

        $configBuilder->procedure
            ->setRelationshipType($this->resourceTypeStore->getProcedureResourceType())
            ->readable()->sortable()->filterable()
            ->addConstructorBehavior(
                ToOneRelationshipConstructorBehavior::createFactory(null, [], null, OptionalField::NO)
            );

        $configBuilder->tags
            ->setRelationshipType($this->resourceTypeStore->getTagResourceType())
            ->readable()->sortable()->filterable();

        $configBuilder->addPostConstructorBehavior(
            new FixedSetBehavior(function (TagTopic $tagTopic, EntityDataInterface $entityData): array {
                $procedure = $tagTopic->getProcedure();
                $title = $tagTopic->getTitle();
                if ($procedure->getId() !== $this->currentProcedureService->getProcedureIdWithCertainty()) {
                    throw new BadRequestException('Contradicting request');
                }

                if (!$this->currentUser->getPermissions()->ownsProcedure()) {
                    throw new BadRequestException('Access denied');
                }

                $this->tagService->assertTitleNotDuplicated($title, $procedure);
                $procedure->addTagTopic($tagTopic);
                $this->resourceTypeService->validateObject($procedure, [
                    ProcedureInterface::VALIDATION_GROUP_DEFAULT,
                    ProcedureInterface::VALIDATION_GROUP_MANDATORY_PROCEDURE,
                ]);
                $this->tagTopicRepository->persistEntities([$tagTopic]);

                return [];
            })
        );

        return $configBuilder;
    }
}
