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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<TagTopic>
 *
 * @template-implements CreatableDqlResourceTypeInterface<TagTopic>
 *
 * @property-read End $id
 * @property-read End $title
 * @property-read ProcedureResourceType $procedure
 * @property-read TagResourceType $tags
 */
final class TagTopicResourceType extends DplanResourceType implements CreatableDqlResourceTypeInterface
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

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            // there is currently no use case in which all tags for all procedures need to be requested
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            $this->procedure->id
        );
    }

    public function isCreatable(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_tag_topic_create');
    }

    public function createObject(array $properties): ResourceChange
    {
        /** @var Procedure $procedure */
        $procedure = $properties[$this->procedure->getAsNamesInDotNotation()];
        /** @var string $title */
        $title = $properties[$this->title->getAsNamesInDotNotation()];

        if ($procedure->getId() !== $this->currentProcedureService->getProcedureIdWithCertainty()) {
            throw new BadRequestException('Contradicting request');
        }

        if (!$this->currentUser->getPermissions()->ownsProcedure()) {
            throw new BadRequestException('Access denied');
        }

        $tagTopic = $this->tagService->createTagTopic($title, $procedure, false);
        $procedure->addTagTopic($tagTopic);

        $this->resourceTypeService->validateObject($tagTopic);
        $this->resourceTypeService->validateObject($procedure, [
            Procedure::VALIDATION_GROUP_DEFAULT,
            Procedure::VALIDATION_GROUP_MANDATORY_PROCEDURE,
        ]);

        $resourceChange = new ResourceChange($tagTopic, $this, $properties);
        $resourceChange->addEntityToPersist($tagTopic);

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
            $this->createAttribute($this->title)->readable(true)->sortable()->filterable()->initializable(),
            $this->createToOneRelationship($this->procedure)->readable()->sortable()->filterable()->initializable(),
            $this->createToManyRelationship($this->tags)->readable()->sortable()->filterable(),
        ];
    }
}
