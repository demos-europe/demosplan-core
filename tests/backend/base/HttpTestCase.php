<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

use demosplan\DemosPlanCoreBundle\EventListener\SetHttpTestPermissionsListener;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class HttpTestCase extends FunctionalTestCase
{
    protected ?KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        // the createClient() method cannot be used when kernel is booted
        static::ensureKernelShutdown();
        $this->client = static::createClient();
    }

    /**
     * Override method to enable permissions via HTTP server param that
     * evaluated in {@see SetHttpTestPermissionsListener}.
     */
    protected function enablePermissions(array $permissionsToEnable): void
    {
        $this->client->setServerParameter(SetHttpTestPermissionsListener::X_DPLAN_TEST_PERMISSIONS, implode(',', $permissionsToEnable));
    }


}
