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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\SegmentComment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\SegmentCommentFactory;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Wrapping\Contracts\AccessException;

/**
 * @template-implements CreatableDqlResourceTypeInterface<SegmentComment>
 *
 * @template-extends DplanResourceType<SegmentComment>
 *
 * @property-read UserResourceType             $submitter
 * @property-read StatementSegmentResourceType $segment
 * @property-read PlaceResourceType            $place
 * @property-read End                          $creationDate
 * @property-read End                          $text
 */
final class SegmentCommentResourceType extends DplanResourceType implements CreatableDqlResourceTypeInterface
{
    public function __construct(private readonly SegmentCommentFactory $segmentCommentFactory)
    {
    }

    public static function getName(): string
    {
        return 'SegmentComment';
    }

    public function getEntityClass(): string
    {
        return SegmentComment::class;
    }

    public function isAvailable(): bool
    {
        return $this->isReferencable() || $this->isDirectlyAccessible();
    }

    public function isReferencable(): bool
    {
        return $this->currentUser->hasPermission('feature_segment_comment_list_on_segment');
    }

    public function isDirectlyAccessible(): bool
    {
        return $this->currentUser->hasPermission('feature_segment_comment_create');
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        // if a segment can be accessed then all its comments can be read
        return $this->conditionFactory->true();
    }

    public function isCreatable(): bool
    {
        return $this->isDirectlyAccessible();
    }

    public function createObject(array $properties): ResourceChange
    {
        /** @var Segment $segment */
        $segment = $properties[$this->segment->getAsNamesInDotNotation()];
        /** @var User $user */
        $user = $properties[$this->submitter->getAsNamesInDotNotation()];
        /** @var Place $place */
        $place = $properties[$this->place->getAsNamesInDotNotation()];
        /** @var string $text */
        $text = $properties[$this->text->getAsNamesInDotNotation()];

        if ($this->currentUser->getUser()->getId() !== $user->getId()) {
            throw new AccessException('Creating comments in the name of other users is not allowed.');
        }

        if ($segment->getPlace()->getId() !== $place->getId()) {
            throw new AccessException('Segment must be in same place as comment on creation.');
        }

        $comment = $this->segmentCommentFactory->createSegmentComment($segment, $user, $place, $text);

        $this->resourceTypeService->validateObject($comment);
        $this->resourceTypeService->validateObject(
            $segment,
            [
                ResourceTypeService::VALIDATION_GROUP_DEFAULT,
                Segment::VALIDATION_GROUP_SEGMENT_MANDATORY,
            ]
        );

        $resourceChange = new ResourceChange($comment, $this, $properties);
        $resourceChange->addEntityToPersist($comment);

        return $resourceChange;
    }

    protected function getProperties(): array
    {
        $creationDate = $this->createAttribute($this->creationDate);
        $text = $this->createAttribute($this->text);
        $submitter = $this->createToOneRelationship($this->submitter);
        $place = $this->createToOneRelationship($this->place);
        $segment = $this->createToOneRelationship($this->segment);

        if ($this->currentUser->hasPermission('feature_segment_comment_list_on_segment')) {
            $creationDate->readable(false, fn (SegmentComment $comment): string => $this->formatDate($comment->getCreationDate()));
            $text->readable();
            $submitter->readable();
            $place->readable();
        }

        if ($this->isCreatable()) {
            $submitter->initializable();
            $place->initializable();
            $segment->initializable();
            $text->initializable();
        }

        return [
            $this->createAttribute($this->id)->readable(true),
            $creationDate,
            $text,
            $submitter,
            $place,
            $segment,
        ];
    }
}
