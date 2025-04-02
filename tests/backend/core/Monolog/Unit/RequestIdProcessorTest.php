<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Monolog\Unit;

use demosplan\DemosPlanCoreBundle\Monolog\Processor\RequestIdProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests for the RequestIdProcessor.
 *
 * @group UnitTest
 */
class RequestIdProcessorTest extends TestCase
{
    /**
     * Test that the processor adds a request ID to the log record.
     */
    public function testProcessorAddsRequestId(): void
    {
        // Arrange
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        
        $processor = new RequestIdProcessor($requestStack);
        $record = ['message' => 'Test message', 'extra' => []];
        
        // Act
        $result = $processor($record);
        
        // Assert
        $this->assertArrayHasKey('rid', $result['extra']);
        // Request ID for CLI should start with 'c'
        $this->assertStringStartsWith('c', $result['extra']['rid']);
    }
    
    /**
     * Test that the processor reuses the same request ID for multiple log records.
     */
    public function testProcessorReusesSameRequestId(): void
    {
        // Arrange
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        
        $processor = new RequestIdProcessor($requestStack);
        $record1 = ['message' => 'Test message 1', 'extra' => []];
        $record2 = ['message' => 'Test message 2', 'extra' => []];
        
        // Act
        $result1 = $processor($record1);
        $result2 = $processor($record2);
        
        // Assert
        $this->assertSame($result1['extra']['rid'], $result2['extra']['rid']);
    }
    
    /**
     * Test that the processor uses the X-Request-ID header if it exists.
     */
    public function testProcessorUsesRequestIdFromHeader(): void
    {
        // Arrange
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $request->headers = $headers;
        
        $headers->method('has')->with('X-Request-ID')->willReturn(true);
        $headers->method('get')->with('X-Request-ID')->willReturn('test-request-id');
        
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        
        $processor = new RequestIdProcessor($requestStack);
        $record = ['message' => 'Test message', 'extra' => []];
        
        // Act
        $result = $processor($record);
        
        // Assert
        $this->assertEquals('test-request-id', $result['extra']['rid']);
    }
    
    /**
     * Test that the processor generates a new ID and stores it on the request.
     */
    public function testProcessorGeneratesAndStoresNewId(): void
    {
        // Arrange
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $attributes = $this->createMock(ParameterBag::class);
        $request->headers = $headers;
        $request->attributes = $attributes;
        
        $headers->method('has')->with('X-Request-ID')->willReturn(false);
        
        // We expect the processor to set the request_id attribute
        $attributes->expects($this->once())
            ->method('set')
            ->with($this->equalTo('request_id'), $this->anything());
        
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        
        $processor = new RequestIdProcessor($requestStack);
        $record = ['message' => 'Test message', 'extra' => []];
        
        // Act
        $result = $processor($record);
        
        // Assert
        $this->assertArrayHasKey('rid', $result['extra']);
        $this->assertNotEmpty($result['extra']['rid']);
    }
    
    /**
     * Test that the generated request ID format is correct (base36 encoded).
     */
    public function testRequestIdFormat(): void
    {
        // Arrange
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $attributes = $this->createMock(ParameterBag::class);
        $request->headers = $headers;
        $request->attributes = $attributes;
        
        $headers->method('has')->with('X-Request-ID')->willReturn(false);
        
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        
        $processor = new RequestIdProcessor($requestStack);
        $record = ['message' => 'Test message', 'extra' => []];
        
        // Act
        $result = $processor($record);
        
        // Assert
        $this->assertArrayHasKey('rid', $result['extra']);
        // Request ID should only contain alphanumeric characters (base36)
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $result['extra']['rid']);
    }
}