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
                if ($censorCitizenData) {
                    $expectedAKey = $statement->getExternId().self::DOCX_EXTENSION;
                    static::assertEquals($expectedAKey, $key);
                } else {
                    $expectedAKey = $statement->getExternId().'-einreichende-person-unbekannt-eingangsnummer-unbekannt'.self::DOCX_EXTENSION;
                    static::assertEquals($expectedAKey, $key);
                }
            } elseif ($statement->isSubmittedByOrganisation()) {
                if ($censorInstitutionData) {
                    $expectedAKey = $statement->getExternId().self::DOCX_EXTENSION;
                    static::assertEquals($expectedAKey, $key);
                } else {
                    $expectedAKey = $statement->getExternId().'-einreichende-person-unbekannt-eingangsnummer-unbekannt'.self::DOCX_EXTENSION;
                    static::assertEquals($expectedAKey, $key);
                }
            }
        }
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

        $censoredA = ($censorCitizenData && $statementA->isSubmittedByCitizen()) || ($censorInstitutionData && $statementA->isSubmittedByOrganisation());
        $censoredB = ($censorCitizenData && $statementB->isSubmittedByCitizen()) || ($censorInstitutionData && $statementB->isSubmittedByOrganisation());

        $statements = $this->sut->resolve([
            [$statementA->_real(), $censoredA],
            [$statementB->_real(), $censoredB],
        ]);

        if ($censoredA) {
            $expectedAKey = 'statement-extern-id-a'.self::DOCX_EXTENSION;
        } else {
            $expectedAKey = 'statement-extern-id-a-statement-author-name-a-statement-intern-id-a'.self::DOCX_EXTENSION;
        }
        self::assertArrayHasKey($expectedAKey, $statements);
        self::assertSame($statementA->_real(), $statements[$expectedAKey]);

        if ($censoredB) {
            $expectedBKey = 'statement-extern-id-b'.self::DOCX_EXTENSION;
        } else {
            $expectedBKey = 'statement-extern-id-b-statement-author-name-a-statement-intern-id-a'.self::DOCX_EXTENSION;
        }
        self::assertArrayHasKey($expectedBKey, $statements);
        self::assertSame($statementB->_real(), $statements[$expectedBKey]);
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

        $censoredA = ($censorCitizenData && $statementA->isSubmittedByCitizen()) || ($censorInstitutionData && $statementA->isSubmittedByOrganisation());
        $censoredB = ($censorCitizenData && $statementB->isSubmittedByCitizen()) || ($censorInstitutionData && $statementB->isSubmittedByOrganisation());

        $statements = $this->sut->resolve([
            [$statementA->_real(), $censoredA],
            [$statementB->_real(), $censoredB],
        ], '');

        $expectedAKey = 'statement-extern-id-xyz-statement-author-name-xyz-statement-intern-id-xyz-'.$statementA->getId().self::DOCX_EXTENSION;
        self::assertArrayHasKey($expectedAKey, $statements);
        self::assertSame($statementA->_real(), $statements[$expectedAKey]);

        $expectedBKey = 'statement-extern-id-xyz-statement-author-name-xyz-statement-intern-id-xyz-'.$statementB->getId().self::DOCX_EXTENSION;
        self::assertArrayHasKey($expectedBKey, $statements);
        self::assertSame($statementB->_real(), $statements[$expectedBKey]);
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
}
