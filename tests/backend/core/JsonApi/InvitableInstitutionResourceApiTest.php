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
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class InvitableInstitutionResourceApiTest extends AbstractApiTest
{
    private const ROUTE = '/api/3.0/InvitableInstitution';

    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->makeCustomerCurrent($this->getCurrentCustomerOf($this->getInstitution()));
    }

    /**
     * /api/3.0/* routes sit behind the session based `main` firewall, see TagResourceApiTest.
     */
    private function loginForApiPlatform(): void
    {
        $this->client->loginUser($this->user, 'main');
    }

    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        return [];
    }

    protected function getServerParameters(): array
    {
        return ['HTTP_ACCEPT' => 'application/vnd.api+json'];
    }

    public function testGetReturnsInstitutionWithOwnCustomerTagsOnly(): void
    {
        $institution = $this->getInstitution();
        $currentCustomer = $this->getCurrentCustomerOf($institution);
        $ownTag = InstitutionTagFactory::createOne([
            'label'    => 'own-tag',
            'category' => InstitutionTagCategoryFactory::new(['customer' => $currentCustomer]),
        ])->_real();
        $foreignTag = InstitutionTagFactory::createOne([
            'label'    => 'foreign-tag',
            'category' => InstitutionTagCategoryFactory::new([
                'customer' => CustomerFactory::new(['subdomain' => 'other-customer-'.uniqid()]),
            ]),
        ])->_real();
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $managedInstitution = $entityManager->find(Orga::class, $institution->getId());
        $managedInstitution->addAssignedTag($ownTag);
        $managedInstitution->addAssignedTag($foreignTag);
        $managedInstitution->setParticipationEmail('beteiligung@example.org');
        $entityManager->flush();
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        $response = $this->sendRequest(self::ROUTE.'/'.$institution->getId(), 'GET', $this->user, null);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = Json::decodeToArray($response->getContent())['data'];
        self::assertSame($institution->getName(), $data['attributes']['name']);
        self::assertSame('beteiligung@example.org', $data['attributes']['participationFeedbackEmailAddress']);
        $tagIds = array_column($data['relationships']['assignedTags']['data'], 'id');
        self::assertSame([$ownTag->getId()], $tagIds);
    }

    /**
     * The request InstitutionList.vue sends: sparse fieldsets keyed by type for the main resource and by
     * relationship path for the included ones, as API Platform expects them.
     */
    public function testListReturnsIncludedTagsAndCategoriesWithSparseFieldsets(): void
    {
        $institution = $this->getInstitution();
        $currentCustomer = $this->getCurrentCustomerOf($institution);
        $tag = InstitutionTagFactory::createOne([
            'label'    => 'own-tag',
            'category' => InstitutionTagCategoryFactory::new(['name' => 'own-category', 'customer' => $currentCustomer]),
        ])->_real();
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $managedInstitution = $entityManager->find(Orga::class, $institution->getId());
        $managedInstitution->addAssignedTag($tag);
        $managedInstitution->setParticipationEmail('beteiligung@example.org');
        $entityManager->flush();
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        $response = $this->sendRequest(
            self::ROUTE.'?include=assignedTags,assignedTags.category'
            .'&fields[InvitableInstitution]=name,createdDate,participationFeedbackEmailAddress,assignedTags&fields[assignedTags]=name,category&fields[assignedTags.category]=name',
            'GET',
            $this->user,
            null
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = Json::decodeToArray($response->getContent());
        $row = current(array_filter($content['data'], fn (array $d): bool => $d['id'] === $institution->getId()));
        self::assertSame(['name', 'createdDate', 'participationFeedbackEmailAddress'], array_keys($row['attributes']));
        self::assertSame('beteiligung@example.org', $row['attributes']['participationFeedbackEmailAddress']);
        $included = [];
        foreach ($content['included'] as $resource) {
            $included[$resource['type']] = $resource['attributes']['name'];
        }
        self::assertSame(['InstitutionTag' => 'own-tag', 'InstitutionTagCategory' => 'own-category'], $included);
    }

    public function testListIsPaginatedAndFilterableByName(): void
    {
        $institution = $this->getInstitution();
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->find(Orga::class, $institution->getId())->setName('Unique Institution '.uniqid());
        $entityManager->flush();
        $uniqueName = $entityManager->find(Orga::class, $institution->getId())->getName();
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        $response = $this->sendRequest(
            self::ROUTE.'?pagination=true&itemsPerPage=1&page=1&name='.rawurlencode(strtoupper($uniqueName)),
            'GET',
            $this->user,
            null
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = Json::decodeToArray($response->getContent());
        self::assertContains($institution->getId(), array_column($content['data'], 'id'));
        self::assertGreaterThanOrEqual(1, $content['meta']['totalItems']);
        self::assertSame(1, $content['meta']['itemsPerPage']);
    }

    public function testTagAndCategoryAreOnlyReadableForTheCurrentCustomer(): void
    {
        $currentCustomer = $this->getCurrentCustomerOf($this->getInstitution());
        $ownTag = InstitutionTagFactory::createOne([
            'label'    => 'own-tag',
            'category' => InstitutionTagCategoryFactory::new(['name' => 'own-category', 'customer' => $currentCustomer]),
        ])->_real();
        $foreignTag = InstitutionTagFactory::createOne([
            'category' => InstitutionTagCategoryFactory::new([
                'customer' => CustomerFactory::new(['subdomain' => 'other-customer-'.uniqid()]),
            ]),
        ])->_real();
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        $own = $this->sendRequest('/api/3.0/InstitutionTag/'.$ownTag->getId(), 'GET', $this->user, null);
        $foreign = $this->sendRequest('/api/3.0/InstitutionTag/'.$foreignTag->getId(), 'GET', $this->user, null);
        $category = $this->sendRequest('/api/3.0/InstitutionTagCategory/'.$ownTag->getCategory()->getId(), 'GET', $this->user, null);

        self::assertSame(Response::HTTP_OK, $own->getStatusCode());
        self::assertSame('own-tag', Json::decodeToArray($own->getContent())['data']['attributes']['name']);
        self::assertSame(Response::HTTP_NOT_FOUND, $foreign->getStatusCode());
        self::assertSame(Response::HTTP_OK, $category->getStatusCode());
        self::assertSame('own-category', Json::decodeToArray($category->getContent())['data']['attributes']['name']);
    }

    public function testListIsOrderedByCreatedDate(): void
    {
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();
        $ids = $this->getIds($this->sendRequest(self::ROUTE.'?pagination=false', 'GET', $this->user, null));
        self::assertGreaterThan(1, count($ids));
        foreach ($ids as $index => $id) {
            $entityManager->find(Orga::class, $id)->setCreatedDate(new DateTime('2020-01-0'.($index + 1)));
        }
        $entityManager->flush();

        $default = $this->getIds($this->sendRequest(self::ROUTE, 'GET', $this->user, null));
        $ascending = $this->getIds($this->sendRequest(self::ROUTE.'?order[createdDate]=asc', 'GET', $this->user, null));
        $descending = $this->getIds($this->sendRequest(self::ROUTE.'?order[createdDate]=desc', 'GET', $this->user, null));

        self::assertSame(array_reverse($ids), $default);
        self::assertSame($ids, $ascending);
        self::assertSame(array_reverse($ids), $descending);
    }

    public function testGetRestrictsAttributesToTheRequestedFields(): void
    {
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        $response = $this->sendRequest(
            self::ROUTE.'/'.$this->getInstitution()->getId().'?fields[InvitableInstitution]=name',
            'GET',
            $this->user,
            null
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['name'], array_keys(Json::decodeToArray($response->getContent())['data']['attributes']));
    }

    public function testListExcludesDeletedInstitutions(): void
    {
        $institution = $this->getInstitution();
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->find(Orga::class, $institution->getId())->setDeleted(true);
        $entityManager->flush();
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        $response = $this->sendRequest(self::ROUTE.'?pagination=false', 'GET', $this->user, null);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $ids = array_column(Json::decodeToArray($response->getContent())['data'], 'id');
        self::assertNotContains($institution->getId(), $ids);
    }

    public function testGetIsDeniedWithoutTagPermission(): void
    {
        $this->loginForApiPlatform();

        $response = $this->sendRequest(self::ROUTE.'/'.$this->getInstitution()->getId(), 'GET', $this->user, null);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testGetUnknownInstitutionIsNotFound(): void
    {
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        $response = $this->sendRequest(self::ROUTE.'/00000000-0000-4000-8000-000000000000', 'GET', $this->user, null);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * @return list<string>
     */
    private function getIds(Response $response): array
    {
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        return array_column(Json::decodeToArray($response->getContent())['data'], 'id');
    }

    private function getInstitution(): Orga
    {
        return $this->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY);
    }

    private function getCurrentCustomerOf(Orga $institution): Customer
    {
        return $institution->getStatusInCustomers()->first()->getCustomer();
    }

    private function makeCustomerCurrent(Customer $customer): void
    {
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($customer->getSubdomain());
    }
}
