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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * Attribute level permissions of the single-item route of /api/3.0/Statement: a field is only part
 * of the response if the user holds the permission its `perm:read:` group names.
 */
class StatementResourceApiTest extends AbstractApiTest
{
    private const STATEMENT_ROUTE = '/api/3.0/Statement/';

    /** Grants access to the route itself, nothing field specific. */
    private const ACCESS_ONLY = ['feature_json_api_statement'];

    private const OPEN_ATTRIBUTES = [
        'externId',
        'internId',
        'initialOrganisationName',
        'initialOrganisationDepartmentName',
        'initialOrganisationStreet',
        'initialOrganisationHouseNumber',
        'initialOrganisationPostalCode',
        'initialOrganisationCity',
        'authoredDate',
        'submitDate',
        'submitType',
    ];

    public function testOpenAttributesAreReturnedWithAccessPermissionOnly(): void
    {
        $attributes = $this->getStatementAttributes(self::ACCESS_ONLY);

        foreach (self::OPEN_ATTRIBUTES as $name) {
            self::assertArrayHasKey($name, $attributes, "Open attribute $name is missing");
        }
    }

    public function testMemoIsHiddenWithoutMemoPermission(): void
    {
        $attributes = $this->getStatementAttributes(self::ACCESS_ONLY);

        self::assertArrayNotHasKey('memo', $attributes);
    }

    public function testMemoIsReturnedWithMemoPermission(): void
    {
        $attributes = $this->getStatementAttributes([...self::ACCESS_ONLY, 'field_statement_memo']);

        self::assertSame('secret memo', $attributes['memo']);
    }

    public function testProcessingStatusIsHiddenWithoutSegmentationPermission(): void
    {
        $attributes = $this->getStatementAttributes(self::ACCESS_ONLY);

        self::assertArrayNotHasKey('status', $attributes);
    }

    public function testProcessingStatusIsReturnedWithSegmentationPermission(): void
    {
        $attributes = $this->getStatementAttributes([...self::ACCESS_ONLY, 'area_statement_segmentation']);

        self::assertArrayHasKey('status', $attributes);
    }

    public function testAuthorAndSubmitNameAreReturnedWithAssessmentPermission(): void
    {
        $attributes = $this->getStatementAttributes(self::ACCESS_ONLY);

        self::assertArrayHasKey('authorName', $attributes);
        self::assertArrayHasKey('submitName', $attributes);
    }

    public function testAuthorAndSubmitNameAreHiddenForSubmitterSearchWithoutAssessmentPermission(): void
    {
        $attributes = $this->getStatementAttributes(['area_search_submitter_in_procedures']);

        self::assertArrayNotHasKey('authorName', $attributes);
        self::assertArrayNotHasKey('submitName', $attributes);
        self::assertArrayHasKey('externId', $attributes);
    }

    public function testIsSubmittedByCitizenIsHiddenWithoutOneOfItsPermissions(): void
    {
        $attributes = $this->getStatementAttributes(self::ACCESS_ONLY);

        self::assertArrayNotHasKey('isSubmittedByCitizen', $attributes);
    }

    public function testIsSubmittedByCitizenIsReturnedWithOneOfItsPermissions(): void
    {
        $attributes = $this->getStatementAttributes([...self::ACCESS_ONLY, 'area_admin_submitters']);

        self::assertArrayHasKey('isSubmittedByCitizen', $attributes);
    }

    /**
     * @param list<string> $permissions permissions enabled on top of the test user's role permissions
     *
     * @return array<string, mixed> the `attributes` of the returned statement
     */
    private function getStatementAttributes(array $permissions): array
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $statement = $this->createFullyPopulatedStatement($procedure);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions($permissions);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::STATEMENT_ROUTE.$statement->getId(), 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return Json::decodeToArray((string) $response->getContent())['data']['attributes'];
    }

    /**
     * API Platform leaves out null values, so every attribute under test needs a value to be able
     * to tell "hidden by permission" from "empty".
     */
    private function createFullyPopulatedStatement(Procedure $procedure): Statement
    {
        $statementProxy = StatementFactory::createOne(['procedure' => $procedure, 'memo' => 'secret memo']);
        $statement = $statementProxy->_real();
        $statement->setInternId('INT-1');
        $meta = $statement->getMeta();
        $meta->setAuthorName('Author Name');
        $meta->setSubmitName('Submit Name');
        $meta->setOrgaName('Orga Name');
        $meta->setOrgaDepartmentName('Department');
        $meta->setOrgaStreet('Street');
        $meta->setHouseNumber('1');
        $meta->setOrgaPostalCode('12345');
        $meta->setOrgaCity('City');
        $meta->setAuthoredDate(new DateTime());
        $statementProxy->_save();

        return $statement;
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
        $headers = [];
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
