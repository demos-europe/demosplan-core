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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Export\StatementZipPathResolver;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class StatementZipPathResolverTest extends FunctionalTestCase
{
    private const DOCX_EXTENSION = '.docx';

    private Statement|Proxy|null $testStatement;

    private StatementMeta|Proxy|null $testStatementeMeta;

    /**
     * @var StatementZipPathResolver
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(StatementZipPathResolver::class);
        $this->testStatement = StatementFactory::createOne();
        $this->testStatementeMeta = StatementMetaFactory::createOne();
        $this->testStatement->setMeta($this->testStatementeMeta->_real());
    }

    public function testResolveWithTrueDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->resolve([
            [$this->testStatement->_real(), false],
            [$this->testStatement->_real(), false],
        ], '');
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testCensorshipOnPathOnExportSegmentsInZip(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $citizenOrganisation = $this->find(Orga::class, User::ANONYMOUS_USER_ORGA_ID);

        $internalStatement = StatementFactory::createOne();
        $externalStatement = StatementFactory::createOne(['organisation' => $citizenOrganisation]);

        static::assertTrue($externalStatement->isSubmittedByCitizen());
        static::assertTrue($internalStatement->isSubmittedByOrganisation());

        $statements = $this->sut->resolve([
            [$externalStatement->_real(), $externalStatement->isSubmittedByCitizen() && $censorCitizenData],
            [$internalStatement->_real(), $internalStatement->isSubmittedByOrganisation() && $censorInstitutionData],
        ]);

        foreach ($statements as $key => $statement) {
            if ($statement->isSubmittedByCitizen()) {
                static::assertEquals($this->getExpectedFileName($statement, $censorCitizenData), $key);
            } elseif ($statement->isSubmittedByOrganisation()) {
                static::assertEquals($this->getExpectedFileName($statement, $censorInstitutionData), $key);
            }
        }
    }

    private function getExpectedFileName(Statement $statement, bool $censored): string
    {
        return $censored
            ? $statement->getExternId().self::DOCX_EXTENSION
            : $statement->getExternId().'-einreichende-person-unbekannt-eingangsnummer-unbekannt'.self::DOCX_EXTENSION;
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testResolveWithSuperficialDuplicate(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $statementA = $this->createMinimalTestStatement('a', 'a', 'a');
        $statementB = $this->createMinimalTestStatement('b', 'a', 'a');

        $censoredA = $this->isCensored($statementA, $censorCitizenData, $censorInstitutionData);
        $censoredB = $this->isCensored($statementB, $censorCitizenData, $censorInstitutionData);

        $statements = $this->sut->resolve([
            [$statementA->_real(), $censoredA],
            [$statementB->_real(), $censoredB],
        ]);

        $expectedAKey = $censoredA
            ? 'statement-extern-id-a'.self::DOCX_EXTENSION
            : 'statement-extern-id-a-statement-author-name-a-statement-intern-id-a'.self::DOCX_EXTENSION;
        $this->assertStatementAtKey($expectedAKey, $statementA, $statements);

        $expectedBKey = $censoredB
            ? 'statement-extern-id-b'.self::DOCX_EXTENSION
            : 'statement-extern-id-b-statement-author-name-a-statement-intern-id-a'.self::DOCX_EXTENSION;
        $this->assertStatementAtKey($expectedBKey, $statementB, $statements);
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testResolveWithoutDuplicate(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $statementA = $this->createMinimalTestStatement('xyz', 'xyz', 'xyz');
        $statementB = $this->createMinimalTestStatement('xyz', 'xyz', 'xyz');

        $censoredA = $this->isCensored($statementA, $censorCitizenData, $censorInstitutionData);
        $censoredB = $this->isCensored($statementB, $censorCitizenData, $censorInstitutionData);

        $statements = $this->sut->resolve([
            [$statementA->_real(), $censoredA],
            [$statementB->_real(), $censoredB],
        ], '');

        $expectedAKey = 'statement-extern-id-xyz-statement-author-name-xyz-statement-intern-id-xyz-'.$statementA->getId().self::DOCX_EXTENSION;
        $this->assertStatementAtKey($expectedAKey, $statementA, $statements);

        $expectedBKey = 'statement-extern-id-xyz-statement-author-name-xyz-statement-intern-id-xyz-'.$statementB->getId().self::DOCX_EXTENSION;
        $this->assertStatementAtKey($expectedBKey, $statementB, $statements);
    }

    public function getCensorParams(): array
    {
        return [
            [true, true],
            [false, false],
            [true, false],
            [false, true],
        ];
    }

    private function isCensored(Statement|Proxy $statement, bool $censorCitizenData, bool $censorInstitutionData): bool
    {
        return ($censorCitizenData && $statement->isSubmittedByCitizen())
            || ($censorInstitutionData && $statement->isSubmittedByOrganisation());
    }

    private function assertStatementAtKey(string $expectedKey, Statement|Proxy $statement, array $statements): void
    {
        self::assertArrayHasKey($expectedKey, $statements);
        self::assertSame($statement->_real(), $statements[$expectedKey]);
    }
}
