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

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Constraint\DateStringConstraint;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedWorksheetNameException;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelImporter;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Base\FunctionalTestCase;

class StatementExcelImporterTest extends FunctionalTestCase
{
    /**
     * @var ExcelImporter;
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(ExcelImporter::class);
    }

    private function setProcedureAndLogin()
    {
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::$container->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE));
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    /**
     * Naming of worksheets of loaded file does not have the expected names.
     */
    public function testInvalidArgumentException(): void
    {
        $this->expectException(UnexpectedWorksheetNameException::class);

        $this->setProcedureAndLogin();

        $testFile = $this->getFileReference('statements_as_xlsx_wrong_worksheetName');
        // hack to access modified file:
        $fileInfo = new SplFileInfo(
            $testFile->getPath().'/'.$testFile->getHash().'.xlsx',
            '',
            $testFile->getFilename().'.xlsx'
        );

        $this->sut->process($fileInfo);
    }

    public function testMinimalDataStatementGeneration(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->setProcedureAndLogin();
        $testFile = $this->getFileReference('statements_as_xlsx_minimal_data');
        $fileInfo = new SplFileInfo(
            $testFile->getPath().'/'.$testFile->getHash().'.xlsx',
            '',
            $testFile->getFilename().'.xlsx'
        );

        $numberOfStatementsBefore = $this->countEntries(Statement::class);
        $numberOfStatementMetasBefore = $this->countEntries(StatementMeta::class);

        $statementsData = $this->extract($fileInfo);

        $this->sut->process($fileInfo);
        $generatedStatements = $this->sut->getGeneratedStatements();
        static::assertCount(4, $generatedStatements);

        // because of no persisting or flushing, there should be the same amount of Statements in the Database
        static::assertCount($numberOfStatementsBefore, $this->getEntries(Statement::class));
        static::assertCount($numberOfStatementMetasBefore, $this->getEntries(StatementMeta::class));
        $sheetCounter = 0;
        $rowCounter = 1;

        foreach ($generatedStatements as $generatedStatement) {
            $this->checkStatementData($generatedStatement, $statementsData, $sheetCounter, $rowCounter);
            $this->checkStatementData($generatedStatement->getOriginal(), $statementsData, $sheetCounter, $rowCounter);

            ++$rowCounter;
            if ($rowCounter > 2) {
                $rowCounter = 1;
                ++$sheetCounter;
            }
        }
    }

    public function testInvalidDateFormat(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->setProcedureAndLogin();

        $statementData = [
            'Name'                => 'my Name',
            'E-Mail'              => 'my@email.address',
            'PLZ'                 => 15645,
            'Ort'                 => 'mein Ort',
            'Einreichungsdatum'   => '03:05:1999', // invalid DateFormat
            'Verfassungsdatum'    => '03/05/1999',
            'Art der Einreichung' => 'E-Mail',
            'Eingangsnummer'      => 'ui788',
            'Stellungnahmetext'   => 'mein super langer, doch nicht so langer Stellungnahmetext',
            'publicStatement'     => Statement::EXTERNAL,
        ];

        $generatedOriginalStatement = $this->sut->createNewOriginalStatement($statementData, 0, 0, 'Öffentlichkeit');
        $this->sut->createCopy($generatedOriginalStatement);
        static::assertTrue($this->sut->hasErrors());
        static::assertCount(1, $this->sut->getErrors());
        static::assertInstanceOf(
            DateStringConstraint::class,
            $this->sut->getErrors()[0]->getViolation()->getConstraint()
        );
    }

    public function testInvalidStatementText(): void
    {
        $this->setProcedureAndLogin();
        $statementData = [
            'Stellungnahmetext'   => '',
            'publicStatement'     => Statement::EXTERNAL,
        ];
        $this->sut->createNewOriginalStatement($statementData, 0, 0, 'Öffentlichkeit');
        // in case of an error an exception would be thrown
        static::asserttrue(true);
    }

    public function testGenerateStatementsFromExcel(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->setProcedureAndLogin();

        $testFile = $this->getFileReference('statements_as_xlsx');
        $fileInfo = new SplFileInfo($testFile->getPath().'/'.$testFile->getHash(), '', $testFile->getFilename());

        $numberOfStatementsBefore = $this->countEntries(Statement::class);
        $numberOfStatementMetasBefore = $this->countEntries(StatementMeta::class);

        $statementsData = $this->extract($fileInfo);

        $this->sut->process($fileInfo);
        $generatedStatements = $this->sut->getGeneratedStatements();
        static::assertCount(4, $generatedStatements);

        // because of no persisting or flushing, there should be the same amount of Statements in the Database
        static::assertCount($numberOfStatementsBefore, $this->getEntries(Statement::class));
        static::assertCount($numberOfStatementMetasBefore, $this->getEntries(StatementMeta::class));
        $sheetCounter = 0;
        $rowCounter = 1;

        foreach ($generatedStatements as $generatedStatement) {
            $this->checkStatementData($generatedStatement, $statementsData, $sheetCounter, $rowCounter);
            $this->checkStatementData($generatedStatement->getOriginal(), $statementsData, $sheetCounter, $rowCounter);

            ++$rowCounter;
            if ($rowCounter > 2) {
                $rowCounter = 1;
                ++$sheetCounter;
            }
        }
    }

