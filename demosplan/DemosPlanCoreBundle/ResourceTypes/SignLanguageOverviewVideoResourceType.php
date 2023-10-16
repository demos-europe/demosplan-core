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
use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Video;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Video>
 *
 * @template-implements  CreatableDqlResourceTypeInterface<Video>
 * @template-implements UpdatableDqlResourceTypeInterface<Video>
 * @template-implements DeletableDqlResourceTypeInterface<Video>
 *
 * @property-read End $title
 * @property-read End $description
 * @property-read CustomerResourceType $customerContext
 * @property-read FileResourceType $file
 */
class SignLanguageOverviewVideoResourceType extends DplanResourceType implements CreatableDqlResourceTypeInterface, UpdatableDqlResourceTypeInterface, DeletableDqlResourceTypeInterface
{
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

    public function isDirectlyAccessible(): bool
    {
        return false;
    }

    public function isReferencable(): bool
    {
        return true;
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

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->title)->readable()->initializable(),
            $this->createAttribute($this->description)->readable()->initializable(),
            $this->createToOneRelationship($this->file)->readable()->initializable(),
        ];
    }

    public function isCreatable(): bool
    {
        return true;
    }

    public function createObject(array $properties): ResourceChange
    {
        $customer = $this->currentCustomerService->getCurrentCustomer();

        $video = new Video(
            $this->currentUser->getUser(),
            $customer,
            $properties[$this->file->getAsNamesInDotNotation()],
            $properties[$this->title->getAsNamesInDotNotation()],
            $properties[$this->description->getAsNamesInDotNotation()]
        );

        $resourceChange = new ResourceChange($video, $this, $properties);

        // until the FE supports multiple sign language videos we automatically remove the old one when a new one is created
        /** @var Video $oldVideo */
        foreach ($customer->getSignLanguageOverviewVideos() as $oldVideo) {
            $customer->removeSignLanguageOverviewVideo($oldVideo);
            $resourceChange->addEntityToDelete($oldVideo);
        }

        $customer->addSignLanguageOverviewVideo($video);

        $this->resourceTypeService->validateObject($video);
        $this->resourceTypeService->validateObject($customer);

        $resourceChange->addEntityToPersist($video);
        $resourceChange->addEntityToPersist($customer);

        return $resourceChange;
    }

    /**
     * @param Video $object
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->title, $object->setTitle(...));
        $updater->ifPresent($this->description, $object->setDescription(...));

        $resourceChange = new ResourceChange($object, $this, $properties);
        $resourceChange->addEntityToPersist($object);

        return $resourceChange;
    }

    /**
     * @param Video $updateTarget
     */
    public function getUpdatableProperties(object $updateTarget): array
    {
        $currentCustomerVideoIds = $this->currentCustomerService->getCurrentCustomer()
            ->getSignLanguageOverviewVideos()
            ->map(fn (VideoInterface $video): ?string => $video->getId())
            ->filter(fn (?string $videoId): bool => null !== $videoId);

        if (!$currentCustomerVideoIds->contains($updateTarget->getId())) {
            return [];
        }

        return $this->toProperties(
            $this->title,
            $this->description
        );
    }

    /**
     * @param Video $entity
     */
    public function delete(object $entity): ResourceChange
    {
        $customer = $this->currentCustomerService->getCurrentCustomer();
        $customer->removeSignLanguageOverviewVideo($entity);

        $this->resourceTypeService->validateObject($customer);

        $resourceChange = new ResourceChange($entity, $this, []);
        $resourceChange->addEntityToDelete($entity);
        $resourceChange->addEntityToPersist($customer);

        return $resourceChange;
    }

    public function getRequiredDeletionPermissions(): array
    {
        return ['field_sign_language_overview_video_edit'];
    }
}
