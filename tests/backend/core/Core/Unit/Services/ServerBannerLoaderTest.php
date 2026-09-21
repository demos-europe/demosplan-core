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

    private ?bool $bannerExistedBeforeTest = false;

    private ?string $preexistingBannerContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new ServerBannerLoader();
        $this->bannerPath = DemosPlanPath::getRootPath('SERVER_BANNER.md');
        $this->bannerExistedBeforeTest = file_exists($this->bannerPath);

        // Preserve any banner file that may already exist on disk so the test
        // never loses real content. Its ownership/permissions are left untouched
        // because the file itself is never deleted, only its contents overwritten.
        if ($this->bannerExistedBeforeTest) {
            $this->preexistingBannerContent = file_get_contents($this->bannerPath);
        }
    }

    protected function tearDown(): void
    {
        if ($this->bannerExistedBeforeTest) {
            file_put_contents($this->bannerPath, $this->preexistingBannerContent);
        } elseif (file_exists($this->bannerPath)) {
            unlink($this->bannerPath);
        }

        $this->sut = null;
        $this->bannerPath = null;
        $this->bannerExistedBeforeTest = false;
        $this->preexistingBannerContent = null;

        parent::tearDown();
    }

    public function testGetServerBannerReturnsNullWhenFileDoesNotExist(): void
    {
        if ($this->bannerExistedBeforeTest) {
            static::markTestSkipped('A SERVER_BANNER.md already exists on disk; skipping to leave it untouched.');
        }

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
