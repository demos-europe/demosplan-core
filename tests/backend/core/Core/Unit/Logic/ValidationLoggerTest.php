<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\ValidationLogger;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tests\Base\UnitTestCase;

class ValidationLoggerTest extends UnitTestCase
{
    private ValidationLogger $sut;
    private LoggerInterface|MockObject $logger;
    private TokenStorageInterface|MockObject $tokenStorage;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->sut = new ValidationLogger($this->logger, $this->tokenStorage);
    }

    public function testLogValidationFailureWithUser(): void
    {
        // Setup token and user mocks
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test_user');
        
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        
        $this->tokenStorage->method('getToken')->willReturn($token);
        
        // Setup request
        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/api/test',
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        
        // Setup exception
        $exception = $this->createMock(InvalidDataException::class);
        $exception->method('getMessage')->willReturn('Validation failed');
        $exception->method('getStatusCode')->willReturn(400);
        
        // Expect logger to be called with correct parameters
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Input validation failed',
                $this->callback(function ($context) {
                    return $context['path'] === '/api/test' &&
                           $context['method'] === 'POST' &&
                           $context['ip'] === '127.0.0.1' &&
                           $context['userId'] === 'test_user' &&
                           $context['errorCode'] === 400 &&
                           $context['errorMessage'] === 'Validation failed';
                })
            );
        
        $this->sut->logValidationFailure($request, $exception);
    }
    
    public function testLogValidationFailureWithAnonymousUser(): void
    {
        // Setup tokenStorage to return no token
        $this->tokenStorage->method('getToken')->willReturn(null);
        
        // Setup request
        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/api/test',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        
        // Setup exception
        $exception = $this->createMock(InvalidDataException::class);
        $exception->method('getMessage')->willReturn('Bad data');
        $exception->method('getStatusCode')->willReturn(422);
        
        // Expect logger to be called with correct parameters
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Input validation failed',
                $this->callback(function ($context) {
                    return $context['path'] === '/api/test' &&
                           $context['method'] === 'GET' &&
                           $context['ip'] === '127.0.0.1' &&
                           $context['userId'] === 'anonymous' &&
                           $context['errorCode'] === 422 &&
                           $context['errorMessage'] === 'Bad data';
                })
            );
        
        $this->sut->logValidationFailure($request, $exception);
    }
}