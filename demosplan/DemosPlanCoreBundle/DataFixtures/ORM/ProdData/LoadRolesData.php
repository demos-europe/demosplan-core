<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Doctrine\Persistence\ObjectManager;

class LoadRolesData extends ProdFixture
{
    public function load(ObjectManager $manager): void
    {
        $roles = [
            [
                'code'      => Role::PLANNING_AGENCY_ADMIN,
                'name'      => 'Fachplaner-Admin',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => Role::PLANNING_AGENCY_WORKER,
                'name'      => 'Fachplaner-Sachbearbeiter',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => Role::HEARING_AUTHORITY_ADMIN,
                'name'      => 'Anhörungsbehörde-Admin',
                'groupCode' => 'GHEAUT',
                'groupName' => 'Anhörungsbehörde',
            ],
            [
                'code'      => Role::HEARING_AUTHORITY_WORKER,
                'name'      => 'Anhörungsbehörde-Sachbearbeiter',
                'groupCode' => 'GHEAUT',
                'groupName' => 'Anhörungsbehörde',
            ],
            [
                'code'      => Role::PRIVATE_PLANNING_AGENCY,
                'name'      => 'Fachplaner-Planungsbüro',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => Role::PLANNING_SUPPORTING_DEPARTMENT,
                'name'      => 'Fachplaner-Fachbehörde',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => Role::PUBLIC_AGENCY_COORDINATION,
                'name'      => 'TöB-Koordinator',
                'groupCode' => Role::GPSORG,
                'groupName' => 'Institution',
            ],
            [
                'code'      => Role::PUBLIC_AGENCY_WORKER,
                'name'      => 'TöB-Sachbearbeiter',
                'groupCode' => Role::GPSORG,
                'groupName' => 'Institution',
            ],
            [
                'code'      => Role::CITIZEN,
                'name'      => 'Bürger',
                'groupCode' => Role::GCITIZ,
                'groupName' => 'Bürgergruppe',
            ],
            [
                'code'      => Role::PROSPECT,
                'name'      => 'Interessent',
                'groupCode' => Role::GINTPA,
                'groupName' => 'Interessent',
            ],
            [
                'code'      => Role::GUEST,
                'name'      => 'Gast',
                'groupCode' => Role::GGUEST,
                'groupName' => 'Gast',
            ],
            [
                'code'      => Role::PLATFORM_SUPPORT,
                'name'      => 'Verfahrenssupport',
                'groupCode' => Role::GTSUPP,
                'groupName' => 'Verfahrenssupport',
            ],
            [
                'code'      => Role::ORGANISATION_ADMINISTRATION,
                'name'      => 'Fachplaner-Masteruser',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => Role::BOARD_MODERATOR,
                'name'      => 'Moderator',
                'groupCode' => Role::GMODER,
                'groupName' => 'Moderator',
            ],
            [
                'code'      => Role::PLANNING_AGENCY_ADMIN,
                'name'      => 'Fachplaner-Admin',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            [
                'code'      => Role::CONTENT_EDITOR,
                'name'      => 'Redakteur',
                'groupCode' => Role::GTEDIT,
                'groupName' => 'Redakteur',
            ],
            [
                'code'      => Role::PROCEDURE_CONTROL_UNIT,
                'name'      => 'Fachliche Leitstelle',
                'groupCode' => Role::GFALST,
                'groupName' => 'Fachliche Leitstelle',
            ],
            [
                'code'      => Role::PROCEDURE_DATA_INPUT,
                'name'      => 'Datenerfassung',
                'groupCode' => Role::GDATA,
                'groupName' => 'Datenerfassung',
            ],
            [
                'code'      => Role::CUSTOMER_MASTER_USER,
                'name'      => 'Mandanten-Masteruser',
                'groupCode' => Role::CUSTOMERMASTERUSERGROUP,
                'groupName' => 'Mandant',
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
