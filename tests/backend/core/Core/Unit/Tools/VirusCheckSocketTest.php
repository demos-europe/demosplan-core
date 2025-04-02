<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Tools;

use demosplan\DemosPlanCoreBundle\Tools\VirusCheckSocket;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Tests\Base\UnitTestCase;

class VirusCheckSocketTest extends UnitTestCase
{
    /** @var LoggerInterface|MockObject */
    private $loggerMock;

    /** @var ParameterBagInterface|MockObject */
    private $parameterBagMock;

    /** @var File|MockObject */
    private $fileMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);
        $this->fileMock = $this->createMock(File::class);

        // Configure parameter bag
        $this->parameterBagMock->method('get')
            ->willReturnMap([
                ['avscan_host', 'localhost'],
                ['avscan_port', 3310],
                ['avscan_timeout', 30],
            ]);

        // Set up file mock behavior
        $this->fileMock->method('getRealPath')->willReturn('/tmp/testfile.txt');
        $this->fileMock->method('getFilename')->willReturn('testfile.txt');

        // Create the system under test
        $this->sut = new VirusCheckSocket(
            $this->loggerMock,
            $this->parameterBagMock
        );
    }

    /**
     * Test scanFile with various file states.
     */
    public function testScanFileWithNonExistentFile(): void
    {
        // Create a temporary test class that overrides the scanFile method
        $sutMock = $this->getMockBuilder(VirusCheckSocket::class)
            ->setConstructorArgs([$this->loggerMock, $this->parameterBagMock])
            ->onlyMethods(['scanFile'])
            ->getMock();

        // Configure the mock to throw an exception
        $sutMock->method('scanFile')
            ->willThrowException(new InvalidArgumentException('File not found or not readable'));

        $this->expectException(Exception::class);
        $sutMock->hasVirus($this->fileMock);
    }

    /**
     * Test hasVirus when a virus is detected.
     */
    public function testHasVirusWhenVirusDetected(): void
    {
        // Create a temporary test class that overrides the scanFile method
        $sutMock = $this->getMockBuilder(VirusCheckSocket::class)
            ->setConstructorArgs([$this->loggerMock, $this->parameterBagMock])
            ->onlyMethods(['scanFile'])
            ->getMock();

        // Configure the mock to return a virus found response
        $sutMock->method('scanFile')
            ->willReturn('stream: Eicar-Test-Signature FOUND');

        // Logger should log a warning message
        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                $this->equalTo('Virus found'),
                $this->anything()
            );

        // Assert result (true means virus found)
        $result = $sutMock->hasVirus($this->fileMock);
        self::assertTrue($result);
    }

    /**
     * Test hasVirus when no virus is detected.
     */
    public function testHasVirusWhenNoVirusDetected(): void
    {
        // Create a temporary test class that overrides the scanFile method
        $sutMock = $this->getMockBuilder(VirusCheckSocket::class)
            ->setConstructorArgs([$this->loggerMock, $this->parameterBagMock])
            ->onlyMethods(['scanFile'])
            ->getMock();

        // Configure the mock to return a clean file response
        $sutMock->method('scanFile')
            ->willReturn('stream: OK');

        // Logger should not log any warning messages
        $this->loggerMock->expects($this->never())
            ->method('warning');

        // Assert result (false means no virus found)
        $result = $sutMock->hasVirus($this->fileMock);
        self::assertFalse($result);
    }

    /**
     * Test hasVirus when an error occurs during scanning.
     */
    public function testHasVirusWhenErrorOccurs(): void
    {
        // Create a temporary test class that overrides the scanFile method
        $sutMock = $this->getMockBuilder(VirusCheckSocket::class)
            ->setConstructorArgs([$this->loggerMock, $this->parameterBagMock])
            ->onlyMethods(['scanFile'])
            ->getMock();

        // Configure the mock to throw an exception
        $sutMock->method('scanFile')
            ->willThrowException(new RuntimeException('Connection error'));

        // Logger should log an error message
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Error in virusCheck:'),
                $this->anything()
            );

        $this->expectException(Exception::class);
        $sutMock->hasVirus($this->fileMock);
    }
}
