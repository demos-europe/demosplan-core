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

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * CUSTOMER-scoped access-condition behaviour (restricting rows to the current customer) is covered
 * separately in {@see \Tests\Core\CustomField\CustomFieldAccessCheckerTest}, which calls
 * CustomFieldAccessChecker directly rather than through HTTP: combining a session login with a
 * Host-header-driven customer switch (needed to change the "current customer" for a real dispatched
 * request) breaks session recognition in this test harness, same as it does for the other resources
 * that need customer switching (see InstitutionTagResourceTypeTest / InvitableInstitutionResourceTypeTest,
 * both of which call their service directly instead of over HTTP for the same reason).
 */
class CustomFieldResourceApiTest extends AbstractApiTest
{
    private const CUSTOM_FIELD_COLLECTION_ROUTE = '/api/3.0/CustomField';
    private const CUSTOM_FIELD_ITEM_ROUTE = '/api/3.0/CustomField/';
    private const PERMISSIONS = ['feature_organisations_custom_fields'];

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

    public function testGetCollectionReturnsFieldsScopedToProcedureAndTargetEntity(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $segmentField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity('SEGMENT')
            ->asTextField('Segment notes')
            ->create();
        CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity('STATEMENT')
            ->asTextField('Statement notes')
            ->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::CUSTOM_FIELD_COLLECTION_ROUTE.'?sourceEntity=PROCEDURE&sourceEntityId='.$procedure->getId().'&targetEntity=SEGMENT',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];

        self::assertSame([$segmentField->getId()], array_column($data, 'id'));
    }

    public function testGetCollectionIsDeniedWithoutPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity('SEGMENT')
            ->asTextField('Segment notes')
            ->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::CUSTOM_FIELD_COLLECTION_ROUTE.'?sourceEntity=PROCEDURE&sourceEntityId='.$procedure->getId().'&targetEntity=SEGMENT',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringNotContainsString('Segment notes', $response->getContent());
    }

    /**
     * `sourceEntity` and `targetEntity` are declared `required: true` on the `GetCollection`
     * QueryParameters, so API Platform's own parameter validation rejects the request (422)
     * before CustomFieldProvider ever runs.
     */
    public function testGetCollectionRequiresSourceEntityAndTargetEntity(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::CUSTOM_FIELD_COLLECTION_ROUTE.'?sourceEntity=PROCEDURE&sourceEntityId='.$procedure->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    /**
     * `sourceEntityId` is only optional for `sourceEntity=CUSTOMER` — for every other source it is
     * required, but that condition depends on `sourceEntity`'s value so it can't be a plain
     * `required` flag on the QueryParameter; it's checked manually in CustomFieldProvider (400).
     */
    public function testGetCollectionRequiresSourceEntityIdUnlessCustomer(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::CUSTOM_FIELD_COLLECTION_ROUTE.'?sourceEntity=PROCEDURE&targetEntity=SEGMENT',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testGetCollectionSupportsSparseFieldsets(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity('SEGMENT')
            ->asTextField('Segment notes')
            ->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::CUSTOM_FIELD_COLLECTION_ROUTE
                .'?sourceEntity=PROCEDURE&sourceEntityId='.$procedure->getId().'&targetEntity=SEGMENT'
                .'&fields[CustomField]=name,fieldType',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $attributes = Json::decodeToArray($content)['data'][0]['attributes'];

        self::assertSame(['name', 'fieldType'], array_keys($attributes));
    }

    public function testGetReturnsCustomFieldById(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity('SEGMENT')
            ->asTextField('Segment notes')
            ->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::CUSTOM_FIELD_ITEM_ROUTE.$customField->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $attributes = Json::decodeToArray($content)['data']['attributes'];

        self::assertSame('Segment notes', $attributes['name']);
        self::assertSame('text', $attributes['fieldType']);
        self::assertSame('PROCEDURE', $attributes['sourceEntity']);
        self::assertSame('SEGMENT', $attributes['targetEntity']);
        self::assertSame($procedure->getId(), $attributes['sourceEntityId']);
    }

    public function testGetIsDeniedWithoutPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity('SEGMENT')
            ->asTextField('Segment notes')
            ->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::CUSTOM_FIELD_ITEM_ROUTE.$customField->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringNotContainsString('Segment notes', $response->getContent());
    }

    public function testGetReturnsNotFoundForNonexistentId(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::CUSTOM_FIELD_ITEM_ROUTE.'00000000-0000-0000-0000-000000000000',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
