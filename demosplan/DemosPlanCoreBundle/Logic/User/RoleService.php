<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;

class RoleService extends CoreService
{
    public function __construct(private readonly CurrentUserInterface $currentUser, private readonly GlobalConfigInterface $globalConfig, private readonly RoleRepository $roleRepository)
    {
    }

    /**
     * @return Role|null
     */
    public function getRole(string $roleId)
    {
        return $this->roleRepository->get($roleId);
    }

    /**
     * @param string[] $ids
     *
     * @return Role[]|null
     */
    public function getUserRolesByIds($ids)
    {
        return $this->roleRepository->getUserRolesByIds($ids);
    }

    /**
     * @return Role[]
     */
    public function getRoles(): array
    {
        return $this->roleRepository->findAll();
    }

    /**
     * @param string[] $codes
     *
     * @return Role[]
     */
    public function getUserRolesByCodes(array $codes): ?array
    {
        return $this->roleRepository->getUserRolesByCodes($codes);
    }

    /**
     * @param string[] $codes
     *
     * @return Role[]
     */
    public function getUserRolesByGroupCodes(array $codes): array
    {
        return $this->roleRepository->getUserRolesByGroupCodes($codes);
    }

    /**
     * @param OrgaType[] $acceptedOrgaTypes orgaTypes which have the status {@link OrgaStatusInCustomer::STATUS_ACCEPTED accepted} in the orga-customer-combination
     *
     * @return Role[]
     */
    public function getGivableRoles(array $acceptedOrgaTypes): array
    {
        $allRoles = $this->getRoles();

        $allRoles = collect($allRoles)->flatMap(
            static fn(Role $role) => [$role->getCode() => $role]
        );

        $acceptedOrgaTypeNames = array_map(static fn(OrgaType $orgaType) => $orgaType->getName(), $acceptedOrgaTypes);

        $givableRoles = [];

        try {
            foreach ($this->currentUser->getUser()->getRoles() as $role) {
                if (Role::ORGANISATION_ADMINISTRATION === $role) {
                    $givableRoles[] = $allRoles[Role::ORGANISATION_ADMINISTRATION];
                }
            }

            if (in_array(OrgaType::HEARING_AUTHORITY_AGENCY, $acceptedOrgaTypeNames, true) &&
                $this->currentUser->hasPermission('feature_assign_procedure_hearing_authority_roles')
            ) {
                $givableRoles[] = $allRoles[Role::HEARING_AUTHORITY_ADMIN];
                $givableRoles[] = $allRoles[Role::HEARING_AUTHORITY_WORKER];
            }

            if (in_array(OrgaType::PLANNING_AGENCY, $acceptedOrgaTypeNames, true) &&
                $this->currentUser->hasPermission('feature_assign_procedure_planningoffice_roles')
            ) {
                $givableRoles[] = $allRoles[Role::PRIVATE_PLANNING_AGENCY];
            }

            if (in_array(OrgaType::PUBLIC_AGENCY, $acceptedOrgaTypeNames, true) &&
                $this->currentUser->hasPermission('feature_assign_procedure_invitable_institution_roles')
            ) {
                $givableRoles[] = $allRoles[Role::PUBLIC_AGENCY_COORDINATION];
                $givableRoles[] = $allRoles[Role::PUBLIC_AGENCY_WORKER];
            }

            if (in_array(OrgaType::MUNICIPALITY, $acceptedOrgaTypeNames, true) &&
                $this->currentUser->hasPermission('feature_assign_procedure_fachplaner_roles')
            ) {
                $givableRoles[] = $allRoles[Role::PLANNING_AGENCY_ADMIN];
                $givableRoles[] = $allRoles[Role::PLANNING_SUPPORTING_DEPARTMENT];
                $givableRoles[] = $allRoles[Role::PLANNING_AGENCY_WORKER];
            }

            if ($this->currentUser->hasPermission('feature_assign_system_roles')) {
                $givableRoles[] = $allRoles[Role::PLATFORM_SUPPORT];
                $givableRoles[] = $allRoles[Role::CUSTOMER_MASTER_USER];
                $givableRoles[] = $allRoles[Role::ORGANISATION_ADMINISTRATION];
                $givableRoles[] = $allRoles[Role::CITIZEN];
                $givableRoles[] = $allRoles[Role::BOARD_MODERATOR];
                $givableRoles[] = $allRoles[Role::PROCEDURE_CONTROL_UNIT];
                $givableRoles[] = $allRoles[Role::CONTENT_EDITOR];
                $givableRoles[] = $allRoles[Role::PROCEDURE_DATA_INPUT];
            }
        } catch (UserNotFoundException) {
        }

        $rolesAllowed = $this->globalConfig->getRolesAllowed();

        return collect($givableRoles)
            ->unique()
            ->filter(static fn(Role $role) => in_array($role->getCode(), $rolesAllowed, true))
            ->sortBy(static fn(Role $role) => $role->getName())->all();
    }
}
