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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadStatementData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class DemosPlanStatementAPIControllerTest extends AbstractApiTest
{
    public function testCopyStatementIsRejectedForStatementOutsideRouteProcedure(): void
    {
        // Arrange
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $ownProcedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $foreignStatement = $this->createForeignStatement();
        $this->enablePermissions(['feature_statement_copy_to_procedure']);
        $statementCountBefore = $this->countEntries(Statement::class);

        // Act: foreign source statement, route + target = own procedure
        $response = $this->sendRequest(
            $this->buildUrl('copy', $foreignStatement->getId(), $ownProcedure->getId(), $ownProcedure->getId()),
            'POST',
            $user,
            $ownProcedure
        );

        // Assert: request rejected, no statement copied
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame($statementCountBefore, $this->countEntries(Statement::class));
    }

    public function testCopyStatementIsRejectedForNotOwnedTargetProcedure(): void
    {
        // Arrange
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $ownProcedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $ownStatement = $this->getStatementReference(LoadStatementData::TEST_STATEMENT);
        $foreignProcedure = $this->createForeignProcedure();
        $this->enablePermissions(['feature_statement_copy_to_procedure']);
        $statementCountBefore = $this->countEntries(Statement::class);

        // Act: own source statement, target = not-owned procedure
        $response = $this->sendRequest(
            $this->buildUrl('copy', $ownStatement->getId(), $ownProcedure->getId(), $foreignProcedure->getId()),
            'POST',
            $user,
            $ownProcedure
        );

        // Assert: request rejected, no statement copied
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame($statementCountBefore, $this->countEntries(Statement::class));
    }

    public function testMoveStatementIsRejectedForStatementOutsideRouteProcedure(): void
    {
        // Arrange
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $ownProcedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $foreignStatement = $this->createForeignStatement();
        $foreignProcedureId = $foreignStatement->getProcedureId();
        $this->enablePermissions(['feature_statement_move_to_procedure']);

        // Act: foreign source statement, route + target = own procedure
        $response = $this->sendRequest(
            $this->buildUrl('move', $foreignStatement->getId(), $ownProcedure->getId(), $ownProcedure->getId()),
            'POST',
            $user,
            $ownProcedure,
            ['deleteVersionHistory' => false]
        );

        // Assert: request rejected, statement stayed in its procedure
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $unmovedStatement = $this->find(Statement::class, $foreignStatement->getId());
        self::assertSame($foreignProcedureId, $unmovedStatement->getProcedureId());
    }

    public function testMoveStatementIsRejectedForNotOwnedTargetProcedure(): void
    {
        // Arrange
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $ownProcedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $ownStatement = $this->getStatementReference(LoadStatementData::TEST_STATEMENT);
        $foreignProcedure = $this->createForeignProcedure();
        $this->enablePermissions(['feature_statement_move_to_procedure']);

        // Act: own source statement, target = not-owned procedure
        $response = $this->sendRequest(
            $this->buildUrl('move', $ownStatement->getId(), $ownProcedure->getId(), $foreignProcedure->getId()),
            'POST',
            $user,
            $ownProcedure,
            ['deleteVersionHistory' => false]
        );

        // Assert: request rejected, statement stayed in its procedure
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $unmovedStatement = $this->find(Statement::class, $ownStatement->getId());
        self::assertSame($ownProcedure->getId(), $unmovedStatement->getProcedureId());
    }

    /**
     * The copy/move guards allow the action only when the current user owns the target
     * procedure (or holds the *_to_foreign_procedure permission). This verifies the
     * ownership predicate the guards rely on: allow for an owned target, deny for a
     * foreign one.
     */
    public function testTargetProcedureOwnershipGate(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $ownedTargetProcedure = $this->getProcedureReference(LoadProcedureData::TEST_PROCEDURE_2);
        $foreignProcedure = $this->createForeignProcedure();
        $this->logIn($user);

        /** @var ProcedureAccessEvaluator $accessEvaluator */
        $accessEvaluator = $this->getContainer()->get(ProcedureAccessEvaluator::class);

        self::assertTrue($accessEvaluator->isOwningProcedure($user, $ownedTargetProcedure));
        self::assertFalse($accessEvaluator->isOwningProcedure($user, $foreignProcedure));
    }

    protected function getServerParameters(): array
    {
        return [];
    }

    private function buildUrl(string $action, string $statementId, string $routeProcedureId, string $targetProcedureId): string
    {
        return sprintf(
            '/api/1.0/statements/%s/%s/%s?targetProcedureId=%s',
            $statementId,
            $action,
            $routeProcedureId,
            $targetProcedureId
        );
    }

    private function createForeignProcedure(): Procedure
    {
        return ProcedureFactory::createOne()->_real();
    }

    private function createForeignStatement(): Statement
    {
        return StatementFactory::createOne(['procedure' => $this->createForeignProcedure()])->_real();
    }
}
