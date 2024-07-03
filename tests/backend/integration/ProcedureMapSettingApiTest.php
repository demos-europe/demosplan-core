<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\integration;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Tests\Base\HttpTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class ProcedureMapSettingApiTest extends HttpTestCase
{
    protected Procedure|Proxy|null $procedure;
    protected ProcedureSettings|Proxy|null $procedureMapSetting;
    protected User|Proxy|null $user;

    public function testProcedureMapSetting(): void
    {
        $this->user = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);
        $this->procedure = ProcedureFactory::createOne();
        $this->procedureMapSetting = ProcedureSettingsFactory::createOne();
        $this->procedureMapSetting->setProcedure($this->procedure->_real());
        $this->procedureMapSetting->_save();

        $this->tokenManager = $this->getContainer()->get(JWTTokenManagerInterface::class);

        $jwtToken = $this->initializeUser($this->user);
        $headers = $this->getAdditionalHeaders($jwtToken, $this->procedure->_real());

        $this->enablePermissions([
            'area_admin_map',
            'area_public_participation',
            'area_admin_initial_map_view_page']);

        $this->client->request(
            'GET',
            '/api/2.0/Procedure/'.$this->procedure->getId().
            '?include=mapSetting&'.
            'fields[Procedure]=mapSetting&'.
            'fields[ProcedureMapSetting]='.
            'boundingBox,'.
            'mapExtent,'.
            'scales,'.
            'informationUrl,'.
            'copyright,'.
            'showOnlyOverlayCategory,'.
            'availableScales,'.
            'coordinate,'.
            'territory,'.
            'defaultBoundingBox,'.
            'defaultMapExtent,'.
            'useGlobalInformationUrl',
            [],
            [],
            $headers
        );

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $decodedContent = json_decode($content, true);

        self::assertEquals('ProcedureMapSetting', $decodedContent['included'][0]['type']);
        self::assertEquals($this->procedureMapSetting->getId(), $decodedContent['included'][0]['id']);
    }
}
