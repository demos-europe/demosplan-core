<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\DraftStatementFIle;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\FileFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\DraftStatementFileFactory;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementFileHandler;
use Tests\Base\FunctionalTestCase;

class DraftStatementFileHandlerTest extends FunctionalTestCase
{
    /** @var DraftStatementFileHandler */
    protected $sut;

    /** @var DraftStatement */
    protected $testDraftStatement;

    /** @var File */
    protected $testFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(DraftStatementFileHandler::class);
        $this->testDraftStatement = $this->fixtures->getReference('testDraftStatement');
    }

    public function testGetDraftStatementRelatedToThisFileReturnsEmptyArrayWhenFileIsNull(): void
    {
        $fileId = 'nonexistent-file-id';
        $result = $this->sut->getDraftStatementRelatedToThisFile($fileId);
        $this->assertEmpty($result);
    }

    public function testGetDraftStatementRelatedToThisFileReturnsDraftStatements(): void
    {
        $testFile = FileFactory::createOne();
        $fileId = $testFile->getId();
        $draftStatementFile = DraftStatementFileFactory::createOne();
        $draftStatementFile->setFile($testFile->_real());
        $draftStatementFile->_save();

        $result = $this->sut->getDraftStatementRelatedToThisFile($fileId);
        $this->assertSame([$draftStatementFile->_real()], $result);
    }
}
