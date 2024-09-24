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

use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Faq>
 *
 * @property-read End                     $enabled
 * @property-read End                     $title
 * @property-read End                     $invitableInstitutionVisible
 * @property-read End                     $publicVisible
 * @property-read End                     $fpVisible
 * @property-read FaqCategoryResourceType $faqCategory
 */
class FaqResourceType extends DplanResourceType
{
    public function __construct(private readonly RoleRepository $roleRepository)
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

    public function isUpdateAllowed(): bool
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
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->enabled)->readable(true)->updatable(),
            $this->createAttribute($this->title)->readable(true),
            $this->createAttribute($this->invitableInstitutionVisible)
                ->readable(true, fn (Faq $faq): bool => $faq->hasRoleGroupCode(Role::GPSORG))
                ->updatable([], function (Faq $faqEntity, mixed $newValue): array {
                    $this->buildAddOrRemoveRoleGroupFunction($faqEntity, Role::GPSORG)($newValue);

                    return [];
                }),
            $this->createAttribute($this->publicVisible)
                ->readable(true, fn (Faq $faq): bool => $faq->hasRoleGroupCode(Role::GGUEST))
                ->updatable([], function (Faq $faqEntity, mixed $newValue): array {
                    $this->buildAddOrRemoveRoleGroupFunction($faqEntity, Role::GGUEST)($newValue);

                    return [];
                }),
            $this->createAttribute($this->fpVisible)
                ->readable(true, fn (Faq $faq): bool => $faq->hasRoleGroupCode(Role::GLAUTH))
                ->updatable([], function (Faq $faqEntity, mixed $newValue): array {
                    $this->buildAddOrRemoveRoleGroupFunction($faqEntity, Role::GLAUTH)($newValue);

                    return [];
                }),
        ];
    }

    /**
     * Returns a callable that accepts a boolean.
     *
     * If given `true` the callable will add the {@link Role}s
     * corresponding to the `$groupCode` which are not already present to the `$faqEntity`.
     *
     * If given `false` the callable will remove the {@link Role}s
     * corresponding to the `$groupCode` which are present from the `$faqEntity`.
     *
     * @return callable(bool):void
     */
    private function buildAddOrRemoveRoleGroupFunction(Faq $faqEntity, string $groupCode): callable
    {
        return function (bool $setVisible) use ($faqEntity, $groupCode): void {
            $groupRoles = $this->roleRepository->findBy([
                'groupCode' => $groupCode,
            ]);
            $currentRoles = $faqEntity->getRoles();
            foreach ($groupRoles as $role) {
                $present = $currentRoles->exists(static fn (int $index, Role $currentRole): bool => $currentRole->getId() === $role->getId());
                if ($setVisible) {
                    if (!$present) {
                        $currentRoles->add($role);
                    }
                } elseif ($present) {
                    $currentRoles = $currentRoles->filter(static fn (Role $currentRole): bool => $currentRole->getId() !== $role->getId());
                }
            }
            $faqEntity->setRoles($currentRoles->getValues());
        };
    }
}