    public function testMappingOfSubmitType(): void
    {
        /** @var GlobalConfigInterface|GlobalConfig $globalConfig */
        $globalConfig = self::$container->get(GlobalConfigInterface::class);
        $submitTypes = $globalConfig->getFormOptions()['statement_submit_types']['values'];
        $allowedValues = array_combine(array_keys($submitTypes), array_keys($submitTypes));

        static::assertEquals($allowedValues['eakte'], $this->sut->mapSubmitType('E-Akte'));
        static::assertEquals($allowedValues['declaration'], $this->sut->mapSubmitType('Niederschrift'));
        static::assertEquals($allowedValues['email'], $this->sut->mapSubmitType('E-Mail'));
        static::assertEquals($allowedValues['fax'], $this->sut->mapSubmitType('Fax'));
        static::assertEquals($allowedValues['letter'], $this->sut->mapSubmitType('Brief'));
        static::assertEquals($allowedValues['system'], $this->sut->mapSubmitType('Beteiligungsplattform'));
        static::assertEquals($allowedValues['unknown'], $this->sut->mapSubmitType('Unbekannt'));
        static::assertEquals($allowedValues['unspecified'], $this->sut->mapSubmitType('Sonstige'));
    }

    private function extract(SplFileInfo $fileInfo): array
    {
        $statementsData = [];
        $spreadsheet = IOFactory::load($fileInfo->getPathname());
        $worksheets = $spreadsheet->getAllSheets();
        foreach ($worksheets as $worksheet) {
            $statementsData[] = $worksheet->toArray();
        }

        return $statementsData;
    }

    private function checkStatementData(Statement $generatedStatement, array $statementsData, int $sheetCounter, int $rowCounter): void
    {
        $statementsData[$sheetCounter][$rowCounter] = array_combine($statementsData[$sheetCounter][0], $statementsData[$sheetCounter][$rowCounter]);

        static::assertEquals($statementsData[$sheetCounter][$rowCounter]['Name'], $generatedStatement->getAuthorName(), 'author name');
        static::assertEquals($statementsData[$sheetCounter][$rowCounter]['E-Mail'], $generatedStatement->getOrgaEmail(), 'emailaddress');
        static::assertEquals($statementsData[$sheetCounter][$rowCounter]['PLZ'], $generatedStatement->getOrgaPostalCode(), 'plz');
        static::assertEquals($statementsData[$sheetCounter][$rowCounter]['Ort'], $generatedStatement->getOrgaCity(), 'city');

        static::assertEquals($statementsData[$sheetCounter][$rowCounter]['Hausnummer'] ?? '', $generatedStatement->getMeta()->getHouseNumber(), 'houseNumber');
        static::assertEquals($statementsData[$sheetCounter][$rowCounter]['Straße'] ?? '', $generatedStatement->getMeta()->getOrgaStreet(), 'street');

        // because of manual statement, this has to be null:
        static::assertNull($generatedStatement->getOrganisationName(), "name of related organisation ({$generatedStatement->getExternId()})");

        if (Statement::EXTERNAL === $generatedStatement->getPublicStatement()) {
            static::assertEquals(User::ANONYMOUS_USER_NAME, $generatedStatement->getMeta()->getOrgaName(), "organisation name ({$generatedStatement->getExternId()})");
            static::assertEquals(User::ANONYMOUS_USER_DEPARTMENT_NAME, $generatedStatement->getMeta()->getOrgaDepartmentName(), 'department name');
            static::assertEquals('citizen', $generatedStatement->getMeta()->getMiscDataValue(StatementMeta::SUBMITTER_ROLE), 'department name');
        } else {
            static::assertEquals($statementsData[$sheetCounter][$rowCounter]['Institution'], $generatedStatement->getMeta()->getOrgaName(), "organisation name ({$generatedStatement->getExternId()})");
            static::assertEquals($statementsData[$sheetCounter][$rowCounter]['Abteilung'], $generatedStatement->getMeta()->getOrgaDepartmentName(), 'department name');
            static::assertEquals('publicagency', $generatedStatement->getMeta()->getMiscDataValue(StatementMeta::SUBMITTER_ROLE), 'department name');
        }

        static::assertTrue(
            Carbon::parse($statementsData[$sheetCounter][$rowCounter]['Einreichungsdatum'])->isSameMinute($generatedStatement->getSubmitObject()),
            'submitDate'
        );
        static::assertTrue(
            Carbon::parse($statementsData[$sheetCounter][$rowCounter]['Verfassungsdatum'])->isSameMinute($generatedStatement->getMeta()->getAuthoredDateObject()),
            'authoredDate'
        );
        static::assertEquals($statementsData[$sheetCounter][$rowCounter]['Eingangsnummer'], $generatedStatement->getInternId(), 'internId');
        static::assertEquals($statementsData[$sheetCounter][$rowCounter]['Stellungnahmetext'], $generatedStatement->getText(), 'text');

        $incomingSubmitType = $this->sut->mapSubmitType($statementsData[$sheetCounter][$rowCounter]['Art der Einreichung'] ?? 'Unbekannt');
        static::assertEquals($incomingSubmitType, $generatedStatement->getSubmitType(), 'submitType');

        // check for not ex externId:
        $entries = $this->getEntries(Statement::class, ['externId' => $generatedStatement->getExternId()]);
        static::assertEmpty($entries); // should be empty, because non of the generated Statements are persisted yet.
    }
}
