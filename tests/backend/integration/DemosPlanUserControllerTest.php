<?php
declare(strict_types=1);

namespace backend\integration;

use Tests\Base\HttpTestCase;

class DemosPlanUserControllerTest extends HttpTestCase
{

    public function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
    }

    public function testUserRegister()
    {
        $this->enablePermissions(['feature_citizen_registration']);
        $crawler = $this->client->request('GET', '/user/register');

        // Validate a successful response and some content
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'registrieren', $this->client->getResponse()->getContent());
    }
}
