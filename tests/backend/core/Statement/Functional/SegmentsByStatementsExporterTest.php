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

    public function testMapStatementsToPathInZipWithTrueDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->mapStatementsToPathInZip(
            [$this->testStatement->_real(), $this->testStatement->_real()],
            false,
            false,
            '');
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testMapStatementsToPathInZipWithSuperficialDuplicate(bool $censored): void
    {
        $statementA = $this->createMinimalTestStatement('a', 'a', 'a');
        $statementB = $this->createMinimalTestStatement('b', 'a', 'a');

        $statements = $this->sut->mapStatementsToPathInZip([$statementA->_real(), $statementB->_real()], $censored, $censored,'');

        if ($censored) {
            $expectedAKey = 'statement-extern-id-a.docx';
        } else {
            $expectedAKey = 'statement-extern-id-a-statement-author-name-a-statement-intern-id-a.docx';
        }
        self::assertArrayHasKey($expectedAKey, $statements);
        self::assertSame($statementA->_real(), $statements[$expectedAKey]);
        if ($censored) {
            $expectedBKey = 'statement-extern-id-b.docx';
        } else {
            $expectedBKey = 'statement-extern-id-b-statement-author-name-a-statement-intern-id-a.docx';
        }
        self::assertArrayHasKey($expectedBKey, $statements);
        self::assertSame($statementB->_real(), $statements[$expectedBKey]);
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testMapStatementsToPathInZipWithoutDuplicate(bool $censored): void
    {
        $statementA = $this->createMinimalTestStatement('xyz', 'xyz', 'xyz');
        $statementB = $this->createMinimalTestStatement('xyz', 'xyz', 'xyz');

        $statements = $this->sut->mapStatementsToPathInZip([$statementA->_real(), $statementB->_real()], $censored, $censored, '');

        $expectedAKey = 'statement-extern-id-xyz-statement-author-name-xyz-statement-intern-id-xyz-'.$statementA->getId().'.docx';
        self::assertArrayHasKey($expectedAKey, $statements);
        self::assertSame($statementA->_real(), $statements[$expectedAKey]);
        $expectedBKey = 'statement-extern-id-xyz-statement-author-name-xyz-statement-intern-id-xyz-'.$statementB->getId().'.docx';
        self::assertArrayHasKey($expectedBKey, $statements);
        self::assertSame($statementB->_real(), $statements[$expectedBKey]);
    }

    private function createMinimalTestStatement(string $idSuffix, string $internIdSuffix, string $submitterNameSuffix): Statement|Proxy
    {
        $statement = StatementFactory::createOne();
        $statement->setExternId("statement_extern_id_$idSuffix");
        $statement->_save();
        $statement->setInternId("statement_intern_id_$internIdSuffix");
        $statement->_save();
        $statement->getMeta()->setOrgaName(UserInterface::ANONYMOUS_USER_NAME);
        $statement->_save();
        $statement->getMeta()->setAuthorName("statement_author_name_$submitterNameSuffix");
        $statement->_save();

        return $statement;
    }

    public function getCensorParams(): array
    {
        return [
            [true],
            [false],
        ];
    }
}
