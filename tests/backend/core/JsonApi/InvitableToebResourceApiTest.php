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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * The institutions of the current customer that can be invited to the current procedure. The customer is created
 * for each test, so the lists contain exactly the institutions the test created.
 */
class InvitableToebResourceApiTest extends AbstractApiTest
{
    private const ROUTE = '/api/3.0/InvitableToeb';
    private const PHONE_QUERY = '?include=locationContacts&fields[InvitableToeb]=locationContacts&fields[locationContacts]=street,phone';

    private ?User $user = null;
    private ?Customer $currentCustomer = null;
    private ?OrgaType $publicAgencyType = null;
    private ?Procedure $procedure = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentCustomer = CustomerFactory::createOne(['subdomain' => 'invitable-toeb-'.uniqid()])->_real();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($this->currentCustomer->getSubdomain());
        $this->user = $this->getUserWithRolesInCurrentCustomer();
        $this->publicAgencyType = OrgaTypeFactory::createOne(['name' => OrgaTypeInterface::PUBLIC_AGENCY])->_real();
        $this->procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
    }

    /**
     * The session login resolves the roles of the user in the current customer, so the user of the fixtures gets the
     * roles it has in the fixture customer in the customer of this test as well.
     */
    private function getUserWithRolesInCurrentCustomer(): User
    {
        $entityManager = $this->getApiEntityManager();
        $user = $entityManager->find(User::class, $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());
        $fixtureCustomer = $this->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY)->getStatusInCustomers()->first()->getCustomer();
        foreach ($user->getDplanroles($fixtureCustomer) as $role) {
            $user->addDplanrole($role, $this->currentCustomer);
        }
        $entityManager->flush();

        return $user;
    }

    /**
     * /api/3.0/* routes sit behind the session based `main` firewall, see TagResourceApiTest.
     */
    private function loginForApiPlatform(): void
    {
        $this->client->loginUser($this->user, 'main');
    }

    /**
     * Only the procedure header, the JWT header is meant for the stateless `api` firewall, see TagResourceApiTest.
     */
    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        return null === $procedure ? [] : ['HTTP_X_DEMOSPLAN_PROCEDURE_ID' => $procedure->getId()];
    }

    protected function getServerParameters(): array
    {
        return ['HTTP_ACCEPT' => 'application/vnd.api+json'];
    }

    /**
     * @param list<string> $additionalPermissions
     */
    private function enableInvitablePermissions(array $additionalPermissions = []): void
    {
        $this->enablePermissions(['area_main_procedures', 'area_admin_invitable_institution', ...$additionalPermissions]);
        $this->loginForApiPlatform();
    }

    public function testListReturnsInstitutionWithItsAttributes(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga(name: 'Amt fuer Test', roleCode: RoleInterface::PUBLIC_AGENCY_WORKER);
        $orga->setParticipationEmail('beteiligung@example.org');
        $this->getApiEntityManager()->flush();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = Json::decodeToArray($response->getContent())['data'];
        self::assertCount(1, $data);
        self::assertSame($orga->getId(), $data[0]['id']);
        self::assertSame('Amt fuer Test', $data[0]['attributes']['legalName']);
        self::assertSame('beteiligung@example.org', $data[0]['attributes']['participationFeedbackEmailAddress']);
        self::assertTrue($data[0]['attributes']['hasActiveUsers']);
    }

    public function testListReturnsHasActiveUsersFalseForInstitutionWithoutInstitutionUser(): void
    {
        // Arrange
        $this->createInvitableOrga();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = Json::decodeToArray($response->getContent())['data'];
        self::assertCount(1, $data);
        self::assertFalse($data[0]['attributes']['hasActiveUsers']);
    }

    public function testListHidesInstitutionsInvitedToTheProcedure(): void
    {
        // Arrange
        $invited = $this->createInvitableOrga();
        $notInvited = $this->createInvitableOrga();
        $this->procedure->addOrganisation($invited);
        $this->getApiEntityManager()->flush();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame([$notInvited->getId()], $this->getIds($response));
    }

    public function testListHidesInstitutionsThatAreNotListedNotAcceptedDeletedOrOfAnotherTypeOrCustomer(): void
    {
        // Arrange
        $listed = $this->createInvitableOrga();
        $this->createInvitableOrga(showlist: false);
        $this->createInvitableOrga(status: OrgaStatusInCustomerInterface::STATUS_PENDING);
        $this->createInvitableOrga(deleted: true);
        $this->createInvitableOrga(type: OrgaTypeFactory::createOne(['name' => OrgaTypeInterface::MUNICIPALITY])->_real());
        $this->createInvitableOrga(acceptedInCustomer: CustomerFactory::createOne(['subdomain' => 'other-'.uniqid()])->_real());
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame([$listed->getId()], $this->getIds($response));
    }

    public function testListIsEmptyWithoutProcedure(): void
    {
        // Arrange
        $this->createInvitableOrga();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, null);

        // Assert
        self::assertSame([], $this->getIds($response));
    }

    public function testListIsOrderedByNameAscending(): void
    {
        // Arrange: created in a different order than their names
        $c = $this->createInvitableOrga(name: 'C Amt');
        $a = $this->createInvitableOrga(name: 'A Amt');
        $b = $this->createInvitableOrga(name: 'B Amt');
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame([$a->getId(), $b->getId(), $c->getId()], $this->getIds($response));
    }

    public function testListIsPaginated(): void
    {
        // Arrange
        $this->createInvitableOrga(name: 'C Amt');
        $this->createInvitableOrga(name: 'A Amt');
        $b = $this->createInvitableOrga(name: 'B Amt');
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?pagination=true&itemsPerPage=1&page=2', 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = Json::decodeToArray($response->getContent());
        self::assertSame([$b->getId()], array_column($content['data'], 'id'));
        self::assertSame(3, $content['meta']['totalItems']);
        self::assertSame(1, $content['meta']['itemsPerPage']);
    }

    public function testListRestrictsAttributesToRequestedFields(): void
    {
        // Arrange
        $this->createInvitableOrga();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(
            self::ROUTE.'?fields[InvitableToeb]=legalName,hasActiveUsers',
            'GET',
            $this->user,
            $this->procedure
        );

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = Json::decodeToArray($response->getContent())['data'];
        self::assertSame(['legalName', 'hasActiveUsers'], array_keys($data[0]['attributes']));
    }

    public function testListIsDeniedWithoutTheInvitableInstitutionArea(): void
    {
        // Arrange: only one of the two required permissions
        $this->createInvitableOrga();
        $this->enablePermissions(['area_main_procedures']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testGetReturnsInstitutionAndHidesInvitedOne(): void
    {
        // Arrange
        $notInvited = $this->createInvitableOrga(name: 'Amt fuer Get');
        $invited = $this->createInvitableOrga();
        $this->procedure->addOrganisation($invited);
        $this->getApiEntityManager()->flush();
        $this->enableInvitablePermissions();

        // Act
        $found = $this->sendRequest(self::ROUTE.'/'.$notInvited->getId(), 'GET', $this->user, $this->procedure);
        $hidden = $this->sendRequest(self::ROUTE.'/'.$invited->getId(), 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $found->getStatusCode());
        self::assertSame('Amt fuer Get', Json::decodeToArray($found->getContent())['data']['attributes']['legalName']);
        self::assertSame(Response::HTTP_NOT_FOUND, $hidden->getStatusCode());
    }

    /**
     * The request OrganisationTable.vue sends: sparse fieldsets for the main resource by type and for the included
     * resources by relationship path.
     */
    public function testListIncludesOwnCustomerTagsAndLocationContactsWithSparseFieldsets(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga(name: 'Amt mit Adresse');
        $ownTag = $this->assignTag($orga, 'own-tag', $this->currentCustomer);
        $this->assignTag($orga, 'foreign-tag', CustomerFactory::createOne(['subdomain' => 'other-'.uniqid()])->_real());
        $address = $this->addAddress($orga);
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions(['feature_institution_tag_read']);

        // Act
        $response = $this->sendRequest(
            self::ROUTE.'?include=assignedTags,locationContacts&fields[InvitableToeb]=legalName,assignedTags,locationContacts'
            .'&fields[assignedTags]=name&fields[locationContacts]=street,postalcode,city',
            'GET',
            $this->user,
            $this->procedure
        );

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = Json::decodeToArray($response->getContent());
        self::assertCount(1, $content['data']);
        $relationships = $content['data'][0]['relationships'];
        self::assertSame([$ownTag->getId()], array_column($relationships['assignedTags']['data'], 'id'));
        self::assertSame([$address->getId()], array_column($relationships['locationContacts']['data'], 'id'));
        $included = [];
        foreach ($content['included'] as $resource) {
            $included[$resource['type']][$resource['id']] = $resource['attributes'];
        }
        self::assertSame([$ownTag->getId() => ['name' => 'own-tag']], $included['InstitutionTag']);
        self::assertSame(
            [$address->getId() => ['street' => 'Teststrasse 1', 'postalcode' => '20095', 'city' => 'Hamburg']],
            $included['InstitutionLocationContact']
        );
    }

    public function testListExposesThePhoneOfTheLocationContactWithPermission(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $this->addAddress($orga);
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions(['field_organisation_phone']);

        // Act
        $response = $this->sendRequest(self::ROUTE.self::PHONE_QUERY, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $included = Json::decodeToArray($response->getContent())['included'];
        self::assertSame(['street' => 'Teststrasse 1', 'phone' => '040 123456'], $included[0]['attributes']);
    }

    /**
     * API Platform leaves null values out of the response, so the phone is not part of the contact at all.
     */
    public function testListOmitsThePhoneOfTheLocationContactWithoutPermission(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $this->addAddress($orga);
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.self::PHONE_QUERY, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $included = Json::decodeToArray($response->getContent())['included'];
        self::assertSame(['street' => 'Teststrasse 1'], $included[0]['attributes']);
    }

    public function testListReturnsNoTagsWithoutTagPermission(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $this->assignTag($orga, 'own-tag', $this->currentCustomer);
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?include=assignedTags', 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = Json::decodeToArray($response->getContent());
        self::assertSame([], $content['data'][0]['relationships']['assignedTags']['data']);
        self::assertEmpty($content['included'] ?? []);
    }

    public function testGetReturnsTagsAndLocationContacts(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $tag = $this->assignTag($orga, 'own-tag', $this->currentCustomer);
        $address = $this->addAddress($orga);
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions(['feature_institution_tag_read']);

        // Act
        $response = $this->sendRequest(self::ROUTE.'/'.$orga->getId(), 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $relationships = Json::decodeToArray($response->getContent())['data']['relationships'];
        self::assertSame([$tag->getId()], array_column($relationships['assignedTags']['data'], 'id'));
        self::assertSame([$address->getId()], array_column($relationships['locationContacts']['data'], 'id'));
    }

    /**
     * @return array<string, array{string, string, string}> attribute, permission, entity setter
     */
    public static function permissionBasedAttributeProvider(): array
    {
        return [
            'competence'     => ['competenceDescription', 'field_organisation_competence', 'setCompetence'],
            'cc emails'      => ['ccEmailAddresses', 'field_organisation_email2_cc', 'setCcEmail2'],
            'contact person' => ['contactPerson', 'field_organisation_contact_person', 'setContactPerson'],
        ];
    }

    #[DataProvider('permissionBasedAttributeProvider')]
    public function testListExposesPermissionBasedAttributeWithPermission(string $attribute, string $permission, string $setter): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $orga->$setter('some value');
        $this->getApiEntityManager()->flush();
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions([$permission]);

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('some value', $this->getAttributes($response, $orga)[$attribute]);
    }

    /**
     * API Platform leaves null values out of the response, so the attribute is not part of it at all.
     */
    #[DataProvider('permissionBasedAttributeProvider')]
    public function testListOmitsPermissionBasedAttributeWithoutPermission(string $attribute, string $permission, string $setter): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $orga->$setter('some value');
        $this->getApiEntityManager()->flush();
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertArrayNotHasKey($attribute, $this->getAttributes($response, $orga));
    }

    public function testGetExposesPermissionBasedAttribute(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $orga->setCompetence('some value');
        $this->getApiEntityManager()->flush();
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions(['field_organisation_competence']);

        // Act
        $response = $this->sendRequest(self::ROUTE.'/'.$orga->getId(), 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('some value', Json::decodeToArray($response->getContent())['data']['attributes']['competenceDescription']);
    }

    public function testSearchMatchesTheNameCaseInsensitively(): void
    {
        // Arrange
        $match = $this->createInvitableOrga('Amt fuer Wasser');
        $other = $this->createInvitableOrga('Amt fuer Luft');
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?search=WASSER', 'GET', $this->user, $this->procedure);

        // Assert
        $ids = $this->getIds($response);
        self::assertContains($match->getId(), $ids);
        self::assertNotContains($other->getId(), $ids);
    }

    public function testSearchTreatsWildcardsAsPlainCharacters(): void
    {
        // Arrange
        $other = $this->createInvitableOrga('Amt fuer Luft');
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?search=%25', 'GET', $this->user, $this->procedure);

        // Assert
        self::assertNotContains($other->getId(), $this->getIds($response));
    }

    public function testSearchStillHidesInvitedInstitutions(): void
    {
        // Arrange
        $invited = $this->createInvitableOrga('Amt fuer Wasser');
        $this->procedure->addOrganisation($invited);
        $this->getApiEntityManager()->flush();
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?search=Wasser', 'GET', $this->user, $this->procedure);

        // Assert
        self::assertNotContains($invited->getId(), $this->getIds($response));
    }

    public function testSearchMatchesTheCompetence(): void
    {
        // Arrange
        $match = $this->createInvitableOrga('Amt A');
        $match->setCompetence('Gewaesserschutz');
        $other = $this->createInvitableOrga('Amt B');
        $this->getApiEntityManager()->flush();
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?search=gewaesser', 'GET', $this->user, $this->procedure);

        // Assert
        $ids = $this->getIds($response);
        self::assertContains($match->getId(), $ids);
        self::assertNotContains($other->getId(), $ids);
    }

    public function testSearchMatchesATagNameAndReturnsTheInstitutionOnce(): void
    {
        // Arrange: two matching tags must not return the institution twice
        $match = $this->createInvitableOrga('Amt A');
        $this->assignTag($match, 'Umwelt-1', $this->currentCustomer);
        $this->assignTag($match, 'Umwelt-2', $this->currentCustomer);
        $other = $this->createInvitableOrga('Amt B');
        $this->assignTag($other, 'Verkehr', $this->currentCustomer);
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?search=umwelt&pagination=true', 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame([$match->getId()], $this->getIds($response));
        self::assertSame(1, Json::decodeToArray($response->getContent())['meta']['totalItems']);
    }

    /**
     * An institution without any tag must still be found by its name or competence.
     */
    public function testSearchFindsInstitutionsWithoutTagsNextToTaggedOnes(): void
    {
        // Arrange
        $untagged = $this->createInvitableOrga('Amt Wasser');
        $tagged = $this->createInvitableOrga('Amt A');
        $this->assignTag($tagged, 'Wasser', $this->currentCustomer);
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?search=wasser', 'GET', $this->user, $this->procedure);

        // Assert
        self::assertEqualsCanonicalizing([$untagged->getId(), $tagged->getId()], $this->getIds($response));
    }

    public function testListExposesTheCustomFieldValuesWithPermission(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $this->setCustomFieldValue($orga, 'custom-field-id', 'some note');
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions(['field_organisations_custom_fields']);

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(
            [['id' => 'custom-field-id', 'value' => 'some note']],
            $this->getAttributes($response, $orga)['customFields']
        );
    }

    /**
     * API Platform leaves null values out of the response, so the attribute is not part of it at all.
     */
    public function testListOmitsTheCustomFieldValuesWithoutPermission(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $this->setCustomFieldValue($orga, 'custom-field-id', 'some note');
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertArrayNotHasKey('customFields', $this->getAttributes($response, $orga));
    }

    public function testGetExposesTheCustomFieldValuesWithPermission(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga();
        $this->setCustomFieldValue($orga, 'custom-field-id', 'some note');
        $this->getApiEntityManager()->clear();
        $this->enableInvitablePermissions(['field_organisations_custom_fields']);

        // Act
        $response = $this->sendRequest(self::ROUTE.'/'.$orga->getId(), 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            [['id' => 'custom-field-id', 'value' => 'some note']],
            Json::decodeToArray($response->getContent())['data']['attributes']['customFields']
        );
    }

    private function setCustomFieldValue(Orga $orga, string $customFieldId, string $value): void
    {
        $customFieldValue = new CustomFieldValue();
        $customFieldValue->setId($customFieldId);
        $customFieldValue->setValue($value);
        $customFieldValues = new CustomFieldValuesList();
        $customFieldValues->addCustomFieldValue($customFieldValue);
        $orga->setCustomFields($customFieldValues);
        $this->getApiEntityManager()->flush();
    }

    /**
     * @return array<string, mixed> the attributes of the given institution in a list response
     */
    private function getAttributes(Response $response, Orga $orga): array
    {
        $rows = array_column(Json::decodeToArray($response->getContent())['data'], 'attributes', 'id');

        return $rows[$orga->getId()];
    }

    /**
     * @return list<string> the ids of the listed institutions, in the order of the response
     */
    private function getIds(Response $response): array
    {
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        return array_column(Json::decodeToArray($response->getContent())['data'], 'id');
    }

    /**
     * Creates an institution that can be invited: not deleted, listed and accepted as public agency in the current
     * customer, unless a parameter says otherwise. It has one user holding the given role in the current customer,
     * or no user at all if the role is null.
     */
    private function createInvitableOrga(
        ?string $name = null,
        ?string $roleCode = null,
        bool $deleted = false,
        bool $showlist = true,
        string $status = OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
        ?OrgaType $type = null,
        ?Customer $acceptedInCustomer = null,
    ): Orga {
        $orgaAttributes = ['deleted' => $deleted, 'showlist' => true];
        if (null !== $name) {
            $orgaAttributes['name'] = $name;
        }
        $orga = OrgaFactory::createOne($orgaAttributes)->_real();
        $statusInCustomer = OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $acceptedInCustomer ?? $this->currentCustomer,
            'orgaType' => $type ?? $this->publicAgencyType,
            'status'   => $status,
            'showlist' => $showlist,
        ])->_real();
        $orga->addStatusInCustomer($statusInCustomer);

        if (null !== $roleCode) {
            $role = $this->getContainer()->get(RoleHandler::class)->getUserRolesByCodes([$roleCode])[0];
            $user = UserFactory::createOne(['deleted' => false])->_real();
            $orga->addUser($user);
            $user->addDplanrole($role, $this->currentCustomer);
        }
        $this->getApiEntityManager()->flush();

        return $orga;
    }

    /**
     * Assigns a new tag, which belongs to a category of the given customer, to the institution.
     */
    private function assignTag(Orga $orga, string $label, Customer $customer): InstitutionTag
    {
        $tag = InstitutionTagFactory::createOne([
            'label'    => $label,
            'category' => InstitutionTagCategoryFactory::new(['customer' => $customer]),
        ])->_real();
        $orga->addAssignedTag($tag);
        $this->getApiEntityManager()->flush();

        return $tag;
    }

    private function addAddress(Orga $orga): Address
    {
        $address = new Address();
        $address->setStreet('Teststrasse 1');
        $address->setPostalcode('20095');
        $address->setCity('Hamburg');
        $address->setPhone('040 123456');
        $entityManager = $this->getApiEntityManager();
        $entityManager->persist($address);
        $orga->addAddress($address);
        $entityManager->flush();

        return $address;
    }

    /**
     * The kernel is rebooted after the fixtures are loaded, see AbstractApiTest::setUp(). Only the entity manager of
     * the current container knows the entities created in this test.
     */
    private function getApiEntityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }
}
