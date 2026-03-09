<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakDataMapper;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;

class PrivateCitizenMapper
{
    public function __construct(
        private readonly OrgaRepository $orgaRepository,
    ) {
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

    public function getCitizenOrga(): Orga
    {
        $orga = $this->orgaRepository->findOneBy(['id' => UserInterface::ANONYMOUS_USER_ORGA_ID]);
        if (null === $orga) {
            throw new \RuntimeException(sprintf('Orga with id %d not found', UserInterface::ANONYMOUS_USER_ORGA_ID));
        }

        return $orga;
    }
}
