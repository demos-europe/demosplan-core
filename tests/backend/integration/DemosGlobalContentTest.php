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

use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Tests\Base\AbstractApiTest;

class DemosGlobalContentTest extends AbstractApiTest
{
    public function testUserRegister(): void
    {
        $this->enablePermissions(['area_admin_globalnews']);
        $user = $this->loginTestUser();
        $currentUserService = $this->getContainer()->get(CurrentUserService::class);
        $currentUserService->setUser($user);

        $this->client->setServerParameter('TEST_USER', 'TEST_USER');
        // $response = $this->sendRequest('/news/verwalten','GET', $user, null);
        $this->client->request('GET', '/news/verwalten');
        $content = $this->client->getResponse()->getContent();
        // Validate a successful response and some content
        self::assertResponseIsSuccessful();
        // self::assertSelectorTextContains('h1', 'registrieren', $this->client->getResponse()->getContent());
    }

    protected function getServerParameters(): array
    {
        return [];
    }
}
