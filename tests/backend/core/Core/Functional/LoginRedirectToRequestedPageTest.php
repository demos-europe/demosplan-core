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

use demosplan\DemosPlanCoreBundle\Cookie\PreviousRouteCookie;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseCookieValueSame;
use Tests\Base\FunctionalTestCase;

/**
 * A guest who opens a page that requires a login is sent to the start page. After logging in they
 * must be forwarded to the page they originally requested, not to their default start page.
 */
class LoginRedirectToRequestedPageTest extends FunctionalTestCase
{
    private const SCHEDULED_EXPORT_PATH = '/xlsx-export/geplant/45f445bd-0a45-4d09-9050-76137337718d';

    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();

        static::ensureKernelShutdown();
        // createClient() cannot be used while the kernel is booted
        $this->client = static::createClient();
    }

    public function testGuestRequestingProtectedPageIsRememberedForAfterLogin(): void
    {
        $this->client->request('GET', self::SCHEDULED_EXPORT_PATH);

        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirection());
        self::assertThat($response, new ResponseCookieValueSame(PreviousRouteCookie::NAME, self::SCHEDULED_EXPORT_PATH));
    }

    public function testUserIsForwardedToRequestedPageAfterLogin(): void
    {
        $this->client->request('GET', self::SCHEDULED_EXPORT_PATH);

        $this->client->loginUser($this->getUserReference(LoadUserData::TEST_USER_FP_ONLY), 'main');
        $this->client->request('GET', '/loggedin');

        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect(self::SCHEDULED_EXPORT_PATH));
    }
}
