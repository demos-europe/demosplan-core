<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AssessmentTable;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use Exception;
use Tests\Base\FunctionalTestCase;

class AssessmentHandlerTest extends FunctionalTestCase
{
    public const STATEMENT_REFERENCE = 'testStatementWithFile';

    /**
     * @var AssessmentHandler
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(AssessmentHandler::class);
        $this->loginTestUser();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->sut = null;
    }

    /**
     * StatementFragments can also have files. But they are not mentioned in T11544
     * -> exportType = statementsOnly.
     *
     * @throws Exception
     */
    public function testAnonymousFileOnStatementsDoxcExport()
    {
        // getStatementsByProcedureId() is called in exportDocx() and it is not mocked which needs elasticsearch
        self::markSkippedForCIIntervention();

        /** @var Statement $statement */
        $statement = $this->fixtures->getReference(self::STATEMENT_REFERENCE);
        $file1 = new File();
        $file1->setHash('df055eb7-5405-425b-9e21-7faa63f69a70');
        $file1->setDescription('session: ee39542b87bc4d0cab7700646d250028');
        $file1->setName('asdf_testdokument.pdf');
        $file1->setPath(__DIR__.'/../../../../../app/cache/test/files');
        $file1->setFilename('testdokument.pdf');
        $file1->setTags(',,Demos Test');
        $file1->setAuthor('0a8ca8ee-ce50-432b-a376-935d4fd5aacb');
        $file1->setApplication('FI');
        $file1->setMimetype('application/x-pdf');
        $file1->setMimetype('application/x-pdf');
        $file1->setCreated(new DateTime());
        $file1->setModified(new DateTime());
        $file1->setValidUntil(new DateTime());
        $file1->setDeleted(0);
        $file1->setStatDown(0);
        $file1->setInfected(0);
        $file1->setLastVScan(new DateTime());
        $file1->setBlocked(0);
        $this->getEntityManager()->persist($file1);
        $statement->setFiles([$file1]);
        $this->getEntityManager()->persist($statement);

        $requestPost = [
            'procedure' => $statement->getProcedureId(),
            'items'     => [$statement->getId()],
        ];

        $condensedExport = [
            'template'         => 'condensed',
            'anonymous'        => true,
            'numberStatements' => true,
            'exportType'       => 'statementsOnly',
            'sortType'         => AssessmentTableServiceOutput::EXPORT_SORT_DEFAULT,
        ];
        $landscapeExport = [
            'template'         => 'landscape',
            'anonymous'        => true,
            'numberStatements' => true,
            'exportType'       => 'statementsOnly',
            'sortType'         => AssessmentTableServiceOutput::EXPORT_SORT_DEFAULT,
        ];

        $exportChoices = [$condensedExport, $landscapeExport];

        foreach ($exportChoices as $exportChoice) {
            /**
             * Notice how procedureId is passed twice.
             */
            $exportResult = $this->sut->exportDocx($statement->getProcedureId(), $requestPost, $exportChoice, AssessmentTableViewMode::DEFAULT_VIEW, false);

            $serialized = serialize($exportResult->getWriter());

            $matches = [];
            preg_match('/(testdokument\.pdf)/', $serialized, $matches);

            $this->assertCount(0, $matches);
        }
    }
}
