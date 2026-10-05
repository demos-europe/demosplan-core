<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi;

use DateTime;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * The PATCH of /api/3.0/Statement/{id}: the body is read into the UpdateStatement class, every sent
 * field needs its permission, and the answer is the Statement resource printed like a GET.
 */
class StatementPatchApiTest extends AbstractApiTest
{
    private const STATEMENT_ROUTE = '/api/3.0/Statement/';

    /** Opens the route and passes the "may update at all" gate. `area_admin_statement_list` also unlocks the address and id fields. */
    private const CAN_UPDATE = ['feature_json_api_statement', 'feature_statement_assignment', 'area_admin_statement_list'];

    public function testPatchUpdatesAnAllowedFieldAndAnswersWithTheStatement(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch($statement, ['internId' => 'NEW-1'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('NEW-1', $this->getAttributes($response)['internId']);
        self::assertSame('NEW-1', $this->reload($statement)->getInternId());
    }

    public function testPatchUpdatesTheAddressFieldsOfTheSubmitter(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch(
            $statement,
            ['initialOrganisationCity' => 'Hamburg', 'initialOrganisationStreet' => 'Hafenstrasse', 'authorName' => 'New Author'],
            self::CAN_UPDATE
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $reloaded = $this->reload($statement);
        self::assertSame('Hamburg', $reloaded->getMeta()->getOrgaCity());
        self::assertSame('Hafenstrasse', $reloaded->getMeta()->getOrgaStreet());
        self::assertSame('New Author', $reloaded->getMeta()->getAuthorName());
    }

    public function testPatchUpdatesTheMemoWithTheMemoPermission(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch($statement, ['memo' => 'new memo'], [...self::CAN_UPDATE, 'field_statement_memo']);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('new memo', $this->getAttributes($response)['memo']);
        self::assertSame('new memo', $this->reload($statement)->getMemo());
    }

    /**
     * Guards the mapping from the update class to the statement: a property without a changer
     * would end in an error here.
     */
    public function testPatchAcceptsEveryPropertyOfTheUpdateClass(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch(
            $statement,
            [
                'memo'                              => 'all memo',
                'internId'                          => 'ALL-1',
                'authorName'                        => 'All Author',
                'submitName'                        => 'All Submitter',
                'initialOrganisationName'           => 'All Orga',
                'initialOrganisationDepartmentName' => 'All Department',
                'initialOrganisationStreet'         => 'All Street',
                'initialOrganisationHouseNumber'    => '7',
                'initialOrganisationPostalCode'     => '20095',
                'initialOrganisationCity'           => 'Hamburg',
                'submitType'                        => 'email',
                'submitDate'                        => '2025-03-01T10:00:00+00:00',
                'authoredDate'                      => '2025-02-01T10:00:00+00:00',
            ],
            [...self::CAN_UPDATE, 'field_statement_memo']
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $reloaded = $this->reload($statement);
        $meta = $reloaded->getMeta();
        self::assertSame('all memo', $reloaded->getMemo());
        self::assertSame('ALL-1', $reloaded->getInternId());
        self::assertSame('All Author', $meta->getAuthorName());
        self::assertSame('All Submitter', $meta->getSubmitName());
        self::assertSame('All Orga', $meta->getOrgaName());
        self::assertSame('All Department', $meta->getOrgaDepartmentName());
        self::assertSame('All Street', $meta->getOrgaStreet());
        self::assertSame('7', $meta->getHouseNumber());
        self::assertSame('20095', $meta->getOrgaPostalCode());
        self::assertSame('Hamburg', $meta->getOrgaCity());
        self::assertSame('email', $reloaded->getSubmitType());
        self::assertSame('2025-03-01', $reloaded->getSubmitObject()->format('Y-m-d'));
        self::assertSame('2025-02-01', $meta->getAuthoredDateObject()->format('Y-m-d'));
    }

    public function testPatchDoesNotChangeAPropertyThatIsNotPartOfTheUpdateClass(): void
    {
        $statement = $this->createEditableStatement();
        $externId = $statement->getExternId();

        $response = $this->patch($statement, ['externId' => 'HACKED', 'internId' => 'NEW-7'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $reloaded = $this->reload($statement);
        self::assertSame($externId, $reloaded->getExternId());
        self::assertSame('NEW-7', $reloaded->getInternId());
    }

    public function testPatchOfAFieldWithoutItsPermissionIsRejectedAndNothingIsSaved(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch($statement, ['memo' => 'hacked'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('old memo', $this->reload($statement)->getMemo());
    }

    public function testPatchWithAnAllowedAndAForbiddenFieldChangesNothing(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch($statement, ['internId' => 'NEW-2', 'memo' => 'hacked'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        $reloaded = $this->reload($statement);
        self::assertSame('OLD-1', $reloaded->getInternId());
        self::assertSame('old memo', $reloaded->getMemo());
    }

    public function testPatchIsDeniedWithoutAnyUpdatePermission(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch($statement, ['internId' => 'NEW-3'], ['feature_json_api_statement']);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('OLD-1', $this->reload($statement)->getInternId());
    }

    public function testPatchOfAStatementInAnotherProcedureIsNotFound(): void
    {
        $currentProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $foreignStatement = $this->createEditableStatement();

        $response = $this->patch($foreignStatement, ['internId' => 'NEW-4'], self::CAN_UPDATE, $currentProcedure);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('OLD-1', $this->reload($foreignStatement)->getInternId());
    }

    public function testPatchWithAnInternIdThatAlreadyExistsInTheProcedureIsRejected(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $statement = $this->createEditableStatement($procedure);
        $this->createEditableStatement($procedure, [], 'TAKEN');

        $response = $this->patch($statement, ['internId' => 'TAKEN'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('OLD-1', $this->reload($statement)->getInternId());
    }

    public function testPatchSetsAnAuthoredDateAfterTheSubmitDateToTheSubmitDate(): void
    {
        $statement = $this->createEditableStatement();
        $statement->setSubmit(new DateTime('2025-01-10 12:00:00'));
        $this->getEntityManager()->flush();

        $response = $this->patch($statement, ['authoredDate' => '2025-02-01T12:00:00+00:00'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringStartsWith('2025-01-10', $this->getAttributes($response)['authoredDate']);
    }

    public function testPatchOfAnInvalidDateIsRejected(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch($statement, ['submitDate' => 'not a date'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAdminFieldsCannotBeChangedOnAStatementThatIsNotManual(): void
    {
        $statement = $this->createEditableStatement(null, ['manual' => false]);

        $response = $this->patch($statement, ['internId' => 'NEW-5'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('OLD-1', $this->reload($statement)->getInternId());
    }

    public function testTheMemoCanBeChangedOnAStatementThatIsNotManual(): void
    {
        $statement = $this->createEditableStatement(null, ['manual' => false]);

        $response = $this->patch($statement, ['memo' => 'new memo'], [...self::CAN_UPDATE, 'field_statement_memo']);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('new memo', $this->reload($statement)->getMemo());
    }

    public function testTheAnswerHidesAttributesTheUserMayNotSee(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch($statement, ['internId' => 'NEW-6'], self::CAN_UPDATE);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertArrayNotHasKey('memo', $this->getAttributes($response));
    }

    public function testPatchSavesTheChangeHistoryWhenItIsEnabled(): void
    {
        $statement = $this->createEditableStatement();

        $response = $this->patch(
            $statement,
            ['memo' => 'tracked memo'],
            [...self::CAN_UPDATE, 'field_statement_memo', 'feature_statement_content_changes_save']
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $changes = $this->getEntityManager()->getRepository(EntityContentChange::class)->findBy(['entityId' => $statement->getId()]);
        self::assertNotSame([], $changes);
    }

    /**
     * A statement the user may edit: a copy with an original, in the given procedure, manual by default.
     *
     * @param array<string, mixed> $attributes
     */
    private function createEditableStatement(?Procedure $procedure = null, array $attributes = [], string $internId = 'OLD-1'): Statement
    {
        $procedure ??= ProcedureFactory::new()->withDefaultSettings()->create();
        $original = StatementFactory::createOne(['procedure' => $procedure])->_real();
        $original->setInternId($internId);

        $statementProxy = StatementFactory::createOne(array_merge(
            ['procedure' => $procedure, 'original' => $original, 'manual' => true, 'memo' => 'old memo'],
            $attributes
        ));
        $statement = $statementProxy->_real();
        $statement->getMeta()->setOrgaCity('Berlin');
        $statementProxy->_save();

        return $statement;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string>         $permissions
     */
    private function patch(Statement $statement, array $attributes, array $permissions, ?Procedure $currentProcedure = null): Response
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions($permissions);
        $this->loginUserForApiPlatform($user);

        return $this->sendRequest(
            self::STATEMENT_ROUTE.$statement->getId(),
            'PATCH',
            $user,
            $currentProcedure ?? $statement->getProcedure(),
            ['data' => ['type' => 'Statement', 'id' => $statement->getId(), 'attributes' => $attributes]]
        );
    }

    private function reload(Statement $statement): Statement
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $reloaded = $entityManager->find(Statement::class, $statement->getId());
        self::assertInstanceOf(Statement::class, $reloaded);

        return $reloaded;
    }

    /**
     * @return array<string, mixed>
     */
    private function getAttributes(Response $response): array
    {
        return Json::decodeToArray((string) $response->getContent())['data']['attributes'] ?? [];
    }

    /**
     * /api/3.0/* routes sit behind the `api_platform` firewall (context: main, form-login
     * authenticator), not the stateless JWT `api` firewall AbstractApiTest::sendRequest() targets —
     * so authentication needs the session-based test login, not an X-JWT-Authorization header.
     */
    private function loginUserForApiPlatform(User $user): void
    {
        $this->client->loginUser($user, 'main');
    }

    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        $headers = ['CONTENT_TYPE' => 'application/vnd.api+json'];
        if (null !== $procedure) {
            $headers['HTTP_X_DEMOSPLAN_PROCEDURE_ID'] = $procedure->getId();
        }

        return $headers;
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
