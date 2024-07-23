<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Handler;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use stdClass;
use Symfony\Component\HttpFoundation\FileBag;
use Tests\Base\UnitTestCase;

/**
 * Teste CoreHandler
 * Class CoreHandlerTest.
 *
 * @group UnitTest
 */
class CoreHandlerTest extends UnitTestCase
{
    /**
     * @var CoreHandler
     */
    protected $coreHandler;

    protected $uploadDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->coreHandler = new CoreHandler(self::$container->get(MessageBagInterface::class));

        if (is_dir($this->getUploadDir().'/coreHandlerUpload_test/')) {
            $this->deleteTestDir();
        }

        mkdir($this->getUploadDir().'/coreHandlerUpload_test', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTestDir();
        parent::tearDown();
    }

    protected function deleteTestDir()
    {
        foreach (glob($this->getUploadDir().'/coreHandlerUpload_test/*') as $file) {
            unlink($file);
        }

        rmdir($this->getUploadDir().'/coreHandlerUpload_test');
    }

    public function testSetGetRequest()
    {
        $request = ['one' => 'eins', 2 => 'two', 3 => 3];

        $this->coreHandler->setRequestValues($request);

        static::assertEquals($request, $this->coreHandler->getRequestValues());
    }

    public function testSetGetFiles()
    {
        $fileBag = new FileBag();
        $this->coreHandler->setSymfonyFileBag($fileBag);

        static::assertEquals($fileBag, $this->coreHandler->getSymfonyFileBag());
    }

    protected function createTempFile()
    {
        return tempnam($this->getUploadDir().'/coreHandlerUpload_test', 'CoreHandlerUploadTest');
    }

    public function testSetGetConfigService()
    {
        self::markSkippedForCIIntervention();

        $configService = new stdClass();
        $this->coreHandler->setDemosplanConfig($configService);
        static::assertEquals($configService, $this->coreHandler->getDemosplanConfig());
    }

    protected function getUploadDir()
    {
        if (!is_null($this->uploadDir)) {
            return $this->uploadDir;
        }

        $this->uploadDir = sys_get_temp_dir();

        return $this->uploadDir;
    }
}
