<?php

namespace demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;

class PrivateCitizenMapper
{

    private function __construct(
        private readonly OrgaRepository $orgaRepository
    )
    {

    }

    /**
     * @param array<int, Role> $desiredRoles
     */
    public function isUserCitizen(array $desiredRoles): bool
    {
        foreach ($desiredRoles as $role) {
            if (RoleInterface::CITIZEN === $role->getCode()) {
                return true;
            }
        }

        return false;
    }

    public function getCitizenOrga(): ?Orga
    {
        return $this->orgaRepository->findOneBy(['id' => User::ANONYMOUS_USER_ORGA_ID]);
    }


}
