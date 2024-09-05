<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use Cocur\Slugify\Slugify;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentExporterFileNameGenerator;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class SegmentExporterFileNameGeneratorTest extends FunctionalTestCase
{
    private Statement|Proxy|null $testStatement;

    private StatementMeta|Proxy|null $testStatementeMeta;

    /**
     * @var SegmentExporterFileNameGenerator
     */
    protected $sut;

    private Slugify|Proxy|null $slugify;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentExporterFileNameGenerator::class);
        $this->slugify = $this->getContainer()->get(Slugify::class);
        $this->testStatement = StatementFactory::createOne();
        $this->testStatementeMeta = StatementMetaFactory::createOne();
        $this->testStatement->setMeta($this->testStatementeMeta->_real());
    }

    public function testGetFileName(): void
    {
        $this->testStatement->setInternId('12345');
        $this->testStatement->_save();

        $testData = [
            SegmentExporterFileNameGenerator::DEFAULT_TEMPLATE_NAME => $this->testStatement->getExternId().'-'.$this->testStatement->getMeta()->getOrgaName().'-'.$this->testStatement->getInternId(),
            SegmentExporterFileNameGenerator::PLACEHOLDER_NAME      => $this->testStatement->getMeta()->getOrgaName(),
            'My Custom Template'                                    => 'My Custom Template',
            ''                                                      => $this->testStatement->getExternId().'-'.$this->testStatement->getMeta()->getOrgaName().'-'.$this->testStatement->getInternId(),
        ];

        foreach ($testData as $templateName => $rawExpectedFileName) {
            $this->verifyFileNameFromTemplate(
                $rawExpectedFileName,
                $templateName,
                $this->testStatement);
        }
    }

    private function verifyFileNameFromTemplate(string $rawExpectedFileName, string $templateName, Statement|Proxy|null $testStatement): void
    {
        $expectedFileName = $this->slugify->slugify($rawExpectedFileName);
        $fileName = $this->sut->getFileName($testStatement->_real(), $templateName);
        self::assertSame($expectedFileName, $fileName);
    }
}
