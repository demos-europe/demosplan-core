<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\RepositoryLoaderPass;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Repository\AddressBookEntryRepository;
use demosplan\DemosPlanCoreBundle\Repository\MailRepository;
use demosplan\DemosPlanCoreBundle\Repository\MunicipalityRepository;
use demosplan\DemosPlanCoreBundle\Repository\ReportRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tests\Base\FunctionalTestCase;

class RepositoryLoaderPassTest extends FunctionalTestCase
{
    private $repositoryLoaderPass;
    private $containerBuilder;

    protected function setUp(): void
    {
        $this->repositoryLoaderPass = new RepositoryLoaderPass();
        $this->containerBuilder = $this->createMock(ContainerBuilder::class);
    }

    /**
     * @dataProvider processDefaultCaseDataProvider
     */
    public function testDefaultCase($services, $containerDefinition, $entityClass)
    {
        $definition = $this->getDefinition($services, $containerDefinition);

        $definition->expects($this->once())
            ->method('setArgument')
            ->with('$entityClass', $entityClass);

        $this->repositoryLoaderPass->process($this->containerBuilder);
    }

    public function processDefaultCaseDataProvider(): array
    {
        return [
            [
                [AddressBookEntryRepository::class => []],
                AddressBookEntryRepository::class,
                AddressBookEntry::class,
            ],
            [
                [MunicipalityRepository::class => []],
                MunicipalityRepository::class,
                Municipality::class,
            ],
        ];
    }

    /**
     * @dataProvider processInvalidCaseDataProvider
     */
    public function testInvalidCase($services, $containerDefinition): void
    {
        $definition = $this->getDefinition($services, $containerDefinition);

        $definition->expects($this->never())
            ->method('setArgument');

        $this->repositoryLoaderPass->process($this->containerBuilder);
    }

    public function processInvalidCaseDataProvider(): array
    {
        return [
            [
                [ReportRepository::class => []],
                ReportRepository::class,
            ],
            [
                [MailRepository::class => []],
                MailRepository::class,
            ],
        ];
    }

    private function getDefinition($services, $containerDefinition): MockObject
    {
        $definition = $this->createMock(Definition::class);
        $definition->method('getClass')
            ->willReturn('Doctrine\Persistence\ObjectRepository');

        $this->containerBuilder->method('findTaggedServiceIds')
            ->with('doctrine.repository_service')
            ->willReturn($services);

        $this->containerBuilder->method('findDefinition')
            ->with($containerDefinition)
            ->willReturn($definition);

        return $definition;
    }
}
