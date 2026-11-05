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

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\AbstractApiTest;

class DemosPlanClaimAPIControllerTest extends AbstractApiTest
{
    public function testAssignToSelfConfirmsWithPersonalMessage(): void
    {
        // Arrange
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $statement = $this->createStatement(null);

        // Act
        $response = $this->sendAssignment($statement, $user, $user->getId());

        // Assert
        self::assertSame(
            [$this->translate('confirm.statement.assignment.assigned')],
            $this->getConfirmMessages($response)
        );
    }

    public function testAssignToOtherUserConfirmsWithAssigneeName(): void
    {
        // Arrange
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $otherUser = $this->createUser();
        $statement = $this->createStatement(null);

        // Act
        $response = $this->sendAssignment($statement, $user, $otherUser->getId());

        // Assert
        self::assertSame(
            [$this->translate('confirm.statement.assignment.assigned.to', ['name' => 'Selta Seewind'])],
            $this->getConfirmMessages($response)
        );
    }

    public function testReassignConfirmsWithChangedMessage(): void
    {
        // Arrange
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $otherUser = $this->createUser();
        $statement = $this->createStatement($user);

        // Act
        $response = $this->sendAssignment($statement, $user, $otherUser->getId());

        // Assert
        self::assertSame(
            [$this->translate('confirm.statement.assignment.changed')],
            $this->getConfirmMessages($response)
        );
    }

    public function testUnassignConfirmsWithUnassignedMessage(): void
    {
        // Arrange
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $statement = $this->createStatement($user);

        // Act: the frontend sends an empty id to unassign
        $response = $this->sendAssignment($statement, $user, '');

        // Assert
        self::assertSame(
            [$this->translate('confirm.statement.assignment.unassigned')],
            $this->getConfirmMessages($response)
        );
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT'  => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ];
    }

    private function sendAssignment(Statement $statement, User $user, string $assigneeId): Response
    {
        $this->enablePermissions(['feature_statement_claim']);

        $response = $this->sendRequest(
            sprintf('/api/1.0/statement/%s/relationships/assignee', $statement->getId()),
            'PATCH',
            $user,
            $statement->getProcedure(),
            ['data' => ['type' => 'user', 'id' => $assigneeId]]
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        return $response;
    }

    private function createStatement(?User $assignee): Statement
    {
        /** @var Procedure $procedure */
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();

        // Original statements are locked against updates; the list works on their copies.
        $original = StatementFactory::createOne(['procedure' => $procedure]);

        /** @var Statement $statement */
        $statement = StatementFactory::createOne([
            'procedure' => $procedure,
            'original'  => $original,
            'assignee'  => $assignee,
        ])->_real();

        return $statement;
    }

    private function createUser(): User
    {
        $orga = OrgaFactory::createOne(['name' => 'Amt Seewind']);
        $user = UserFactory::createOne([
            'firstname' => 'Selta',
            'lastname'  => 'Seewind',
            'orga'      => $orga,
            'deleted'   => false,
        ]);
        $orga->_real()->addUser($user->_real());
        $orga->_save();

        /** @var User $realUser */
        $realUser = $user->_real();

        return $realUser;
    }

    /**
     * @return list<string>
     *
     * @throws JsonException
     */
    private function getConfirmMessages(Response $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);

        return Json::decodeToArray($content)['meta']['messages']['confirm'] ?? [];
    }

    private function translate(string $key, array $parameters = []): string
    {
        return $this->getContainer()->get(TranslatorInterface::class)->trans($key, $parameters);
    }
}
