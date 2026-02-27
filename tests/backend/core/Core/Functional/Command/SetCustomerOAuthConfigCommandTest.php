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
    private (MockObject&ParameterBagInterface)|null $parameterBagMock = null;

    private const SUBDOMAIN = 'testcustomer';
    private const CLIENT_ID = 'dplan-test';
    private const CLIENT_SECRET = 'super-secret';
    private const AUTH_SERVER_URL = 'https://keycloak.example.com/auth';
    private const REALM = 'dplan';
    private const LOGOUT_ROUTE = 'https://keycloak.example.com/auth/realms/dplan/protocol/openid-connect/logout';

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepository::class);
        $this->configRepositoryMock = $this->createMock(CustomerOAuthConfigRepository::class);
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);
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
            self::CLIENT_ID,      // clientId
            self::CLIENT_SECRET,  // clientSecret
            self::AUTH_SERVER_URL, // authServerUrl
            self::REALM,          // realm
            '',                   // logoutRoute (skip)
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
            self::CLIENT_ID,
            self::CLIENT_SECRET,
            self::AUTH_SERVER_URL,
            self::REALM,
            '',
            'no', // deny
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Aborted', $tester->getDisplay());
    }

    public function testInteractiveFailsForUnknownSubdomain(): void
    {
        $this->customerRepositoryMock->method('findOneBy')
            ->willReturn(null);

        $tester = $this->executeCommand([], [
            'nonexistent',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No customer found', $tester->getDisplay());
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
            self::CLIENT_ID,  // new client ID
            '',               // empty secret → keep existing
            '',               // keep default authServerUrl
            '',               // keep default realm
            '',               // skip logoutRoute
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
            self::CLIENT_ID,
            '',               // empty secret on new config
            self::AUTH_SERVER_URL,
            self::REALM,
            '',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Client secret is required', $tester->getDisplay());
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

    private function createTempConfigFile(array $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'oauth_test_');
        file_put_contents($path, json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

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
