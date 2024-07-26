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

use Tests\Base\AbstractApiTest;

class DemosPlanUserControllerTest extends AbstractApiTest
{
    public function testUserRegister(): void
    {
        $this->enablePermissions(['feature_citizen_registration']);
        $this->client->request('GET', '/user/register');

        // Validate a successful response and some content
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'registrieren', $this->client->getResponse()->getContent());
    }

    protected function getServerParameters(): array
    {
        return [];
    }
}
