<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\SetCustomerOAuthConfigCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\CustomerOAuthConfig;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class SetCustomerOAuthConfigCommandTest extends FunctionalTestCase
{
    private (MockObject&EntityManagerInterface)|null $entityManagerMock = null;
    private (MockObject&CustomerRepository)|null $customerRepositoryMock = null;
    private (MockObject&CustomerOAuthConfigRepository)|null $configRepositoryMock = null;
    private (MockObject&OrgaRepository)|null $orgaRepositoryMock = null;
    private (MockObject&ParameterBagInterface)|null $parameterBagMock = null;

    /** @var list<string>|null */
    private ?array $tempFiles = [];

    private const SUBDOMAIN = 'testcustomer';
    private const CLIENT_ID = 'dplan-test';
    private const CLIENT_SECRET = 'super-secret';
    private const AUTH_SERVER_URL = 'https://keycloak.example.com/auth';
    private const REALM = 'dplan';

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepository::class);
        $this->configRepositoryMock = $this->createMock(CustomerOAuthConfigRepository::class);
        $this->orgaRepositoryMock = $this->createMock(OrgaRepository::class);
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);

        // Interactive mode uses choice() which needs findAll() to list subdomains
        $customerStub = $this->createCustomerStub();
        $this->customerRepositoryMock->method('findAll')
            ->willReturn([$customerStub]);
    }

    public function testFromFileUpsertsConfigForValidJson(): void
    {
        $customer = $this->createCustomerStub();
        $this->customerRepositoryMock->method('findOneBy')
            ->with(['subdomain' => self::SUBDOMAIN])
            ->willReturn($customer);
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn(null);

        $this->entityManagerMock->expects(self::once())->method('persist');
        $this->entityManagerMock->expects(self::once())->method('flush');

        $configFile = $this->createTempConfigFile([
            self::SUBDOMAIN => [
                'clientId'      => self::CLIENT_ID,
                'clientSecret'  => self::CLIENT_SECRET,
                'authServerUrl' => self::AUTH_SERVER_URL,
                'realm'         => self::REALM,
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Upserted: 1', $tester->getDisplay());
        self::assertStringContainsString('Skipped: 0', $tester->getDisplay());
    }

    public function testFromFileSkipsUnknownSubdomain(): void
    {
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn(null);

        $configFile = $this->createTempConfigFile([
            'nonexistent' => [
                'clientId'      => self::CLIENT_ID,
                'clientSecret'  => self::CLIENT_SECRET,
                'authServerUrl' => self::AUTH_SERVER_URL,
                'realm'         => self::REALM,
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Upserted: 0', $tester->getDisplay());
        self::assertStringContainsString('Skipped: 1', $tester->getDisplay());
    }

    public function testFromFileFailsForMissingFile(): void
    {
        $tester = $this->executeCommand(['--config-file' => '/tmp/nonexistent.json']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Config file not found', $tester->getDisplay());
    }

    public function testFromFileSkipsEntryWithMissingRequiredField(): void
    {
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($this->createCustomerStub());

        $configFile = $this->createTempConfigFile([
            self::SUBDOMAIN => [
                'clientId'      => self::CLIENT_ID,
                // clientSecret missing
                'authServerUrl' => self::AUTH_SERVER_URL,
                'realm'         => self::REALM,
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Skipped', $tester->getDisplay());
        self::assertStringContainsString('Missing or empty required field', $tester->getDisplay());
        self::assertStringContainsString('clientSecret', $tester->getDisplay());
    }

    public function testFromFileFailsForInvalidJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'oauth_test_');
        file_put_contents($path, '{ invalid json');
        $this->tempFiles[] = $path;

        $tester = $this->executeCommand(['--config-file' => $path]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Invalid JSON', $tester->getDisplay());
    }

    public function testFromFileSkipsEntryWithInvalidAuthServerUrl(): void
    {
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($this->createCustomerStub());

        $configFile = $this->createTempConfigFile([
            self::SUBDOMAIN => [
                'clientId'      => self::CLIENT_ID,
                'clientSecret'  => self::CLIENT_SECRET,
                'authServerUrl' => 'http://insecure.example.com', // not HTTPS
                'realm'         => self::REALM,
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Skipped', $display);
        self::assertStringContainsString('authServerUrl must be a valid HTTPS URL', $display);
    }

    public function testFromFileUpdatesExistingConfig(): void
    {
        $customer = $this->createCustomerStub();
        $existingConfig = new CustomerOAuthConfig();
        $existingConfig->setCustomer($customer);
        $existingConfig->setKeycloakClientId('old-id');
        $existingConfig->setKeycloakClientSecret('old-secret');
        $existingConfig->setKeycloakAuthServerUrl('https://old.example.com');
        $existingConfig->setKeycloakRealm('old-realm');

        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($customer);
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn($existingConfig);

        // persist should NOT be called for existing config
        $this->entityManagerMock->expects(self::never())->method('persist');
        $this->entityManagerMock->expects(self::once())->method('flush');

        $configFile = $this->createTempConfigFile([
            self::SUBDOMAIN => [
                'clientId'      => self::CLIENT_ID,
                'clientSecret'  => self::CLIENT_SECRET,
                'authServerUrl' => self::AUTH_SERVER_URL,
                'realm'         => self::REALM,
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(self::CLIENT_ID, $existingConfig->getKeycloakClientId());
        self::assertSame(self::CLIENT_SECRET, $existingConfig->getKeycloakClientSecret());
        self::assertSame(self::AUTH_SERVER_URL, $existingConfig->getKeycloakAuthServerUrl());
        self::assertSame(self::REALM, $existingConfig->getKeycloakRealm());
    }

    public function testInteractiveCreatesNewConfig(): void
    {
        $customer = $this->createCustomerStub();
        $this->customerRepositoryMock->method('findOneBy')
            ->with(['subdomain' => self::SUBDOMAIN])
            ->willReturn($customer);
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn(null);

        $this->entityManagerMock->expects(self::once())->method('persist');
        $this->entityManagerMock->expects(self::once())->method('flush');

        $tester = $this->executeCommand([], [
            self::SUBDOMAIN,      // subdomain
            self::CLIENT_SECRET,  // clientSecret
            self::CLIENT_ID,      // clientId
            self::AUTH_SERVER_URL, // authServerUrl
            self::REALM,          // realm
            '',                   // logoutRoute (skip)
            '',                   // defaultOrganisationId (skip)
            'keycloak',           // identityProviderType (choice)
            '',                   // autoProvisionUsers (default)
            'yes',                // confirm
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('saved', $tester->getDisplay());
    }

    public function testInteractiveAbortsOnDeny(): void
    {
        $customer = $this->createCustomerStub();
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($customer);
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn(null);

        $this->entityManagerMock->expects(self::never())->method('flush');

        $tester = $this->executeCommand([], [
            self::SUBDOMAIN,
            self::CLIENT_SECRET,
            self::CLIENT_ID,
            self::AUTH_SERVER_URL,
            self::REALM,
            '',
            '',           // defaultOrganisationId (skip)
            'keycloak',   // identityProviderType (choice)
            '',           // autoProvisionUsers (default)
            'no',         // deny
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Aborted', $tester->getDisplay());
    }

    public function testInteractiveKeepsExistingSecretWhenEmpty(): void
    {
        $customer = $this->createCustomerStub();
        $existingConfig = new CustomerOAuthConfig();
        $existingConfig->setCustomer($customer);
        $existingConfig->setKeycloakClientId('old-id');
        $existingConfig->setKeycloakClientSecret('existing-secret');
        $existingConfig->setKeycloakAuthServerUrl(self::AUTH_SERVER_URL);
        $existingConfig->setKeycloakRealm(self::REALM);

        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($customer);
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn($existingConfig);

        $this->entityManagerMock->expects(self::once())->method('flush');

        $tester = $this->executeCommand([], [
            self::SUBDOMAIN,
            '',               // empty secret → keep existing
            self::CLIENT_ID,  // new client ID
            '',               // keep default authServerUrl
            '',               // keep default realm
            '',               // skip logoutRoute
            '',               // skip defaultOrganisationId
            'keycloak',       // identityProviderType (choice)
            '',               // autoProvisionUsers (default)
            'yes',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('existing-secret', $existingConfig->getKeycloakClientSecret());
        self::assertSame(self::CLIENT_ID, $existingConfig->getKeycloakClientId());
    }

    public function testInteractiveRequiresSecretForNewConfig(): void
    {
        $customer = $this->createCustomerStub();
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($customer);
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn(null);

        $tester = $this->executeCommand([], [
            self::SUBDOMAIN,
            '',               // empty secret on new config
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Client secret is required', $tester->getDisplay());
    }

    public function testFromFileRejectsInvalidIdentityProviderType(): void
    {
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($this->createCustomerStub());
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn(null);

        $configFile = $this->createTempConfigFile([
            self::SUBDOMAIN => [
                'clientId'             => self::CLIENT_ID,
                'clientSecret'         => self::CLIENT_SECRET,
                'authServerUrl'        => self::AUTH_SERVER_URL,
                'realm'                => self::REALM,
                'identityProviderType' => 'invalid_type',
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Skipped', $tester->getDisplay());
        self::assertStringContainsString('Invalid identityProviderType', $tester->getDisplay());
    }

    public function testFromFileAcceptsValidIdentityProviderTypes(): void
    {
        $customer = $this->createCustomerStub();
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($customer);
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn(null);

        $this->entityManagerMock->expects(self::once())->method('persist');
        $this->entityManagerMock->expects(self::once())->method('flush');

        $configFile = $this->createTempConfigFile([
            self::SUBDOMAIN => [
                'clientId'             => self::CLIENT_ID,
                'clientSecret'         => self::CLIENT_SECRET,
                'authServerUrl'        => self::AUTH_SERVER_URL,
                'realm'                => self::REALM,
                'identityProviderType' => 'azure_entra_id',
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Upserted: 1', $tester->getDisplay());
    }

    public function testFromFileRejectsAutoProvisionWithKeycloakProvider(): void
    {
        $orga = $this->createMock(\demosplan\DemosPlanCoreBundle\Entity\User\Orga::class);
        $orga->method('getId')->willReturn('test-org-id');

        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($this->createCustomerStub());
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn(null);
        $this->orgaRepositoryMock->method('get')
            ->with('test-org-id')
            ->willReturn($orga);

        $configFile = $this->createTempConfigFile([
            self::SUBDOMAIN => [
                'clientId'              => self::CLIENT_ID,
                'clientSecret'          => self::CLIENT_SECRET,
                'authServerUrl'         => self::AUTH_SERVER_URL,
                'realm'                 => self::REALM,
                'defaultOrganisationId' => 'test-org-id',
                'identityProviderType'  => 'keycloak',
                'autoProvisionUsers'    => true,
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Skipped', $display);
        self::assertStringContainsString('only supported for azure_entra_id', $display);
    }

    public function testFromFileRejectsAutoProvisionWithoutDefaultOrg(): void
    {
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn($this->createCustomerStub());
        $this->configRepositoryMock->method('findByCustomer')
            ->willReturn(null);

        $configFile = $this->createTempConfigFile([
            self::SUBDOMAIN => [
                'clientId'           => self::CLIENT_ID,
                'clientSecret'       => self::CLIENT_SECRET,
                'authServerUrl'      => self::AUTH_SERVER_URL,
                'realm'              => self::REALM,
                'autoProvisionUsers' => true,
            ],
        ]);

        $tester = $this->executeCommand(['--config-file' => $configFile]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Skipped', $tester->getDisplay());
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('autoProvisionUsers requires a defaultOrganisationId', $display);
    }

    /**
     * @return MockObject&Customer
     */
    private function createCustomerStub(): MockObject
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('test-customer-id');
        $customer->method('getSubdomain')->willReturn(self::SUBDOMAIN);

        return $customer;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function createTempConfigFile(array $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'oauth_test_');
        file_put_contents($path, json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * @param array<string, mixed> $options    Command options (e.g. --config-file)
     * @param list<string>         $userInputs Interactive inputs (one per prompt)
     */
    private function executeCommand(array $options = [], array $userInputs = []): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);
        $application->add(
            new SetCustomerOAuthConfigCommand(
                $this->entityManagerMock,
                $this->customerRepositoryMock,
                $this->configRepositoryMock,
                $this->orgaRepositoryMock,
                $this->parameterBagMock,
            )
        );

        $command = $application->find('dplan:customer:oauth-config:sync');
        $tester = new CommandTester($command);

        if ([] !== $userInputs) {
            $tester->setInputs($userInputs);
        }

        $execute = ['command' => $command->getName()];
        foreach ($options as $key => $value) {
            $execute[$key] = $value;
        }

        $tester->execute($execute);

        return $tester;
    }
}
