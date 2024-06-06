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
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Tests\Base\HttpTestCase;
use Zenstruck\Foundry\Proxy;

class ProcedureMapSettingApiTest extends HttpTestCase
{
    protected Procedure|Proxy|null $procedure;

    protected ProcedureSettings|Proxy|null $procedureMapSetting;

    protected User|Proxy|null $user;

    protected JWTManager|Proxy|null $tokenManager;

    public function testProcedureMapSetting(): void
    {
        $this->user = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);
        $this->procedure = ProcedureFactory::createOne();
        $this->procedureMapSetting = ProcedureSettingsFactory::createOne();
        $this->procedureMapSetting->setProcedure($this->procedure->object());
        $this->procedureMapSetting->save();

        $this->tokenManager = $this->getContainer()->get(JWTTokenManagerInterface::class);

        $jwtToken = $this->initializeUser($this->user);
        $headers = $this->getAdditionalHeaders($jwtToken, $this->procedure->object());

        $this->enablePermissions([
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

        $content = $this->client->getResponse()->getContent();

        // Validate a successful response and some content
        self::assertResponseIsSuccessful();
        // self::assertSelectorTextContains('h1', 'registrieren', $this->client->getResponse()->getContent());
    }

    protected function initializeUser(User $user): string
    {
        $token = $this->tokenManager->create($user);
        $userToken = new JWTUserToken($user->getDplanRolesArray(), $user, $token);
        $this->tokenStorage->setToken($userToken);

        return $token;
    }

    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        $headers = [
            'HTTP_X-JWT-Authorization' => "Bearer $jwtToken",
        ];
        if (null !== $procedure) {
            $headers['HTTP_X_DEMOSPLAN_PROCEDURE_ID'] = $procedure->getId();
        }

        return $headers;
    }
}
