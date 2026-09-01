<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Services;

use demosplan\DemosPlanCoreBundle\Services\ServerBannerLoader;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Tests\Base\UnitTestCase;

/**
 * @group UnitTest
 */
class ServerBannerLoaderTest extends UnitTestCase
{
    private ?ServerBannerLoader $sut = null;

    private ?string $bannerPath = null;

    private ?string $preexistingBannerContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new ServerBannerLoader();
        $this->bannerPath = DemosPlanPath::getRootPath('SERVER_BANNER.md');

        // Preserve any banner file that may already exist on disk so the test
        // never clobbers real state, then start from a clean slate.
        if (is_readable($this->bannerPath)) {
            $this->preexistingBannerContent = file_get_contents($this->bannerPath);
            unlink($this->bannerPath);
        }
    }

    protected function tearDown(): void
    {
        if (null !== $this->bannerPath && file_exists($this->bannerPath)) {
            unlink($this->bannerPath);
        }

        if (null !== $this->preexistingBannerContent) {
            file_put_contents($this->bannerPath, $this->preexistingBannerContent);
        }

        $this->sut = null;
        $this->bannerPath = null;
        $this->preexistingBannerContent = null;

        parent::tearDown();
    }

    public function testGetServerBannerReturnsNullWhenFileDoesNotExist(): void
    {
        static::assertFileDoesNotExist($this->bannerPath);

        static::assertNull($this->sut->getServerBanner());
    }

    public function testGetServerBannerReturnsNullWhenFileIsEmpty(): void
    {
        file_put_contents($this->bannerPath, '');

        static::assertNull($this->sut->getServerBanner());
    }

    public function testGetServerBannerReturnsParsedMarkdownAsHtml(): void
    {
        file_put_contents($this->bannerPath, '**Maintenance** on Sunday');

        $banner = $this->sut->getServerBanner();

        static::assertNotNull($banner);
        static::assertStringContainsString('<strong>Maintenance</strong>', $banner);
    }

    public function testGetServerBannerWrapsPlainTextInParagraph(): void
    {
        file_put_contents($this->bannerPath, 'System is under maintenance.');

        $banner = $this->sut->getServerBanner();

        static::assertSame('<p>System is under maintenance.</p>', $banner);
    }
}
