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
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseSegmentCommentResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\SegmentComment;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Repository\SegmentCommentRepository;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\ToOneRelationshipConstructorBehavior;
use Geocoder\Assert;

/**
 * @template-extends DplanResourceType<SegmentComment>
 *
 * @property-read UserResourceType             $submitter
 * @property-read StatementSegmentResourceType $segment
 * @property-read PlaceResourceType            $place
 * @property-read End                          $creationDate
 * @property-read End                          $text
 */
final class SegmentCommentResourceType extends DplanResourceType
{
    public function __construct(
        protected readonly SegmentCommentRepository $segmentCommentRepository,
    ) {
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
        return $this->currentUser->hasPermission('feature_segment_comment_create');
    }

    protected function getAccessConditions(): array
    {
        // if a segment can be accessed then all its comments can be read
        return [];
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_segment_comment_create');
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(BaseSegmentCommentResourceConfigBuilder::class);
        $configBuilder->id->readable();
        $configBuilder->submitter->setRelationshipType($this->resourceTypeStore->getUserResourceType());
        $configBuilder->place->setRelationshipType($this->resourceTypeStore->getPlaceResourceType());

        if ($this->currentUser->hasPermission('feature_segment_comment_list_on_segment')) {
            $configBuilder->creationDate
                ->readable(false, fn (SegmentComment $comment): string => $this->formatDate($comment->getCreationDate()));
            $configBuilder->text->readable();
            $configBuilder->submitter->readable();
            $configBuilder->place->readable();
        }

        if ($this->isCreateAllowed()) {
            // Creating comments in the name of other users is not allowed.
            $currentUserCondition = $this->conditionFactory->propertyHasValue(
                $this->currentUser->getUser()->getId(),
                Paths::user()->id
            );
            $configBuilder->submitter->addConstructorBehavior(ToOneRelationshipConstructorBehavior::createFactory(null, [$currentUserCondition], null, OptionalField::NO));
            $configBuilder->place->addConstructorBehavior(ToOneRelationshipConstructorBehavior::createFactory(null, [], null, OptionalField::NO));
            $configBuilder->segment
                ->setRelationshipType($this->resourceTypeStore->getStatementSegmentResourceType())
                ->addConstructorBehavior(ToOneRelationshipConstructorBehavior::createFactory(null, [], null, OptionalField::NO));

            $configBuilder->text->addConstructorBehavior(AttributeConstructorBehavior::createFactory(null, OptionalField::NO, null));

            $configBuilder->addPostConstructorBehavior(new FixedSetBehavior(function (SegmentComment $segmentComment, EntityDataInterface $entityData): array {
                $segment = $segmentComment->getSegment();
                $segmentCommentPlace = $segmentComment->getPlace();
                Assert::notNull($segmentCommentPlace);
                $this->segmentCommentRepository->persistEntities([$segmentComment]);
                $segment->addComment($segmentComment);

                if ($segment->getPlace()->getId() !== $segmentCommentPlace->getId()) {
                    throw new AccessException($this, 'Segment must be in same place as comment on creation.');
                }

                $this->resourceTypeService->validateObject(
                    $segment,
                    [ResourceTypeService::VALIDATION_GROUP_DEFAULT, Segment::VALIDATION_GROUP_SEGMENT_MANDATORY]
                );

                return [];
            }));
        }

        return $configBuilder;
    }
}
