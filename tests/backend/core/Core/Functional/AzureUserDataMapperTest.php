<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\CustomerOAuthConfig;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\AzureUserDataMapper;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Types\IdentityProviderType;
use demosplan\DemosPlanCoreBundle\ValueObject\AzureUserData;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Tests\Base\FunctionalTestCase;

class AzureUserDataMapperTest extends FunctionalTestCase
{
    private (MockObject&CustomerOAuthConfigRepository)|null $configRepositoryMock = null;
    private (MockObject&CustomerService)|null $customerServiceMock = null;
    private (MockObject&EntityManagerInterface)|null $entityManagerMock = null;
    private (MockObject&UserRepository)|null $userRepositoryMock = null;
    private (MockObject&UserService)|null $userServiceMock = null;
    private ?AzureUserDataMapper $sut = null;

    private const OBJECT_ID = 'azure-oid-12345';
    private const EMAIL = 'test.user@example.com';
    private const FIRST_NAME = 'Test';
    private const LAST_NAME = 'User';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configRepositoryMock = $this->createMock(CustomerOAuthConfigRepository::class);
        $this->customerServiceMock = $this->createMock(CustomerService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->userServiceMock = $this->createMock(UserService::class);

        $customer = $this->createMock(Customer::class);
        $this->customerServiceMock->method('getCurrentCustomer')->willReturn($customer);

        $this->sut = new AzureUserDataMapper(
            $this->configRepositoryMock,
            $this->customerServiceMock,
            $this->entityManagerMock,
            new NullLogger(),
            $this->userRepositoryMock,
            $this->userServiceMock,
        );
    }

    public function testFindsExistingUserByGwId(): void
    {
        $existingUser = $this->createUserStub(self::OBJECT_ID);

        $this->userRepositoryMock->method('findOneBy')
            ->with(['gwId' => self::OBJECT_ID, 'deleted' => false])
            ->willReturn($existingUser);

        $result = $this->sut->mapUserData($this->createAzureUserData());

        self::assertSame($existingUser, $result);
    }

    public function testFindsExistingUserByEmailAndBackfillsGwId(): void
    {
        // Not found by gwId
        $this->userRepositoryMock->method('findOneBy')->willReturn(null);

        $existingUser = $this->createUserStub('');
        $this->userServiceMock->method('findDistinctUserByEmailOrLogin')
            ->with(self::EMAIL)
            ->willReturn($existingUser);

        $this->entityManagerMock->expects(self::once())->method('flush');

        $result = $this->sut->mapUserData($this->createAzureUserData());

        self::assertSame($existingUser, $result);
        self::assertSame(self::OBJECT_ID, $existingUser->getGwId());
    }

    public function testDoesNotOverwriteExistingGwIdOnEmailMatch(): void
    {
        $this->userRepositoryMock->method('findOneBy')->willReturn(null);

        $existingUser = $this->createUserStub('existing-oid');
        $this->userServiceMock->method('findDistinctUserByEmailOrLogin')
            ->willReturn($existingUser);

        // flush should NOT be called since gwId is already set
        $this->entityManagerMock->expects(self::never())->method('flush');

        $result = $this->sut->mapUserData($this->createAzureUserData());

        self::assertSame($existingUser, $result);
        self::assertSame('existing-oid', $existingUser->getGwId());
    }

    public function testThrowsWhenNoConfigExists(): void
    {
        $this->userRepositoryMock->method('findOneBy')->willReturn(null);
        $this->userServiceMock->method('findDistinctUserByEmailOrLogin')->willReturn(null);
        $this->configRepositoryMock->method('findByCustomer')->willReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auto-provisioning is not configured');

        $this->sut->mapUserData($this->createAzureUserData());
    }

