<?php declare(strict_types=1);


namespace Tests\Core\Security\Authenticator;


use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\ValueObject\BasicKeycloakUserData;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakUserDataInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use Tests\Base\FunctionalTestCase;

class OzgKeycloakUserLoginTest extends FunctionalTestCase
{
    /** @var OzgKeycloakUserDataMapper */
    protected $sut;

    protected BasicKeycloakUserData $testKeycloakUserData;
    private OAuth2ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(OzgKeycloakUserDataMapper::class);

        /** @var ClientRegistry $clientRegistry */
        $clientRegistry = $this->getContainer()->get(KeycloakUserDataInterface::class);
        $this->client = $clientRegistry->getClient('keycloak_ozg');
    }

    public function testNewUserAndOrgaOnMapUserData(): void
    {
        //setup:
        $accessToken = new AccessToken(['access_token' => 'eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICI0WlhYVzVBc21GUXFGZ2JxR2xNRWRjVDhTNnNTME9fc1BxNUV5YjNxN0xFIn0.eyJleHAiOjE2NzkzMjEyNjcsImlhdCI6MTY3OTMyMDk2NywiYXV0aF90aW1lIjoxNjc5MzIwOTM0LCJqdGkiOiI0NWQ5MDMzNS1iMmFhLTQ0MzMtYjUzNi05YjM5YzE5YjllMTUiLCJpc3MiOiJodHRwOi8vMTcyLjIyLjI1NS4xOjgwODAvcmVhbG1zL2RpcGxhbnVuZyIsImF1ZCI6ImFjY291bnQiLCJzdWIiOiIyOWQ2OTAxZS1iNjM2LTQ3ODQtYTg1MS1hNzcxN2E5YTc1NDkiLCJ0eXAiOiJCZWFyZXIiLCJhenAiOiJkaXBsYW4tYmV0ZWlsaWd1bmctYmVybGluIiwic2Vzc2lvbl9zdGF0ZSI6ImQzNjJmNjY2LTNhNmYtNDBjNS04YTI4LWM3MjNkODY5OWQ4YSIsImFjciI6IjEiLCJhbGxvd2VkLW9yaWdpbnMiOlsiaHR0cDovL2RpcGxhbmJhdS5kcGxhbi5sb2NhbCJdLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsiZGVmYXVsdC1yb2xlcy1teXJlYWxtIiwib2ZmbGluZV9hY2Nlc3MiLCJ1bWFfYXV0aG9yaXphdGlvbiJdfSwicmVzb3VyY2VfYWNjZXNzIjp7ImFjY291bnQiOnsicm9sZXMiOlsibWFuYWdlLWFjY291bnQiLCJtYW5hZ2UtYWNjb3VudC1saW5rcyIsInZpZXctcHJvZmlsZSJdfX0sInNjb3BlIjoib3BlbmlkIGVtYWlsIHByb2ZpbGUgZGlwbGFuX3Njb3BlIiwic2lkIjoiZDM2MmY2NjYtM2E2Zi00MGM1LThhMjgtYzcyM2Q4Njk5ZDhhIiwib3JnYW5pc2F0aW9uTmFtZSI6IkFtdCBOb3Jkd2VzdCIsImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwib3JnYW5pc2F0aW9uSWQiOjE0MCwibmFtZSI6IldhbHRlciBXZXN0aSIsInByZWZlcnJlZF91c2VybmFtZSI6Im15dXNlciIsImdpdmVuX25hbWUiOiJXYWx0ZXIiLCJmYW1pbHlfbmFtZSI6Ildlc3RpIiwiZW1haWwiOiJib2Itc2gxMkBkZW1vcy1kZXV0c2NobGFuZC5kZSJ9.lPT4QqQAA6yn5Z_VDV1SDuF0MG0oZKDk7_wZxdttUlaHkcs5sjtj3bZp3Tboea1PlskrUAsmecSniRWamWyvG7BtCNIverf6pQS6VgEDFtinNAT8co27LVmR0KDz2bdXiLJhBFItK9HGyFeBeq4LakVsOHc_k7moXjHI04NcyG53K9iNvzrGDqf2P5-FJ4XavkOt5cHL545yiZlPQRxihaAWFFSVlboykIkwVrmPSrVmJnrT4agvFLhhoSriDCDy9nIjfHC8fDJ6es8Q5YweowXJYGvD-esFgZDTjYX32d7bgc9BiTQ5Yh6qRNDYfA4fKJOVj4mEe5gjojT0KqKTgA']);
        $resourceOwner = $this->client->fetchUserFromToken($accessToken);
        //already covered by BasicKeycloakUserDataTest::testFill()
        $this->testKeycloakUserData->fill($resourceOwner);

        $initialAmountOfUsers = $this->countEntries(User::class);
        $initialAmountOfOrganisations = $this->countEntries(Orga::class);
        $initialAmountOfCustomers = $this->countEntries(Customer::class);
        $initialAmountOfRoles = $this->countEntries(Role::class);

        //execution and test:
        //missing test-data-user. It will be create a new user, but the values should be as expected.
        $user = $this->sut->mapUserData($this->testKeycloakUserData);

        static::assertSame($initialAmountOfUsers + 1, $this->countEntries(User::class));
        static::assertSame($initialAmountOfOrganisations + 1, $this->countEntries(Orga::class));
        static::assertSame($initialAmountOfCustomers, $this->countEntries(Customer::class));
        static::assertSame($initialAmountOfRoles, $this->countEntries(Role::class));

        //Check User
        static::assertSame('Walter Westi', $user->getName());
        static::assertSame('29d6901e-b636-4784-a851-a7717a9a7549', $user->getGwId());
        static::assertSame('myuser', $user->getLogin());
        static::assertSame('bob-sh12@demos-deutschland.de', $user->getEmail());

        //Check Customer
        static::assertCount(1, $user->getCustomers());
        static::assertSame(LoadCustomerData::SCHLESWIGHOLSTEIN, $user->getCustomers()[0]->getName());

        //Check User Roles of Customer:
        static::assertCount(2, $user->getRoleBySubdomain('sh'));
        static::assertContains(Role::PLANNING_AGENCY_ADMIN, $user->getRoleBySubdomain('sh'));
        static::assertContains(Role::PRIVATE_PLANNING_AGENCY, $user->getRoleBySubdomain('sh'));
        static::assertCount(2, $user->getRoles());
        static::assertContains(Role::PLANNING_AGENCY_ADMIN, $user->getRoles());
        static::assertContains(Role::PRIVATE_PLANNING_AGENCY, $user->getRoles());

        //check Organisation
        static::assertSame('Amt Nordwest', $user->getOrga()->getName());
        static::assertSame('140', $user->getOrga()->getGwId());
        static::assertSame('bob-sh12@demos-deutschland.de', $user->getOrgaName());

        //todo: count orgas before and after. cover creating new orga, change existing one, and find but no change orga
    }

    public function testExistingUserAndOrgaOnMapUserData(): void
    {
        //setup:
        $accessToken = new AccessToken(['access_token' => 'eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICI0WlhYVzVBc21GUXFGZ2JxR2xNRWRjVDhTNnNTME9fc1BxNUV5YjNxN0xFIn0.eyJleHAiOjE2NzkzMjEyNjcsImlhdCI6MTY3OTMyMDk2NywiYXV0aF90aW1lIjoxNjc5MzIwOTM0LCJqdGkiOiI0NWQ5MDMzNS1iMmFhLTQ0MzMtYjUzNi05YjM5YzE5YjllMTUiLCJpc3MiOiJodHRwOi8vMTcyLjIyLjI1NS4xOjgwODAvcmVhbG1zL2RpcGxhbnVuZyIsImF1ZCI6ImFjY291bnQiLCJzdWIiOiIyOWQ2OTAxZS1iNjM2LTQ3ODQtYTg1MS1hNzcxN2E5YTc1NDkiLCJ0eXAiOiJCZWFyZXIiLCJhenAiOiJkaXBsYW4tYmV0ZWlsaWd1bmctYmVybGluIiwic2Vzc2lvbl9zdGF0ZSI6ImQzNjJmNjY2LTNhNmYtNDBjNS04YTI4LWM3MjNkODY5OWQ4YSIsImFjciI6IjEiLCJhbGxvd2VkLW9yaWdpbnMiOlsiaHR0cDovL2RpcGxhbmJhdS5kcGxhbi5sb2NhbCJdLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsiZGVmYXVsdC1yb2xlcy1teXJlYWxtIiwib2ZmbGluZV9hY2Nlc3MiLCJ1bWFfYXV0aG9yaXphdGlvbiJdfSwicmVzb3VyY2VfYWNjZXNzIjp7ImFjY291bnQiOnsicm9sZXMiOlsibWFuYWdlLWFjY291bnQiLCJtYW5hZ2UtYWNjb3VudC1saW5rcyIsInZpZXctcHJvZmlsZSJdfX0sInNjb3BlIjoib3BlbmlkIGVtYWlsIHByb2ZpbGUgZGlwbGFuX3Njb3BlIiwic2lkIjoiZDM2MmY2NjYtM2E2Zi00MGM1LThhMjgtYzcyM2Q4Njk5ZDhhIiwib3JnYW5pc2F0aW9uTmFtZSI6IkFtdCBOb3Jkd2VzdCIsImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwib3JnYW5pc2F0aW9uSWQiOjE0MCwibmFtZSI6IldhbHRlciBXZXN0aSIsInByZWZlcnJlZF91c2VybmFtZSI6Im15dXNlciIsImdpdmVuX25hbWUiOiJXYWx0ZXIiLCJmYW1pbHlfbmFtZSI6Ildlc3RpIiwiZW1haWwiOiJib2Itc2gxMkBkZW1vcy1kZXV0c2NobGFuZC5kZSJ9.lPT4QqQAA6yn5Z_VDV1SDuF0MG0oZKDk7_wZxdttUlaHkcs5sjtj3bZp3Tboea1PlskrUAsmecSniRWamWyvG7BtCNIverf6pQS6VgEDFtinNAT8co27LVmR0KDz2bdXiLJhBFItK9HGyFeBeq4LakVsOHc_k7moXjHI04NcyG53K9iNvzrGDqf2P5-FJ4XavkOt5cHL545yiZlPQRxihaAWFFSVlboykIkwVrmPSrVmJnrT4agvFLhhoSriDCDy9nIjfHC8fDJ6es8Q5YweowXJYGvD-esFgZDTjYX32d7bgc9BiTQ5Yh6qRNDYfA4fKJOVj4mEe5gjojT0KqKTgA']);
        $resourceOwner = $this->client->fetchUserFromToken($accessToken);
        //already covered by BasicKeycloakUserDataTest::testFill()
        $this->testKeycloakUserData->fill($resourceOwner);


        $initialAmountOfUsers = $this->countEntries(User::class);
        $initialAmountOfOrganisations = $this->countEntries(Orga::class);
        $initialAmountOfCustomers = $this->countEntries(Customer::class);
        $initialAmountOfRoles = $this->countEntries(Role::class);

        //missing test-data-user. It will be create a new user, but the values should be as expected.
        $user = $this->sut->mapUserData($this->testKeycloakUserData);

        static::assertSame($initialAmountOfUsers, $this->countEntries(User::class));
        static::assertSame($initialAmountOfOrganisations, $this->countEntries(Orga::class));
        static::assertSame($initialAmountOfCustomers, $this->countEntries(Customer::class));
        static::assertSame($initialAmountOfRoles, $this->countEntries(Role::class));
    }
}
