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

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementDeleter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementMover;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Tests\Base\FunctionalTestCase;

class StatementMoverTest extends FunctionalTestCase
{
    /** @var StatementMover */
    protected $sut;

    private StatementService|null $statementService;
    private StatementDeleter|null $statementDeleter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(StatementMover::class);
        $this->statementService = $this->getContainer()->get(StatementService::class);
        $this->statementDeleter = $this->getContainer()->get(StatementDeleter::class);

        /** @var User $testUser */
        $testUser = $this->fixtures->getReference('testUser');
        $this->logIn($testUser);
    }

    /**
     * @dataProvider getStatementsToMove
     */
    public function testRevertMoveStatement($providerData): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Statement $statementToMove */
        $statementToMove = $this->fixtures->getReference($providerData['nameOfStatementToMove']);
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference($providerData['nameOfTargetProcedure']);

        // check setup:
        static::assertNotEquals($statementToMove->getProcedureId(), $targetProcedure->getId());
        // is possible, because will be deleted?!:
        static::assertEmpty($statementToMove->getElement(), 'Cant move Statement with element(s).');
        static::assertEmpty($statementToMove->getDocument(), 'Cant move Statement with document(s).');
        static::assertEmpty($statementToMove->getParagraph(), 'Cant move Statement with paragraph(s).');
        static::assertInstanceOf(
            Statement::class,
            $statementToMove->getOriginal(),
            'Cant move Statement with paragraph(s).'
        );

        if ('standard' === $providerData['testCase']) {
            static::assertEquals($statementToMove->getOriginalId(), $statementToMove->getParentId());
            static::assertEmpty($statementToMove->getChildren());
            static::assertTrue(0 < $statementToMove->getFragments()->count());
        }

        if ('parentStatement' === $providerData['testCase']) {
            static::assertEquals($statementToMove->getOriginalId(), $statementToMove->getParentId());
            static::assertTrue(0 < $statementToMove->getChildren()->count(), 'not ture');
        }

        if ('childOfStatement' === $providerData['testCase']) {
            static::assertInstanceOf(Statement::class, $statementToMove->getParent());
            static::assertEmpty($statementToMove->getChildren());
        }

        // execute move:
        $movedStatement = $this->sut->moveStatementToProcedure($statementToMove, $targetProcedure);
        static::assertInstanceOf(Statement::class, $movedStatement);
    }

    public function testOriginalStatementDataOnMoveStatement(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $statementToMove = $this->getStatementReference('testStatement1');
        $targetProcedure = $this->getProcedureReference('testProcedure3');
        $sourceProcedure = $statementToMove->getProcedure();
        $amountOfOriginalStatementsBefore = $this->countEntries(Statement::class, ['original' => null]);
        $amountOfStatementsBefore = $this->countEntries(Statement::class);
        $parentOfStatementToMove = $statementToMove->getParent();
        $originalOfStatementToMove = $statementToMove->getOriginal();

        // checkSetUp
        static::assertNotEquals($targetProcedure->getId(), $statementToMove->getProcedureId(), 'invalid setup');
        static::assertFalse($statementToMove->wasMoved(), 'invalid setup');
        static::assertFalse($statementToMove->isClusterStatement(), 'invalid setup');
        static::assertFalse($statementToMove->isInCluster(), 'invalid setup');
        static::assertEquals($statementToMove->getParentId(), $statementToMove->getOriginalId(), 'invalid setup');

        $movedStatement = $this->sut->moveStatementToProcedure($statementToMove, $targetProcedure);

        $amountOfOriginalStatementsAfter = $this->countEntries(Statement::class, ['original' => null]);
        $amountOfStatementsAfter = $this->countEntries(Statement::class);
        static::assertEquals($amountOfOriginalStatementsAfter, $amountOfOriginalStatementsBefore + 1);
        static::assertEquals($amountOfStatementsAfter, $amountOfStatementsBefore + 2);

        static::assertFalse($movedStatement->getOriginal()->wasMoved()); // on move statement, related original will be copied
        static::assertTrue($movedStatement->getPlaceholderStatement()->isPlaceholder());
        static::assertFalse($movedStatement->getPlaceholderStatement()->getOriginal()->isPlaceholder());

        // statement to move is same entity as moved statement,means: technically and actually moved
        // Placeholder is a new created entity (copy) of the statementToMove
        $placeholder = $movedStatement->getPlaceholderStatement();
        static::assertEquals($movedStatement->getId(), $statementToMove->getId());
        static::assertNotEquals($placeholder->getId(), $statementToMove->getId());
        static::assertNotEquals($placeholder->getProcedureId(), $statementToMove->getProcedureId());

        static::assertNotEquals($movedStatement->getOriginalId(), $placeholder->getOriginalId());
        static::assertEquals($movedStatement->getId(), $placeholder->getMovedStatementId());
        static::assertEquals($movedStatement->getProcedureId(), $placeholder->getMovedToProcedureId());

        static::assertTrue($movedStatement->wasMoved());
        static::assertEquals($targetProcedure->getId(), $movedStatement->getProcedureId());
        static::assertEquals($targetProcedure->getId(), $placeholder->getMovedToProcedureId());
        static::assertEquals($sourceProcedure->getId(), $movedStatement->getMovedFromProcedureId());
        static::assertEquals($sourceProcedure->getId(), $placeholder->getProcedureId());
        static::assertNull($placeholder->getMovedFromProcedure());
        static::assertNull($placeholder->getMovedFromProcedureId());
        static::assertNull($movedStatement->getMovedToProcedure());
        static::assertNull($movedStatement->getMovedToProcedureId());

        static::assertFalse($placeholder->wasMoved());
        static::assertTrue($movedStatement->wasMoved());

        static::assertEquals($targetProcedure->getId(), $movedStatement->getOriginal()->getProcedureId());
        static::assertEquals($targetProcedure->getId(), $movedStatement->getProcedureId());
        static::assertEquals($sourceProcedure->getId(), $placeholder->getOriginal()->getProcedureId());
        static::assertEquals($sourceProcedure->getId(), $placeholder->getProcedureId());

        // todo: check for children of original all in same procedure and assert count usw.
        static::assertEquals($placeholder->getParentId(), $parentOfStatementToMove->getId());
        static::assertEquals($placeholder->getOriginalId(), $originalOfStatementToMove->getId());
        static::assertEquals($placeholder->getParentId(), $placeholder->getOriginalId());

        static::assertNotEquals($movedStatement->getParentId(), $parentOfStatementToMove->getOriginalId());
        static::assertNotEquals($movedStatement->getOriginalId(), $parentOfStatementToMove->getOriginalId());
        static::assertEquals($movedStatement->getParentId(), $movedStatement->getOriginalId());

        static::assertCount(1, $placeholder->getOriginal()->getChildren());
        static::assertCount(1, $placeholder->getParent()->getChildren());
        static::assertCount(1, $movedStatement->getOriginal()->getChildren());
        static::assertCount(1, $movedStatement->getParent()->getChildren());
    }

    /**
     * @dataProvider getStatementsToRemoveRelations
     */
    public function testRemoveRelationsOnMoveStatement($providerData): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Statement $statementToMove */
        $statementToMove = $this->fixtures->getReference($providerData['nameOfStatementToMove']);
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference($providerData['nameOfTargetProcedure']);

        // check setup:
        static::assertNotEquals($statementToMove->getProcedureId(), $targetProcedure->getId());
        static::assertFalse($statementToMove->isOriginal());
        static::assertTrue($statementToMove->getOriginal()->isOriginal());
        static::assertInstanceOf(GdprConsent::class, $statementToMove->getOriginal()->getGdprConsent());
        static::assertFalse($statementToMove->isInCluster());
        static::assertFalse($statementToMove->isClusterStatement());

        $lockedByAssignment = $this->statementService->isStatementObjectLockedByAssignment($statementToMove);
        static::assertFalse($lockedByAssignment);

        if ('element' === $providerData['testCase']) {
            static::assertNotNull($statementToMove->getElement());
        }
        if ('document' === $providerData['testCase']) {
            static::assertNotNull($statementToMove->getDocument());
        }
        if ('paragraph' === $providerData['testCase']) {
            static::assertNotNull($statementToMove->getParagraph());
        }
        if ('internID' === $providerData['testCase']) {
            static::assertNotNull($statementToMove->getInternId());
        }

        $movedStatement = $this->sut->moveStatementToProcedure($statementToMove, $targetProcedure);
        static::assertInstanceOf(Statement::class, $movedStatement);
        // if statementTo move has an element, movedStatement should have an element to:
        if ('element' === $providerData['testCase']) {
            static::assertInstanceOf(Elements::class, $movedStatement->getElement());
            static::assertInstanceOf(Elements::class, $movedStatement->getOriginal()->getElement());
            static::assertNull($movedStatement->getDocument());
            static::assertNull($movedStatement->getOriginal()->getDocument());
            static::assertNull($movedStatement->getParagraph());
            static::assertNull($movedStatement->getOriginal()->getParagraph());
        }
        if ('document' === $providerData['testCase']) {
            static::assertEquals('Gesamtstellungnahme', $movedStatement->getElement()->getTitle());
            static::assertEquals('Gesamtstellungnahme', $movedStatement->getOriginal()->getElement()->getTitle());
            static::assertNull($movedStatement->getDocument());
            static::assertNull($movedStatement->getOriginal()->getDocument());
            static::assertNull($movedStatement->getParagraph());
            static::assertNull($movedStatement->getOriginal()->getParagraph());
        }
        if ('paragraph' === $providerData['testCase']) {
            static::assertEquals('Gesamtstellungnahme', $movedStatement->getElement()->getTitle());
            static::assertEquals('Gesamtstellungnahme', $movedStatement->getOriginal()->getElement()->getTitle());
            static::assertNull($movedStatement->getDocument());
            static::assertNull($movedStatement->getOriginal()->getDocument());
            static::assertNull($movedStatement->getParagraph());
            static::assertNull($movedStatement->getOriginal()->getParagraph());
        }
    }

    public function testDeleteMovedStatement(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Statement $statementToMove */
        $statementToMove = $this->fixtures->getReference('testStatement20');
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');

        $movedStatement = $this->sut->moveStatementToProcedure($statementToMove, $targetProcedure);

        static::assertInstanceOf(Statement::class, $movedStatement);

        $result = $this->statementDeleter->deleteStatementObject($movedStatement);
        static::assertTrue($result);
    }

    public function testHandlePublicationOnMoveOrCopyPublicStatementToPublicProcedure(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        static::assertTrue($targetProcedure->getPublicParticipationPublicationEnabled());

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testCopiedStatement2');

        static::assertFalse($testStatement->wasMoved());
        static::assertFalse($testStatement->isClusterStatement());
        static::assertFalse($testStatement->isInCluster());
        static::assertTrue($testStatement->getPublicAllowed());

        $movedStatement = $this->sut->moveStatementToProcedure($testStatement, $targetProcedure);
        static::assertInstanceOf(Statement::class, $movedStatement);

        static::assertEquals(Statement::PUBLICATION_APPROVED, $movedStatement->getPublicVerified());
        static::assertEquals($testStatement->getPublicAllowed(), $movedStatement->getPublicAllowed());
        static::assertEquals($testStatement->getNumberOfAnonymVotes(), $movedStatement->getNumberOfAnonymVotes());
        static::assertEquals($testStatement->getVotesNum(), $movedStatement->getVotesNum());
    }

    public function testHandlePublicationOnMoveOrCopyPublicStatementToNonPublicProcedure(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure3');
        static::assertFalse($targetProcedure->getPublicParticipationPublicationEnabled());

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testCopiedStatement2');
        static::assertFalse($testStatement->wasMoved());
        static::assertFalse($testStatement->isClusterStatement());
        static::assertFalse($testStatement->isInCluster());
        static::assertTrue($testStatement->getPublicAllowed());

        $movedStatement = $this->sut->moveStatementToProcedure($testStatement, $targetProcedure);
        static::assertInstanceOf(Statement::class, $movedStatement);

        static::assertEquals(
            Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            $movedStatement->getPublicVerified()
        );
        static::assertFalse($movedStatement->getPublicAllowed());
        static::assertEquals(0, $movedStatement->getNumberOfAnonymVotes());
        static::assertEquals(0, $movedStatement->getVotesNum());
    }

    public function testHandlePublicationOnOrCopyMoveNonPublicStatementToPublicProcedure(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        static::assertTrue($targetProcedure->getPublicParticipationPublicationEnabled());

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement20');
        static::assertFalse($testStatement->wasMoved());
        static::assertFalse($testStatement->isClusterStatement());
        static::assertFalse($testStatement->isInCluster());
        static::assertFalse($testStatement->getPublicAllowed());

        $movedStatement = $this->sut->moveStatementToProcedure($testStatement, $targetProcedure);
        static::assertInstanceOf(Statement::class, $movedStatement);

        static::assertEquals($testStatement->getPublicVerified(), $movedStatement->getPublicVerified());
        static::assertEquals($testStatement->getPublicAllowed(), $movedStatement->getPublicAllowed());
        static::assertEquals(0, $movedStatement->getNumberOfAnonymVotes());
        static::assertEquals(0, $movedStatement->getVotesNum());
    }

    public function testHandleInternIdOnCopyStatement(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        static::assertTrue($targetProcedure->getPublicParticipationPublicationEnabled());

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement20');
        static::assertFalse($testStatement->wasMoved());
        static::assertFalse($testStatement->isClusterStatement());
        static::assertFalse($testStatement->isInCluster());
        static::assertFalse($testStatement->getPublicAllowed());
        static::assertNotNull($testStatement->getInternId());

        $movedStatement = $this->sut->moveStatementToProcedure($testStatement, $targetProcedure);
        static::assertInstanceOf(Statement::class, $movedStatement);

        static::assertEquals($testStatement->getInternId(), $movedStatement->getInternId());
    }

    /**
     * @dataProvider getStatementsToMove
     */
    public function testMoveStatementCopyToProcedure($providerData): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        // user not found exception

        /** @var Statement $statementToMove */
        $statementToMove = $this->fixtures->getReference($providerData['nameOfStatementToMove']);
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference($providerData['nameOfTargetProcedure']);

        $sourceProcedure = $statementToMove->getProcedure();
        $oldExternId = $statementToMove->getExternId();
        $parentIdOfStatementBeforeMove = $statementToMove->getParentId();
        $fragmentsOfStatementToMove = $statementToMove->getFragments();

        $amountOfChildrenOfStatementToMove = $statementToMove->getChildren()->count();
        $amountOfCountiesOfStatementToMove = $statementToMove->getCounties()->count();
        $amountOfMunicipalitiesOfStatementToMove = $statementToMove->getMunicipalities()->count();
        $amountOfPriorityAreasOfStatementToMove = $statementToMove->getPriorityAreas()->count();
        $amountOfVotesOfStatementToMove = count($statementToMove->getVotes());
        $amountOfStatementsBefore = $this->countEntries(Statement::class);

        static::assertEquals($statementToMove->getParentId(), $statementToMove->getOriginalId());
        static::assertInstanceOf(Statement::class, $statementToMove->getParent());

        // check setup:
        if ('standard' === $providerData['testCase']) {
            static::assertEmpty($statementToMove->getChildren());
        }

        if ('parentStatement' === $providerData['testCase']) {
            static::assertTrue(0 < $statementToMove->getChildren()->count(), 'not true');
        }

        if ('childOfStatement' === $providerData['testCase']) {
            static::assertEmpty($statementToMove->getChildren());
        }

        static::assertNotEquals($statementToMove->getProcedureId(), $targetProcedure->getId());
        static::assertFalse($statementToMove->isOriginal());
        static::assertTrue(
            $statementToMove->getOriginal()->isOriginal(),
            'invalid setup data: originalStatement is not an original statement.'
        );
        static::assertEquals(
            $statementToMove->getOriginal()->getProcedureId(),
            $statementToMove->getProcedureId()
        );
        static::assertFalse($statementToMove->isInCluster());
        static::assertFalse($statementToMove->isClusterStatement());

        static::assertEmpty($statementToMove->getElement(), 'Cant move Statement with element(s).');
        static::assertEmpty($statementToMove->getDocument(), 'Cant move Statement with document(s).');
        static::assertEmpty($statementToMove->getParagraph(), 'Cant move Statement with paragraph(s).');

        $lockedByAssignment = $this->statementService->isStatementObjectLockedByAssignment($statementToMove);
        static::assertFalse($lockedByAssignment);

        $numberOfStatementsBefore = $this->countEntries(
            Statement::class,
            ['externId' => $statementToMove->getExternId()]
        );
        $numberOfDraftStatementsBefore = $this->countEntries(
            DraftStatement::class,
            ['externId' => $statementToMove->getExternId()]
        );

        // return created placeHolder to remains in originProcedure
        $movedStatement = $this->sut->moveStatementToProcedure($statementToMove, $targetProcedure);
        $movedStatement = $this->statementService->getStatement($movedStatement->getId());
        $placeHolderStatement = $movedStatement->getPlaceholderStatement();
        // check placeHolderStatement:
        static::assertInstanceOf(Statement::class, $placeHolderStatement);
        static::assertInstanceOf(Statement::class, $statementToMove->getOriginal());
        static::assertEquals($oldExternId, $placeHolderStatement->getExternId());
        static::assertEquals($targetProcedure->getId(), $placeHolderStatement->getMovedToProcedure()->getId());

        // T13668
        static::assertNotEquals(
            $statementToMove->getOriginalId(),
            $placeHolderStatement->getOriginalId(),
            'Placeholder and moved statement use same original-statement.'
        );
        static::assertEquals(
            $statementToMove->getOriginal()->getProcedureId(),
            $statementToMove->getProcedureId()
        );
        static::assertEquals(
            $placeHolderStatement->getOriginal()->getProcedureId(),
            $placeHolderStatement->getProcedureId()
        );

        static::assertNull($placeHolderStatement->getPlaceholderStatement());
        // placeHolderStatement do not need any Fragmenst, Tags, ...:
        static::assertEmpty($placeHolderStatement->getVotes());
        static::assertEmpty($placeHolderStatement->getCounties());
        static::assertEmpty($placeHolderStatement->getMunicipalities());
        static::assertEmpty($placeHolderStatement->getPriorityAreas());
        static::assertEmpty($placeHolderStatement->getFragments());
        static::assertEmpty($placeHolderStatement->getFiles());
        static::assertEmpty($placeHolderStatement->getTags());
        // originalSTN will be stay in sourceProcedure, therefore the placeholder will get this as parent:
        static::assertEquals($parentIdOfStatementBeforeMove, $placeHolderStatement->getParentId());
        static::assertEquals($amountOfChildrenOfStatementToMove, $placeHolderStatement->getChildren()->count());

        // check movedStatement:
        // related Entities, still there?
        static::assertCount($fragmentsOfStatementToMove->count(), $statementToMove->getFragments());
        static::assertCount($amountOfCountiesOfStatementToMove, $statementToMove->getCounties());
        static::assertCount($amountOfMunicipalitiesOfStatementToMove, $statementToMove->getMunicipalities());
        static::assertCount($amountOfPriorityAreasOfStatementToMove, $statementToMove->getPriorityAreas());
        static::assertCount($amountOfVotesOfStatementToMove, $statementToMove->getVotes());

        // on move statement to procedure, parent of statement will be set to new originalSTN
        // $statementToMove will become a normal statement instead of a copystatement
        // placeHolderStatement will be the copy
        // in every case the moved statement will not have a parent
        static::assertEquals($statementToMove->getOriginal(), $statementToMove->getParent());
        // in every case the moved statement will not have children
        static::assertEmpty($statementToMove->getChildren());

        static::assertEquals($statementToMove->getProcedureId(), $targetProcedure->getId());
        static::assertNull($statementToMove->getMovedToProcedure());
        static::assertEquals(
            $sourceProcedure->getId(),
            $statementToMove->getPlaceholderStatement()->getProcedureId()
        );

        // T13668 new original STN is in target Procedure:
        static::assertEquals($statementToMove->getOriginal()->getProcedureId(), $targetProcedure->getId());

        // T13668 original STN is in target Procedure:
        static::assertEquals($placeHolderStatement->getOriginal()->getProcedureId(), $sourceProcedure->getId());
        static::assertNotEquals($oldExternId, $statementToMove->getExternId());

        $newProcedureId = $statementToMove->getProcedureId();

        /** @var StatementFragment $fragment */
        foreach ($statementToMove->getFragments() as $fragment) {
            static::assertEquals($newProcedureId, $fragment->getProcedureId());
        }

        // on move Statement to procedure, all tags will be removed, because tags are bound by procedure
        static::assertEmpty($statementToMove->getTags());

        // check amount of global Statements
        $numberOfStatements = $this->countEntries(
            Statement::class,
            ['externId' => $placeHolderStatement->getExternId()]
        );
        static::assertEquals($numberOfStatementsBefore, $numberOfStatements);
        $numberOfDraftStatements = $this->countEntries(
            DraftStatement::class,
            ['externId' => $placeHolderStatement->getExternId()]
        );
        static::assertEquals($numberOfDraftStatementsBefore, $numberOfDraftStatements);

        $amountOfStatementsAfter = $this->countEntries(Statement::class);
        static::assertEquals($amountOfStatementsBefore + 2, $amountOfStatementsAfter);
    }

    /**
     * DataProvider.
     */
    public function getStatementsToMove(): array
    {
        return [
            [
                [
                    'testCase'              => 'standard',
                    'nameOfStatementToMove' => 'testStatement20',
                    'nameOfTargetProcedure' => 'testProcedure2',
                ],
            ],
            [
                [
                    'testCase'              => 'parentStatement',
                    'nameOfStatementToMove' => 'testStatementParent',
                    'nameOfTargetProcedure' => 'testProcedure2',
                ],
            ],
            [
                [
                    'testCase'              => 'childOfStatement',
                    'nameOfStatementToMove' => 'testStatementNotOriginal',
                    'nameOfTargetProcedure' => 'testProcedure2',
                ],
            ],
        ];
    }

    /**
     * DataProvider.
     */
    public function getStatementsToRemoveRelations(): array
    {
        return [
            [
                [
                    'testCase'              => 'element',
                    'nameOfStatementToMove' => 'testStatementWithElementOnly',
                    'nameOfTargetProcedure' => 'testProcedure2',
                ],
            ],
            [
                [
                    'testCase'              => 'document',
                    'nameOfStatementToMove' => 'testStatementWithDocumentOnly',
                    'nameOfTargetProcedure' => 'testProcedure2',
                ],
            ],
            [
                [
                    'testCase'              => 'paragraph',
                    'nameOfStatementToMove' => 'testStatementWithParagraphOnly',
                    'nameOfTargetProcedure' => 'testProcedure2',
                ],
            ],
            [
                [
                    'testCase'              => 'internID',
                    'nameOfStatementToMove' => 'testStatementWithInternID',
                    'nameOfTargetProcedure' => 'testProcedure2',
                ],
            ],
        ];
    }
}
