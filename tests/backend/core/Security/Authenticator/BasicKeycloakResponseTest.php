<?php declare(strict_types=1);


namespace Tests\Core\Security\Authenticator;


use demosplan\DemosPlanCoreBundle\ValueObject\BasicKeycloakResponse;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakResponseInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Token\AccessToken;
use Tests\Base\FunctionalTestCase;

class BasicKeycloakResponseTest extends FunctionalTestCase
{
    /** @var BasicKeycloakResponse */
    protected $sut;

    /** @var ClientRegistry */
    private $clientRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(BasicKeycloakResponse::class);
//        $this->sut = self::$container->get(BasicKeycloakResponse::class);
        $this->clientRegistry = $this->getContainer()->get(KeycloakResponseInterface::class);
        $this->client = $this->clientRegistry->getClient('keycloak_ozg');
    }

    public function testFill(): void
    {
        $accessToken = new AccessToken(['access_token' => 'eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICI0WlhYVzVBc21GUXFGZ2JxR2xNRWRjVDhTNnNTME9fc1BxNUV5YjNxN0xFIn0.eyJleHAiOjE2NzkzMjEyNjcsImlhdCI6MTY3OTMyMDk2NywiYXV0aF90aW1lIjoxNjc5MzIwOTM0LCJqdGkiOiI0NWQ5MDMzNS1iMmFhLTQ0MzMtYjUzNi05YjM5YzE5YjllMTUiLCJpc3MiOiJodHRwOi8vMTcyLjIyLjI1NS4xOjgwODAvcmVhbG1zL2RpcGxhbnVuZyIsImF1ZCI6ImFjY291bnQiLCJzdWIiOiIyOWQ2OTAxZS1iNjM2LTQ3ODQtYTg1MS1hNzcxN2E5YTc1NDkiLCJ0eXAiOiJCZWFyZXIiLCJhenAiOiJkaXBsYW4tYmV0ZWlsaWd1bmctYmVybGluIiwic2Vzc2lvbl9zdGF0ZSI6ImQzNjJmNjY2LTNhNmYtNDBjNS04YTI4LWM3MjNkODY5OWQ4YSIsImFjciI6IjEiLCJhbGxvd2VkLW9yaWdpbnMiOlsiaHR0cDovL2RpcGxhbmJhdS5kcGxhbi5sb2NhbCJdLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsiZGVmYXVsdC1yb2xlcy1teXJlYWxtIiwib2ZmbGluZV9hY2Nlc3MiLCJ1bWFfYXV0aG9yaXphdGlvbiJdfSwicmVzb3VyY2VfYWNjZXNzIjp7ImFjY291bnQiOnsicm9sZXMiOlsibWFuYWdlLWFjY291bnQiLCJtYW5hZ2UtYWNjb3VudC1saW5rcyIsInZpZXctcHJvZmlsZSJdfX0sInNjb3BlIjoib3BlbmlkIGVtYWlsIHByb2ZpbGUgZGlwbGFuX3Njb3BlIiwic2lkIjoiZDM2MmY2NjYtM2E2Zi00MGM1LThhMjgtYzcyM2Q4Njk5ZDhhIiwib3JnYW5pc2F0aW9uTmFtZSI6IkFtdCBOb3Jkd2VzdCIsImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwib3JnYW5pc2F0aW9uSWQiOjE0MCwibmFtZSI6IldhbHRlciBXZXN0aSIsInByZWZlcnJlZF91c2VybmFtZSI6Im15dXNlciIsImdpdmVuX25hbWUiOiJXYWx0ZXIiLCJmYW1pbHlfbmFtZSI6Ildlc3RpIiwiZW1haWwiOiJib2Itc2gxMkBkZW1vcy1kZXV0c2NobGFuZC5kZSJ9.lPT4QqQAA6yn5Z_VDV1SDuF0MG0oZKDk7_wZxdttUlaHkcs5sjtj3bZp3Tboea1PlskrUAsmecSniRWamWyvG7BtCNIverf6pQS6VgEDFtinNAT8co27LVmR0KDz2bdXiLJhBFItK9HGyFeBeq4LakVsOHc_k7moXjHI04NcyG53K9iNvzrGDqf2P5-FJ4XavkOt5cHL545yiZlPQRxihaAWFFSVlboykIkwVrmPSrVmJnrT4agvFLhhoSriDCDy9nIjfHC8fDJ6es8Q5YweowXJYGvD-esFgZDTjYX32d7bgc9BiTQ5Yh6qRNDYfA4fKJOVj4mEe5gjojT0KqKTgA']);
        $resourceOwner = $this->client->fetchUserFromToken($accessToken);
        $this->sut->fill($resourceOwner);

        static::assertSame('Amt Nordwest', $this->sut->getOrganisationName());
        static::assertSame('140', $this->sut->getOrganisationId());
        static::assertSame('Walter Westi', $this->sut->getFullName());
        static::assertSame('29d6901e-b636-4784-a851-a7717a9a7549', $this->sut->getUserId());
        static::assertSame('myuser', $this->sut->getUserName());
        static::assertSame('bob-sh12@demos-deutschland.de', $this->sut->getEmailAddress());
        static::assertIsArray($this->sut->getCustomerRoleRelations());
        static::assertArrayHasKey('Schleswig-Holstein', $this->sut->getCustomerRoleRelations());
        static::assertIsArray($this->sut->getCustomerRoleRelations()['Schleswig-Holstein']);
        static::assertCount(2, $this->sut->getCustomerRoleRelations()['Schleswig-Holstein']);
        static::assertSame('Fachplanung Administration', $this->sut->getCustomerRoleRelations()['Schleswig-Holstein'][0]);
        static::assertSame('Fachplanung Planungsbüro', $this->sut->getCustomerRoleRelations()['Schleswig-Holstein'][1]);
    }
}
