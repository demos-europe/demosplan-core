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

use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use Tests\Base\FunctionalTestCase;

class StatementCopierTest extends FunctionalTestCase
{
    use RefreshElasticsearchIndexTrait;

    /** @var StatementCopier */
    protected $sut;

    /** @var StatementService */
    private $statementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(StatementCopier::class);
        $this->statementService = self::getContainer()->get(StatementService::class);

        $user = $this->getUserReference('testUser');
        $this->logIn($user);

        $this->setElasticsearchIndexManager(self::getContainer()->get('fos_elastica.index_manager'));
    }

    public function testCopyMunicipalities(): void
    {
        self::markSkippedForCIIntervention();

        $testStatement = $this->getStatementReference('testStatement');
        static::assertNotEmpty($testStatement->getMunicipalities());

        $numberOfMunicipalitiesBefore = count($testStatement->getMunicipalities());
        $totalAmountOfMunicipalitiesBefore = $this->countEntries(Municipality::class);

        // create new statement with valid values to ensure persisting to DB is working
        $data = [
            'text'           => '<p>zuzuzuzzu</p>',
            'phase'          => 'configuration',
            'submittedDate'  => '07.07.2016',
            'pId'            => $testStatement->getProcedureId(),
            'elementId'      => '-',
            'documentId'     => '',
            'paragraphId'    => '',
            'publicVerified' => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'civic'          => true,
            'externId'       => 'M5526',
            'submitType'     => 'system',
        ];
        $newStatement = $this->statementService->newStatement($data);

        $newStatement = $this->sut->copyMunicipalities($testStatement, $newStatement);
        // trigger cascading persist of votes by persist of statement:
        $newStatement = $this->statementService->getStatementPublicRepository()->updateObject($newStatement);

        static::assertCount($numberOfMunicipalitiesBefore, $testStatement->getMunicipalities());
        static::assertCount($numberOfMunicipalitiesBefore, $newStatement->getMunicipalities());

        // assert same amount after and before, because one localization can associated to many statements
        // therefore there is no need to actually copy localisation data
        $totalAmountOfMunicipalitiesAfter = $this->countEntries(Municipality::class);
        static::assertSame($totalAmountOfMunicipalitiesBefore, $totalAmountOfMunicipalitiesAfter);
    }

    public function testCopyPriorityAreas(): void
    {
        self::markSkippedForCIIntervention();

        $testStatement = $this->getStatementReference('testStatement');
        static::assertNotEmpty($testStatement->getPriorityAreas());

        $numberOfPriorityAreasBefore = count($testStatement->getPriorityAreas());
        $totalAmountOfPriorityAreasBefore = $this->countEntries(PriorityArea::class);

        // create new statement with valid values to ensure persisting to DB is working
        $data = [
            'text'           => '<p>zuzuzuzzu</p>',
            'phase'          => 'configuration',
            'submittedDate'  => '07.07.2016',
            'pId'            => $testStatement->getProcedureId(),
            'elementId'      => '-',
            'documentId'     => '',
            'paragraphId'    => '',
            'publicVerified' => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'civic'          => true,
            'externId'       => 'M5526',
            'submitType'     => 'system',
        ];
        $newStatement = $this->sut->newStatement($data);

        $statementCopier = self::getContainer()->get(StatementCopier::class);
        $newStatement = $statementCopier->copyPriorityAreas($testStatement, $newStatement);
        // trigger cascading persist of votes by persist of statement:
        $newStatement = $this->sut->getStatementPublicRepository()->updateObject($newStatement);

        static::assertCount($numberOfPriorityAreasBefore, $testStatement->getPriorityAreas());
        static::assertCount($numberOfPriorityAreasBefore, $newStatement->getPriorityAreas());

        // assert same amount after and before, because one localization can associated to many statements
        // therefore there is no need to actually copy localisation data
        $totalAmountOfPriorityAreasAfter = $this->countEntries(PriorityArea::class);
        static::assertSame($totalAmountOfPriorityAreasBefore, $totalAmountOfPriorityAreasAfter);
    }

    public function testCopyTags(): void
    {
        self::markSkippedForCIIntervention();

        $testStatement = $this->getStatementReference('testStatement2');

        // check setup:
        static::assertNotEmpty($testStatement->getTags());
        $amountOfTagsOfStatementBefore = count($testStatement->getTags());
        $totalAmountOfTagsBefore = $this->countEntries(Tag::class);

        // create new statement with valid values to ensure persisting to DB is working
        $data = [
            'text'           => '<p>zuzuzuzzu</p>',
            'phase'          => 'configuration',
            'submittedDate'  => '07.07.2016',
            'pId'            => $testStatement->getProcedureId(),
            'elementId'      => '-',
            'documentId'     => '',
            'paragraphId'    => '',
            'publicVerified' => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'civic'          => true,
            'externId'       => 'M5526',
            'submitType'     => 'system',
        ];
        $newStatement = $this->sut->newStatement($data);

        static::assertEmpty($newStatement->getTags());
        $statementCopier = self::getContainer()->get(StatementCopier::class);
        $newStatement = $statementCopier->copyTags($testStatement, $newStatement);

        // trigger cascading persist of votes by persist of statement:
        $newStatement = $this->sut->getStatementPublicRepository()->updateObject($newStatement);

        static::assertCount($amountOfTagsOfStatementBefore, $testStatement->getTags());
        static::assertCount($amountOfTagsOfStatementBefore, $newStatement->getTags());

        // assert same amount after and before, because one tag can be associated to many statements
        // therefore there is no need to actually copy tags
        $totalAmountOfTagsAfter = $this->countEntries(Tag::class);
        static::assertSame($totalAmountOfTagsBefore, $totalAmountOfTagsAfter);
    }

    public function testCopyCounties(): void
    {
        self::markSkippedForCIIntervention();

        $testStatement = $this->getStatementReference('testStatement');
        static::assertNotEmpty($testStatement->getCounties());

        $numberOfCountiesBefore = count($testStatement->getCounties());
        $totalAmountOfCountiesBefore = $this->countEntries(County::class);

        // create new statement with valid values to ensure persisting to DB is working
        $data = [
            'text'           => '<p>zuzuzuzzu</p>',
            'phase'          => 'configuration',
            'submittedDate'  => '07.07.2016',
            'pId'            => $testStatement->getProcedureId(),
            'elementId'      => '-',
            'documentId'     => '',
            'paragraphId'    => '',
            'publicVerified' => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'civic'          => true,
            'externId'       => 'M5526',
            'submitType'     => 'system',
        ];
        $newStatement = $this->sut->newStatement($data);

        $statementCopier = self::getContainer()->get(StatementCopier::class);
        $newStatement = $statementCopier->copyCounties($testStatement, $newStatement);
        // trigger cascading persist of votes by persist of statement:
        $newStatement = $this->sut->getStatementPublicRepository()->updateObject($newStatement);

        static::assertCount($numberOfCountiesBefore, $testStatement->getCounties());
        static::assertCount($numberOfCountiesBefore, $newStatement->getCounties());

        // assert same amount after and before, because one localization can associated to many statements
        // therefore there is no need to actually copy localisation data
        $totalAmountOfCountiesAfter = $this->countEntries(County::class);
        static::assertSame($totalAmountOfCountiesBefore, $totalAmountOfCountiesAfter);
    }

    /**
     * @throws \demosplan\DemosPlanCoreBundle\Exception\CopyException
     */
    public function testCopyStatementWithFragments(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $testStatement = $this->getStatementReference('testStatement');

        $createdCopy = $this->sut->copyStatementObjectWithinProcedure($testStatement);
        static::assertInstanceOf(Statement::class, $createdCopy);

        static::assertEquals($testStatement->getFragments()->count(), $createdCopy->getFragments()->count());
    }

    public function testCopyCluster(): void
    {
        /** @var Statement $clusterStatement */
        $clusterStatement = $this->fixtures->getReference('clusterStatement1');

        static::assertTrue($clusterStatement->isClusterStatement());
        $result = $this->sut->copyStatementObjectWithinProcedure($clusterStatement);
        static::assertFalse($result);
    }
}
