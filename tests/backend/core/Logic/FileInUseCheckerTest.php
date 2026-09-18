<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\FileFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\Logic\FileInUseChecker;
use Tests\Base\FunctionalTestCase;

class FileInUseCheckerTest extends FunctionalTestCase
{
    protected $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(FileInUseChecker::class);
    }

    public function testFileReferencedByHashInCustomerFieldIsInUse(): void
    {
        $hash = 'image_id';
        $file = FileFactory::createOne(['hash' => $hash]);
        CustomerFactory::createOne([
            'overviewDescriptionInSimpleLanguage' => '<img src="/file/'.$hash.'">',
        ]);

        $isInUse = $this->sut->isFileInUse($file->getId());

        self::assertTrue($isInUse);
    }

    public function testFileReferencedByIdentInCustomerFieldIsInUse(): void
    {
        $file = FileFactory::createOne(['hash' => 'unrelated-hash']);
        CustomerFactory::createOne([
            'imprint' => '<img src="/file/'.$file->getId().'">',
        ]);

        $isInUse = $this->sut->isFileInUse($file->getId());

        self::assertTrue($isInUse);
    }

    public function testFileNotReferencedAnywhereIsNotInUse(): void
    {
        $file = FileFactory::createOne(['hash' => 'orphaned-hash']);
        CustomerFactory::createOne();

        $isInUse = $this->sut->isFileInUse($file->getId());

        self::assertFalse($isInUse);
    }
}
