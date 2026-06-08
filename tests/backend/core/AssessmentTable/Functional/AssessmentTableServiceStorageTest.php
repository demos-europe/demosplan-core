<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AssessmentTable\Functional;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNameTooLongException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceStorage;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use Tests\Base\FunctionalTestCase;

class AssessmentTableServiceStorageTest extends FunctionalTestCase
{
    private const BASE_DATE = '01.01.2020';
    private const LATER_DATE = '10.01.2020';
    private const SUBMITTED_DATE = self::BASE_DATE.' 00:00:00';

    /**
     * @var AssessmentTableServiceStorage
     */
    protected $sut;

    /**
     * Make it impossible to update (cluster) statements with a name considered too long.
     *
     * The current implementation throws an StatementNameTooLongException, but you may adjust this test as
     * you like (eg. when the validation is refactored to use symfony validation) as long as the name length check
     * is tested here.
     *
     * @throws MessageBagException
     */
    public function testStartServiceActionStatementNameTooLongException()
    {
        $clusterStatement = $this->getStatementReference('clusterStatement 1');
        $clusterStatementId = $clusterStatement->getId();

        $this->expectException(StatementNameTooLongException::class);
        $rParams = [
            'request' => [
                'clusterName' => str_repeat('x', 201),
                'action'      => 'update',
                'ident'       => $clusterStatementId,
            ],
        ];
        $this->sut->executeAdditionalSingleViewAction($rParams);
        self::fail('expected specific exception');
    }

    /**
     * The authoredDate (Verfassungsdatum) must not be later than the submittedDate
     * (Einreichungsdatum). Editing other fields on a pre-existing statement whose
     * dates are already invalid must still be possible, so the check only fires
     * when the user actually changes at least one of the two dates.
     *
     * @dataProvider authoredAfterSubmittedProvider
     */
    public function testIsAuthoredDateAfterSubmittedDate(
        array $statementArray,
        ?string $currentAuthored,
        ?string $currentSubmitted,
        bool $expected,
    ): void {
        // Arrange: build the currently stored statement via factories, without persisting
        // (the method under test is pure in-memory logic and never touches the database).
        $statement = StatementFactory::new()
            ->create([
                'submit' => null === $currentSubmitted
                    ? null
                    : DateTime::createFromFormat('d.m.Y', $currentSubmitted),
            ])
            ->_real();
        $meta = StatementMetaFactory::new()
            ->create([
                'statement'    => $statement,
                'authoredDate' => null === $currentAuthored
                    ? null
                    : DateTime::createFromFormat('d.m.Y', $currentAuthored),
            ])
            ->_real();
        $statement->setMeta($meta);

        // Act: run the cross-field date validation against the proposed values.
        $result = $this->invokeProtectedMethod(
            [AssessmentTableServiceStorage::class, 'isAuthoredDateAfterSubmittedDate'],
            $statementArray,
            $statement
        );

        // Assert: only an authoredDate later than the submittedDate is rejected.
        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: ?string, 2: ?string, 3: bool}>
     */
    public static function authoredAfterSubmittedProvider(): array
    {
        return [
            'authored after submitted, dates changed -> rejected' => [
                ['authoredDate' => self::LATER_DATE, 'submittedDate' => self::SUBMITTED_DATE],
                self::BASE_DATE, self::BASE_DATE, true,
            ],
            'authored equals submitted -> allowed' => [
                ['authoredDate' => '05.01.2020', 'submittedDate' => '05.01.2020 12:00:00'],
                self::BASE_DATE, self::BASE_DATE, false,
            ],
            'authored before submitted -> allowed' => [
                ['authoredDate' => self::BASE_DATE, 'submittedDate' => '05.01.2020 00:00:00'],
                self::BASE_DATE, self::BASE_DATE, false,
            ],
            'both dates unchanged on pre-existing invalid record -> allowed' => [
                ['authoredDate' => self::LATER_DATE, 'submittedDate' => self::SUBMITTED_DATE],
                self::LATER_DATE, self::BASE_DATE, false,
            ],
            'invalid authored date string -> ignored' => [
                ['authoredDate' => 'not-a-date', 'submittedDate' => self::SUBMITTED_DATE],
                self::BASE_DATE, self::BASE_DATE, false,
            ],
            'no authored date available -> ignored' => [
                ['submittedDate' => self::SUBMITTED_DATE],
                null, self::BASE_DATE, false,
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(AssessmentTableServiceStorage::class);

        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE));
    }
}
