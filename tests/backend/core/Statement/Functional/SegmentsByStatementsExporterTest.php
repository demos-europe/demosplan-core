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
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class SegmentsByStatementsExporterTest extends FunctionalTestCase
{
    private Statement|Proxy|null $testStatement;

    private StatementMeta|Proxy|null $testStatementeMeta;

    /**
     * @var SegmentsByStatementsExporter
     */
    protected $sut;

    private Slugify|Proxy|null $slugify;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentsByStatementsExporter::class);
        $this->slugify = $this->getContainer()->get(Slugify::class);
        $this->testStatement = StatementFactory::createOne();
        $this->testStatementeMeta = StatementMetaFactory::createOne();
        $this->testStatement->setMeta($this->testStatementeMeta->_real());
    }
}
