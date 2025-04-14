<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */
namespace Tests\Core\Core\Unit\DependencyInjection\Compiler;

use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\VirusCheckPass;
use demosplan\DemosPlanCoreBundle\Tools\VirusCheckHttp;
use demosplan\DemosPlanCoreBundle\Tools\VirusCheckInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tests\Base\UnitTestCase;

class VirusCheckPassTest extends UnitTestCase
{
    private ?VirusCheckPass $virusCheckPass;

    /** @var ContainerBuilder|MockObject */
    private $containerBuilderMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->virusCheckPass = new VirusCheckPass();
        $this->containerBuilderMock = $this->createMock(ContainerBuilder::class);
    }

    /**
     * Tests that if parameter 'avscan_implementation' is not set, the
     * compiler pass should return early without modifying anything.
     */
    public function testProcessNoImplementationParameter(): void
    {
        // Configure mock to report parameter doesn't exist
        $this->containerBuilderMock->method('hasParameter')
            ->with('avscan_implementation')
            ->willReturn(false);

        // Expect no further actions on container
        $this->containerBuilderMock->expects($this->never())
            ->method('getParameter');
        $this->containerBuilderMock->expects($this->never())
            ->method('hasDefinition');
        $this->containerBuilderMock->expects($this->never())
            ->method('setAlias');

        // Execute the compiler pass
        $this->virusCheckPass->process($this->containerBuilderMock);
    }

    /**
     * Tests that when the requested implementation exists, it's properly set as alias
     * for the VirusCheckInterface.
     *
     * @dataProvider validImplementationProvider
     */
    public function testProcessWithValidImplementation(string $implementation): void
    {
        // Setup mocks to simulate that parameter exists and implementation class exists
        $this->containerBuilderMock->method('hasParameter')
            ->with('avscan_implementation')
            ->willReturn(true);

        $this->containerBuilderMock->method('getParameter')
            ->with('avscan_implementation')
            ->willReturn($implementation);

        $serviceId = 'demosplan\\DemosPlanCoreBundle\\Tools\\' . $implementation;

        $this->containerBuilderMock->method('hasDefinition')
            ->with($serviceId)
            ->willReturn(true);

        // The alias should be set with the right service ID and made public
        $this->containerBuilderMock->expects($this->once())
            ->method('setAlias')
            ->with(
                VirusCheckInterface::class,
                $serviceId
            )
            ->willReturn($this->createMock(Alias::class));

        // Execute the compiler pass
        $this->virusCheckPass->process($this->containerBuilderMock);
    }

    /**
     * Provides valid implementation names and their expected class names
     */
    public function validImplementationProvider(): array
    {
        return [
            ['VirusCheckHttp'],
            ['VirusCheckSocket'],
            ['VirusCheckRabbitmq'],
        ];
    }

    /**
     * Tests that when the requested implementation doesn't exist,
     * it defaults to VirusCheckHttp
     */
    public function testProcessWithInvalidImplementation(): void
    {
        // Setup mocks to simulate parameter exists but implementation class doesn't
        $this->containerBuilderMock->method('hasParameter')
            ->with('avscan_implementation')
            ->willReturn(true);

        $this->containerBuilderMock->method('getParameter')
            ->with('avscan_implementation')
            ->willReturn('NonExistentImplementation');

        $serviceId = 'demosplan\\DemosPlanCoreBundle\\Tools\\NonExistentImplementation';

        $this->containerBuilderMock->method('hasDefinition')
            ->with($serviceId)
            ->willReturn(false);

        // The alias should be set to the default VirusCheckHttp and made public
        $this->containerBuilderMock->expects($this->once())
            ->method('setAlias')
            ->with(
                VirusCheckInterface::class,
                VirusCheckHttp::class
            )
            ->willReturn($this->createMock(Alias::class));

        // Execute the compiler pass
        $this->virusCheckPass->process($this->containerBuilderMock);
    }
}