    public function testThrowsWhenAutoProvisionDisabled(): void
    {
        $this->userRepositoryMock->method('findOneBy')->willReturn(null);
        $this->userServiceMock->method('findDistinctUserByEmailOrLogin')->willReturn(null);

        $config = $this->createConfigWithAutoProvision(false, $this->createOrgaWithDepartment());
        $this->configRepositoryMock->method('findByCustomer')->willReturn($config);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auto-provisioning is not configured');

        $this->sut->mapUserData($this->createAzureUserData());
    }

    public function testThrowsWhenAutoProvisionEnabledButNoDefaultOrg(): void
    {
        $this->userRepositoryMock->method('findOneBy')->willReturn(null);
        $this->userServiceMock->method('findDistinctUserByEmailOrLogin')->willReturn(null);

        $config = new CustomerOAuthConfig();
        $config->setCustomer($this->createMock(Customer::class));
        $config->setKeycloakClientId('test');
        $config->setKeycloakClientSecret('secret');
        $config->setKeycloakAuthServerUrl('https://example.com');
        $config->setKeycloakRealm('realm');
        $config->setAutoProvisionUsers(true);
        // defaultOrganisation is null

        $this->configRepositoryMock->method('findByCustomer')->willReturn($config);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auto-provisioning is not configured');

        $this->sut->mapUserData($this->createAzureUserData());
    }

    public function testAutoProvisionsUserWhenFlagEnabled(): void
    {
        $this->userRepositoryMock->method('findOneBy')->willReturn(null);
        $this->userServiceMock->method('findDistinctUserByEmailOrLogin')->willReturn(null);

        $orga = $this->createOrgaWithDepartment();
        $config = $this->createConfigWithAutoProvision(true, $orga);
        $this->configRepositoryMock->method('findByCustomer')->willReturn($config);

        $createdUser = $this->createUserStub(self::OBJECT_ID);
        $this->userServiceMock->expects(self::once())
            ->method('addUser')
            ->with(self::callback(static function (array $data) use ($orga): bool {
                return self::EMAIL === $data['email']
                    && self::FIRST_NAME === $data['firstname']
                    && self::LAST_NAME === $data['lastname']
                    && self::OBJECT_ID === $data['gwId']
                    && $orga === $data['organisation']
                    && true === $data['providedByIdentityProvider'];
            }))
            ->willReturn($createdUser);

        $result = $this->sut->mapUserData($this->createAzureUserData());

        self::assertSame($createdUser, $result);
    }

    private function createAzureUserData(): AzureUserData|MockObject
    {
        $data = $this->createMock(AzureUserData::class);
        $data->method('getObjectId')->willReturn(self::OBJECT_ID);
        $data->method('getEmailAddress')->willReturn(self::EMAIL);
        $data->method('getFirstName')->willReturn(self::FIRST_NAME);
        $data->method('getLastName')->willReturn(self::LAST_NAME);

        return $data;
    }

    private function createUserStub(string $gwId): User
    {
        $user = new User();
        $user->setGwId($gwId);
        $user->setEmail(self::EMAIL);

        return $user;
    }

    private function createOrgaWithDepartment(): Orga|MockObject
    {
        $department = $this->createMock(Department::class);
        $department->method('getName')->willReturn('Allgemeine Abteilung');

        $orga = $this->createMock(Orga::class);
        $orga->method('getId')->willReturn('test-orga-id');
        $orga->method('getDepartments')->willReturn(new Collection([$department]));

        return $orga;
    }

    private function createConfigWithAutoProvision(bool $autoProvision, ?Orga $orga): CustomerOAuthConfig
    {
        $config = new CustomerOAuthConfig();
        $config->setCustomer($this->createMock(Customer::class));
        $config->setKeycloakClientId('test-client');
        $config->setKeycloakClientSecret('test-secret');
        $config->setKeycloakAuthServerUrl('https://login.microsoftonline.com/tenant');
        $config->setKeycloakRealm('tenant-id');
        $config->setIdentityProviderType(IdentityProviderType::AZURE_ENTRA_ID);
        $config->setAutoProvisionUsers($autoProvision);
        $config->setDefaultOrganisation($orga);

        return $config;
    }
}
