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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\EventSubscriber\CsrfSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

class CsrfSubscriberTest extends FunctionalTestCase
{
    private ?CsrfTokenManagerInterface $csrfTokenManager;
    private ?MessageBagInterface $messageBag;
    private ?LoggerInterface $logger;
    private ?HeaderSanitizerService $headerSanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->headerSanitizer = new HeaderSanitizerService();
    }

    /**
     * @dataProvider dataProviderMethod
     */
    public function testOnKernelRequestWithValidToken($method): void
    {
        // Set up a valid token
        $validToken = new CsrfToken('token_id', 'valid_token');
        $this->csrfTokenManager->expects($this->once())
            ->method('getToken')
            ->with('valid_token')
            ->willReturn($validToken);

        $this->csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with($validToken)
            ->willReturn(true);

        // Create a subscriber with the mocked dependencies
        $subscriber = new CsrfSubscriber($this->csrfTokenManager, $this->messageBag, $this->logger, $this->headerSanitizer);

        // Create a request event with a request and a valid token
        $request = new Request([], ['_token' => 'valid_token'], [], [], [], ['REQUEST_URI' => '/some-uri', 'REQUEST_METHOD' => $method]);
        $event = new RequestEvent($this->createMock(KernelInterface::class), $request, 1);

        // Call the onKernelRequest method
        $subscriber->onKernelRequest($event);

        // Assert that no messages were added to the message bag
        $this->messageBag->expects($this->never())->method('add');

        // Assert that the logger was not called for a valid token
        $this->logger->expects($this->never())->method('info');
    }

    /**
     * @dataProvider dataProviderMethod
     */
    public function testOnKernelRequestWithMissingToken($method): void
    {
        $messageBag = new MessageBag($this->createMock(TranslatorInterface::class));
        $messages = $messageBag->get()->get('dev')->count();
        $this->assertEquals(0, $messages);

        // Create a subscriber with the mocked dependencies
        $subscriber = new CsrfSubscriber($this->csrfTokenManager, $messageBag, $this->logger, $this->headerSanitizer);

        // Create a request event with a request and a missing token
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/some-uri', 'REQUEST_METHOD' => $method]);
        $event = new RequestEvent($this->createMock(KernelInterface::class), $request, 1);

        // Call the onKernelRequest method
        $subscriber->onKernelRequest($event);

        $messages = $messageBag->get()->get('dev')->count();
        $this->assertEquals(1, $messages);
    }

    public function testOnKernelGetRequestWithMissingToken(): void
    {
        $messageBag = new MessageBag($this->createMock(TranslatorInterface::class));
        // get messages to ensure that they are empty
        $messageBag->get();
        $subscriber = new CsrfSubscriber($this->csrfTokenManager, $messageBag, $this->logger, $this->headerSanitizer);
        // Create a request event with a request and a missing token
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/some-uri', 'REQUEST_METHOD' => 'GET']);
        $event = new RequestEvent($this->createMock(KernelInterface::class), $request, 1);
        $subscriber->onKernelRequest($event);
        $messages = $messageBag->get('messages')->count();
        $this->assertEquals(0, $messages);
    }

    public function dataProviderMethod(): array
    {
        return [
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
            ['OPTIONS'],
            ['HEAD'],
        ];
    }
}
