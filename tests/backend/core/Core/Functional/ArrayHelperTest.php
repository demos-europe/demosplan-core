<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ArrayHelper;
use Psr\Log\NullLogger;
use Tests\Base\FunctionalTestCase;

class ArrayHelperTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new ArrayHelper(new NullLogger());
    }

    public function testOrderArrayByIds(): void
    {
        $roles = [
            0 => [
                'ident'     => '197f2e9a-0968-11e1-9d8e-1c64a3042bca',
                'code'      => Role::PLANNING_AGENCY_ADMIN,
                'name'      => 'Fachplaner-Admin',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            1 => [
                'ident'     => '197f3014-0968-11e1-9d8e-1c64a3042bca',
                'code'      => Role::PLANNING_AGENCY_WORKER,
                'name'      => 'Fachplaner-Sachbearbeiter',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            2 => [
                'ident'     => '207f3e9a-0968-11e1-9d8e-1c64a3042bca',
                'code'      => Role::PRIVATE_PLANNING_AGENCY,
                'name'      => 'Fachplaner-Planungsbüro',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
            3 => [
                'ident'     => '41ee1a0a-0968-11e1-9d8e-1c64a3042bca',
                'code'      => Role::PUBLIC_AGENCY_COORDINATION,
                'name'      => 'TöB-Koordinator',
                'groupCode' => Role::GPSORG,
                'groupName' => 'TöB',
            ],
            4 => [
                'ident'     => '41ee1b96-0968-11e1-9d8e-1c64a3042bca',
                'code'      => Role::PUBLIC_AGENCY_WORKER,
                'name'      => 'TöB-Sachbearbeiter',
                'groupCode' => Role::GPSORG,
                'groupName' => 'TöB',
            ],
            5 => [
                'ident'     => 'c2020aa2-0967-11e1-9d8e-1c64a3042bca',
                'code'      => Role::ORGANISATION_ADMINISTRATION,
                'name'      => 'Fachplaner-Masteruser',
                'groupCode' => Role::GLAUTH,
                'groupName' => 'Kommune',
            ],
        ];

        $result = [
            0 => [
                'ident'       => '01d1a703-9ac5-47fb-b75b-eb7f04b57bc2',
                'pId'         => 'd777cb43-c30a-4eaa-a540-fb8268e98354',
                'title'       => 'asdf',
                'description' => '<p>asdf</p>',
                'text'        => '<p>asdf</p>',
                'picture'     => 'BOB-SH_Logo.jpg:89c42e9b-9aaa-11e5-9c91-005056ae0004:6169:image/jpeg',
                'pictitle'    => '',
                'pdf'         => '',
                'pdftitle'    => '',
                'enabled'     => true,
                'deleted'     => false,
                'createDate'  => 1449248821000,
                'modifyDate'  => 1449250649000,
                'deleteDate'  => 1450689893000,
                'roles'       => $roles,
            ],
            1 => [
                'ident'       => '122cce98-e7a4-41ac-8dcb-2abdab346d36',
                'pId'         => 'd777cb43-c30a-4eaa-a540-fb8268e98354',
                'title'       => 'blumentest',
                'description' => '<p>blume, blume</p>',
                'text'        => '',
                'picture'     => 'Chrysanthemum.jpg:74101334-00db-4066-8d50-521da955922e:879394:image/jpeg',
                'pictitle'    => 'blume',
                'pdf'         => '',
                'pdftitle'    => '',
                'enabled'     => true,
                'deleted'     => false,
                'createDate'  => 1441796520000,
                'modifyDate'  => 1441796660000,
                'deleteDate'  => 1450386792000,
                'roles'       => [],
            ],
            2 => [
                'ident'       => '867b772e-fefb-40a6-b0be-b8d01a784e53',
                'pId'         => 'd777cb43-c30a-4eaa-a540-fb8268e98354',
                'title'       => 'Laufzeit des Verfahrens',
                'description' => '<p>Das Beteiligungsverfahren läuft vom 15.7. bis 14.9.2015</p>',
                'text'        => '',
                'picture'     => '',
                'pictitle'    => '',
                'pdf'         => '',
                'pdftitle'    => '',
                'enabled'     => true,
                'deleted'     => false,
                'createDate'  => 1436370350000,
                'modifyDate'  => 1436370373000,
                'deleteDate'  => 1450689893000,
                'roles'       => [
                    0 => [
                        'ident'     => '41ee1a0a-0968-11e1-9d8e-1c64a3042bca',
                        'code'      => Role::PUBLIC_AGENCY_COORDINATION,
                        'name'      => 'TöB-Koordinator',
                        'groupCode' => Role::GPSORG,
                        'groupName' => 'TöB',
                    ],
                    1 => [
                        'ident'     => '41ee1b96-0968-11e1-9d8e-1c64a3042bca',
                        'code'      => Role::PUBLIC_AGENCY_WORKER,
                        'name'      => 'TöB-Sachbearbeiter',
                        'groupCode' => Role::GPSORG,
                        'groupName' => 'TöB',
                    ],
                    2 => [
                        'ident'     => '71a2ebbc-0968-11e1-9d8e-1c64a3042bca',
                        'code'      => Role::GUEST,
                        'name'      => 'Gast',
                        'groupCode' => Role::GGUEST,
                        'groupName' => 'Gast',
                    ],
                ],
            ],
            3 => [
                'ident'       => 'e60dae61-916c-4b73-8b01-0da6cd00f38f',
                'pId'         => 'd777cb43-c30a-4eaa-a540-fb8268e98354',
                'title'       => 'test2',
                'description' => '<p>testtext</p>',
                'text'        => '',
                'picture'     => 'Penguins.jpg:fa2584d1-434f-4dbb-9372-f8c1312e5a99:777835:image/jpeg',
                'pictitle'    => 'pingiune',
                'pdf'         => 'Chrysanthemum.jpg:333b0e21-c548-450e-a7c1-12ba0d9037e3:879394:image/jpeg',
                'pdftitle'    => 'blumen',
                'enabled'     => true,
                'deleted'     => false,
                'createDate'  => 1441107566000,
                'modifyDate'  => 1450781413000,
                'deleteDate'  => 1450386792000,
                'roles'       => $roles,
            ],
        ];

        $manualOrder = [
            0 => '122cce98-e7a4-41ac-8dcb-2abdab346d36',
            1 => 'e60dae61-916c-4b73-8b01-0da6cd00f38f',
            2 => '867b772e-fefb-40a6-b0be-b8d01a784e53',
            3 => '01d1a703-9ac5-47fb-b75b-eb7f04b57bc2',
        ];
        $orderedList = $this->sut->orderArrayByIds($manualOrder, $result);
        static::assertCount(4, $orderedList);
        static::assertEquals($manualOrder[0], $orderedList[0]['ident']);
        static::assertEquals($manualOrder[1], $orderedList[1]['ident']);
        static::assertEquals($manualOrder[2], $orderedList[2]['ident']);
        static::assertEquals($manualOrder[3], $orderedList[3]['ident']);

        // incorrect doubled ident 1 & 3
        $manualOrder = [
            0 => '122cce98-e7a4-41ac-8dcb-2abdab346d36',
            1 => 'e60dae61-916c-4b73-8b01-0da6cd00f38f',
            2 => '867b772e-fefb-40a6-b0be-b8d01a784e53',
            3 => 'e60dae61-916c-4b73-8b01-0da6cd00f38f',
        ];
        $orderedList = $this->sut->orderArrayByIds($manualOrder, $result);
        static::assertCount(4, $orderedList);
        static::assertEquals($manualOrder[0], $orderedList[0]['ident']);
        static::assertEquals($manualOrder[1], $orderedList[1]['ident']);
        static::assertEquals($manualOrder[2], $orderedList[2]['ident']);
        // Entry which is not in the manualOrder array should be appended
        static::assertEquals('01d1a703-9ac5-47fb-b75b-eb7f04b57bc2', $orderedList[3]['ident']);
    }

    public function testOrderArrayByIdsFail(): void
    {
        $arrayToOrder = [['key']];
        $result = $this->sut->orderArrayByIds([1, 2, 3], $arrayToOrder, 'notexistant');
        static::assertEquals($arrayToOrder, $result);
    }
}
