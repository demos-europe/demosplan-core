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

use DemosEurope\DemosplanAddon\Contracts\Entities\VideoInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseVideoResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Video;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\VideoRepository;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathException;
use EDT\Wrapping\CreationDataInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\ToOneRelationshipConstructorBehavior;

/**
 * @template-extends DplanResourceType<Video>
 *
 * @property-read End $title
 * @property-read End $description
 * @property-read CustomerResourceType $customerContext
 * @property-read FileResourceType $file
 */
class SignLanguageOverviewVideoResourceType extends DplanResourceType
{
    public function __construct(
        protected readonly VideoRepository $videoRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'SignLanguageOverviewVideo';
    }

    public function getEntityClass(): string
    {
        return Video::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('field_sign_language_overview_video_edit');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('field_sign_language_overview_video_edit');
    }

    protected function getAccessConditions(): array
    {
        return [
            // for now the access to SignLanguageOverviewVideos is limited to the ones uploaded
            // in the current customer
            $this->conditionFactory->propertyHasValue(
                $this->currentCustomerService->getCurrentCustomer()->getId(),
                $this->customerContext->id
            ),
            // to be an actual 'SignLanguageOverviewVideo' the entity must be set as one of the
            // sign language videos of the customer it was uploaded in
            $this->conditionFactory->propertiesEqual(
                $this->id->getAsNames(),
                $this->customerContext->signLanguageOverviewVideos->id->getAsNames()
            ),
        ];
    }

    /**
     * @throws PathException
     */
    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        // ensure update of title and description is only allowed
        // if the video has one of the following IDs
        $currentCustomerVideoIds = $this->currentCustomerService->getCurrentCustomer()
            ->getSignLanguageOverviewVideos()
            ->map(fn (VideoInterface $video): ?string => $video->getId())
            ->filter(fn (?string $videoId): bool => null !== $videoId)
            ->getValues();
        $customerCondition = [] === $currentCustomerVideoIds
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasAnyOfValues($currentCustomerVideoIds, Paths::video()->id);

        $configBuilder = $this->getConfig(BaseVideoResourceConfigBuilder::class);

        $configBuilder->id->readable();

        $configBuilder->title->readable()->updatable([$customerCondition])->addConstructorBehavior(
            AttributeConstructorBehavior::createFactory(null, OptionalField::NO, null)
        );

        $configBuilder->description->readable()->updatable([$customerCondition])->addConstructorBehavior(
            AttributeConstructorBehavior::createFactory(null, OptionalField::NO, null)
        );

        $configBuilder->file
            ->setRelationshipType($this->resourceTypeStore->getFileResourceType())
            ->readable()->addConstructorBehavior(
                ToOneRelationshipConstructorBehavior::createFactory(null, [], null, OptionalField::NO)
            );
        $configBuilder->addConstructorBehavior(new FixedConstructorBehavior(
            Paths::video()->uploader->getAsNamesInDotNotation(),
            fn (CreationDataInterface $entityData): array => [$this->currentUser->getUser(), []]
        ));
        $configBuilder->addConstructorBehavior(new FixedConstructorBehavior(
            Paths::video()->customerContext->getAsNamesInDotNotation(),
            fn (CreationDataInterface $entityData): array => [$this->currentCustomerService->getCurrentCustomer(), []]
        ));
        $configBuilder->addPostConstructorBehavior(new FixedSetBehavior(
            function (Video $newVideo, EntityDataInterface $entityData): array {
                $customer = $this->currentCustomerService->getCurrentCustomer();

                // until the FE supports multiple sign language videos we automatically remove the old one when a new one is created
                $oldVideos = $customer->getSignLanguageOverviewVideos();
                foreach ($oldVideos as $oldVideo) {
                    $customer->removeSignLanguageOverviewVideo($oldVideo);
                }
                $this->videoRepository->persistAndDelete([], $oldVideos->getValues());

                $customer->addSignLanguageOverviewVideo($newVideo);
                $this->resourceTypeService->validateObject($newVideo);
                $this->resourceTypeService->validateObject($customer);
                $this->videoRepository->persistEntities([$newVideo]);

                return [];
            }
        ));

        return $configBuilder;
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('field_sign_language_overview_video_edit');
    }

    public function deleteEntity(string $entityIdentifier): void
    {
        $this->getTransactionService()->executeAndFlushInTransaction(
            function () use ($entityIdentifier): void {
                $entity = $this->getEntity($entityIdentifier);
                $customer = $this->currentCustomerService->getCurrentCustomer();
                $customer->removeSignLanguageOverviewVideo($entity);
                $this->resourceTypeService->validateObject($customer);

                parent::deleteEntity($entityIdentifier);
            }
        );
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('field_sign_language_overview_video_edit');
    }
}
