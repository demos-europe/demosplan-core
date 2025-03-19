<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Doctrine\Persistence\ObjectManager;

class LoadRolesData extends ProdFixture
{
    public function load(ObjectManager $manager): void
    {
        $roles = [
            [
                'code'      => RoleInterface::PLANNING_AGENCY_ADMIN,
                'name'      => 'Fachplaner-Admin',
                'groupCode' => RoleInterface::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => RoleInterface::PLANNING_AGENCY_WORKER,
                'name'      => 'Fachplaner-Sachbearbeiter',
                'groupCode' => RoleInterface::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => RoleInterface::HEARING_AUTHORITY_ADMIN,
                'name'      => 'Anhörungsbehörde-Admin',
                'groupCode' => 'GHEAUT',
                'groupName' => 'Anhörungsbehörde',
            ],
            [
                'code'      => RoleInterface::HEARING_AUTHORITY_WORKER,
                'name'      => 'Anhörungsbehörde-Sachbearbeiter',
                'groupCode' => 'GHEAUT',
                'groupName' => 'Anhörungsbehörde',
            ],
            [
                'code'      => RoleInterface::PRIVATE_PLANNING_AGENCY,
                'name'      => 'Fachplaner-Planungsbüro',
                'groupCode' => RoleInterface::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => RoleInterface::PLANNING_SUPPORTING_DEPARTMENT,
                'name'      => 'Fachplaner-Fachbehörde',
                'groupCode' => RoleInterface::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => RoleInterface::PUBLIC_AGENCY_COORDINATION,
                'name'      => 'TöB-Koordinator',
                'groupCode' => RoleInterface::GPSORG,
                'groupName' => 'Institution',
            ],
            [
                'code'      => RoleInterface::PUBLIC_AGENCY_WORKER,
                'name'      => 'TöB-Sachbearbeiter',
                'groupCode' => RoleInterface::GPSORG,
                'groupName' => 'Institution',
            ],
            [
                'code'      => RoleInterface::CITIZEN,
                'name'      => 'Bürger',
                'groupCode' => RoleInterface::GCITIZ,
                'groupName' => 'Bürgergruppe',
            ],
            [
                'code'      => RoleInterface::PROSPECT,
                'name'      => 'Interessent',
                'groupCode' => RoleInterface::GINTPA,
                'groupName' => 'Interessent',
            ],
            [
                'code'      => RoleInterface::GUEST,
                'name'      => 'Gast',
                'groupCode' => RoleInterface::GGUEST,
                'groupName' => 'Gast',
            ],
            [
                'code'      => RoleInterface::PLATFORM_SUPPORT,
                'name'      => 'Verfahrenssupport',
                'groupCode' => RoleInterface::GTSUPP,
                'groupName' => 'Verfahrenssupport',
            ],
            [
                'code'      => RoleInterface::ORGANISATION_ADMINISTRATION,
                'name'      => 'Fachplaner-Masteruser',
                'groupCode' => RoleInterface::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => RoleInterface::BOARD_MODERATOR,
                'name'      => 'Moderator',
                'groupCode' => RoleInterface::GMODER,
                'groupName' => 'Moderator',
            ],
            [
                'code'      => RoleInterface::CONTENT_EDITOR,
                'name'      => 'Redakteur',
                'groupCode' => RoleInterface::GTEDIT,
                'groupName' => 'Redakteur',
            ],
            [
                'code'      => RoleInterface::PROCEDURE_CONTROL_UNIT,
                'name'      => 'Fachliche Leitstelle',
                'groupCode' => RoleInterface::GFALST,
                'groupName' => 'Fachliche Leitstelle',
            ],
            [
                'code'      => RoleInterface::PROCEDURE_DATA_INPUT,
                'name'      => 'Datenerfassung',
                'groupCode' => RoleInterface::GDATA,
                'groupName' => 'Datenerfassung',
            ],
            [
                'code'      => RoleInterface::CUSTOMER_MASTER_USER,
                'name'      => 'Mandanten-Masteruser',
                'groupCode' => RoleInterface::CUSTOMERMASTERUSERGROUP,
                'groupName' => 'Mandant',
            ],
            [
                'code'      => RoleInterface::API_AI_COMMUNICATOR,
                'name'      => 'AI API Communicator',
                'groupCode' => RoleInterface::GAICOM,
                'groupName' => 'Data',
            ],
        ];

        foreach ($roles as $roleDefinition) {
            $role = new Role();
            $role->setCode($roleDefinition['code']);
            $role->setName($roleDefinition['name']);
            $role->setGroupCode($roleDefinition['groupCode']);
            $role->setGroupName($roleDefinition['groupName']);
            $manager->persist($role);

            $this->setReference('role_'.$role->getCode(), $role);
        }

        $manager->flush();
    }
}
