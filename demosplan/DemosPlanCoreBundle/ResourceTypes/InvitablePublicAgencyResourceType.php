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

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathException;

/**
 * @template-extends DplanResourceType<Orga>
 *
 * @property-read End                              $legalName
 * @property-read End                              $name
 * @property-read End                              $competenceDescription
 * @property-read End                              $deleted
 * @property-read End                              $showlist
 * @property-read UserResourceType                 $users
 * @property-read OrgaStatusInCustomerResourceType $statusInCustomers
 */
class InvitablePublicAgencyResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'InvitableToeb';
    }

    public function getEntityClass(): string
    {
        return Orga::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_main_procedures',
            'area_admin_invitable_institution'
        );
    }

    /**
     * @throws PathException
     * @throws CustomerNotFoundException
     */
    protected function getAccessConditions(): array
    {
        $customer = $this->currentCustomerService->getCurrentCustomer();
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }
        $invitedOrgaIds = $procedure->getOrganisation()->map(
            static fn (Orga $orga): string => $orga->getId()
        );

        return [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyHasValue(true, $this->showlist),
            $this->conditionFactory->propertyHasValue(
                Role::GPSORG,
                $this->users->roleInCustomers->role->groupCode
            ),
            $this->conditionFactory->propertyHasValue(
                OrgaType::PUBLIC_AGENCY,
                $this->statusInCustomers->orgaType->name
            ),
            $this->conditionFactory->propertyHasValue(
                $customer->getId(),
                $this->statusInCustomers->customer->id
            ),
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomer::STATUS_ACCEPTED,
                $this->statusInCustomers->status
            ),
            // avoid already invited organisations
            $this->conditionFactory->propertyHasNotAnyOfValues(
                $invitedOrgaIds->toArray(),
                $this->id
            ),
        ];
    }

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyAscending($this->name),
        ];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->legalName)->readable(true)->aliasedPath($this->name),
            $this->createAttribute($this->competenceDescription)->readable(
                true,
                static function (Orga $orga): ?string {
                    $competenceDescription = $orga->getCompetence();
                    if ('-' === $competenceDescription || '' === $competenceDescription) {
                        return null;
                    }

                    return $competenceDescription;
                }
            ),
        ];
    }
}
