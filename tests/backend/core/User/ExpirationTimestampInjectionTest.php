<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User;

use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakLogoutManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Base\FunctionalTestCase;

class ExpirationTimestampInjectionTest extends FunctionalTestCase
{
    /**
     * @var User
     */
    private $testUser;

    private $kernelMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernelMock = $this->createMock(KernelInterface::class);

        $this->sut = new OzgKeycloakLogoutManager(
            $this->kernelMock,
            self::getContainer()->get(LoggerInterface::class),
            self::getContainer()->get(CurrentUserService::class),
            self::getContainer()->get(CustomerService::class),
            self::getContainer()->get(ParameterBagInterface::class),
        );

        $this->testUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($this->testUser);
        $this->enablePermissions(['feature_auto_logout_warning']);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function environmentProvider(): array
    {
        return [
            'dev environment should inject'      => [DemosPlanKernel::ENVIRONMENT_DEV, true],
            'test environment should inject'     => [DemosPlanKernel::ENVIRONMENT_TEST, true],
            'prod environment should not inject' => [DemosPlanKernel::ENVIRONMENT_PROD, false],
        ];
    }

    #[DataProvider('environmentProvider')]
    public function testShouldInjectTestExpirationBasedOnEnvironment(string $environment, bool $expectedResult): void
    {
        $this->kernelMock->method('getEnvironment')->willReturn($environment);

        $result = $this->sut->shouldInjectTestExpiration();

        $this->assertEquals($expectedResult, $result);
    }
}
