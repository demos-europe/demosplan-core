<?php
/* TODO REMOVE THIS TEST */
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
    protected $messageBag;

    public function setUp(): void
    {
        parent::setUp();
        $this->coreHandler = new CoreHandler(self::getContainer()->get(MessageBagInterface::class));
        $this->messageBag = self::getContainer()->get(MessageBagInterface::class);

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
            // local file is valid, no need for flysystem
            unlink($file);
        }

        rmdir($this->getUploadDir().'/coreHandlerUpload_test');
    }

    protected function createTempFile()
    {
        return tempnam($this->getUploadDir().'/coreHandlerUpload_test', 'CoreHandlerUploadTest');
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
