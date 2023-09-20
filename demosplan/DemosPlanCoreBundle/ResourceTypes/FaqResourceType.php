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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqHandler;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<Faq>
 *
 * @template-implements UpdatableDqlResourceTypeInterface<Faq>
 *
 * @property-read End                     $enabled
 * @property-read End                     $title
 * @property-read End                     $invitableInstitutionVisible
 * @property-read End                     $publicVisible
 * @property-read End                     $fpVisible
 * @property-read FaqCategoryResourceType $faqCategory
 */
class FaqResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    public function __construct(private readonly FaqHandler $faqHandler)
    {
    }

    public static function getName(): string
    {
        return 'Faq';
    }

    public function getEntityClass(): string
    {
        return Faq::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_faq');
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return $this->currentUser->hasPermission('area_admin_faq');
    }

    protected function getAccessConditions(): array
    {
        $customer = $this->currentCustomerService->getCurrentCustomer();

        return [$this->conditionFactory->propertyHasValue(
            $customer->getId(),
            $this->faqCategory->customer->id
        )];
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->enabled)->readable(true),
            $this->createAttribute($this->title)->readable(true),
            $this->createAttribute($this->invitableInstitutionVisible)->readable(true, fn(Faq $faq): bool => $faq->hasRoleGroupCode(Role::GPSORG)),
            $this->createAttribute($this->publicVisible)->readable(true, fn(Faq $faq): bool => $faq->hasRoleGroupCode(Role::GGUEST)),
            $this->createAttribute($this->fpVisible)->readable(true, fn(Faq $faq): bool => $faq->hasRoleGroupCode(Role::GLAUTH)),
        ];
    }

    /**
     * @param Faq $faqEntity
     */
    public function updateObject(object $faqEntity, array $properties): ResourceChange
    {
        $this->faqHandler->updateFaqFromProperties($faqEntity, $properties);

        return new ResourceChange($faqEntity, $this, $properties);
    }

    /**
     * @param Faq $updateTarget
     */
    public function getUpdatableProperties(object $updateTarget): array
    {
        if (!$this->currentUser->hasPermission('area_admin_faq')) {
            return [];
        }

        return $this->toProperties(
            $this->invitableInstitutionVisible,
            $this->fpVisible,
            $this->publicVisible,
            $this->enabled,
        );
    }
}
