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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\MessageSerializable;
use demosplan\DemosPlanCoreBundle\Logic\TransformMessageBagService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class TransformMessageBagServiceTest extends FunctionalTestCase
{
    public function testTransformMessageBagToFlashes(): void
    {
        // Mock MessageBag contents
        $messageBagMock = $this->getMessageBagMock();

        $flashBag = new FlashBag();
        // Set MessageBag on the TransformMessageBagService
        $transformService = $this->getSut($messageBagMock, $flashBag, 'dev');

        // Transform and check FlashBag contents
        $transformService->transformMessageBagToFlashes();
        $flashes = $flashBag->all();

        // dev env should have all messages
        $this->assertEquals(
            [
                'dev'     => ['Dev message 1'],
                'info'    => ['Info message 1', 'Info message 2'],
                'warning' => ['Warning message 1'],
                'error'   => ['Error message 1'],
            ],
            $flashes
        );

        // prod env should not have dev messages
        $flashBag = new FlashBag();
        $transformService = $this->getSut($messageBagMock, $flashBag, 'prod');

        // Transform and check FlashBag contents
        $transformService->transformMessageBagToFlashes();

        $this->assertEquals(
            [
                'info'    => ['Info message 1', 'Info message 2'],
                'warning' => ['Warning message 1'],
                'error'   => ['Error message 1'],
            ],
            $flashBag->all()
        );
    }

    public function testTransformMessageBagToResponseFormat(): void
    {
        // Mock MessageBag contents
        $messageBagMock = $this->getMessageBagMock();

        $flashBag = new FlashBag();
        // Set MessageBag on the TransformMessageBagService
        $transformService = $this->getSut($messageBagMock, $flashBag, 'dev');
        // Transform and check the response format
        $responseFormat = $transformService->transformMessageBagToResponseFormat();
        // dev env should have all messages
        $this->assertEquals(
            [
                'dev'     => ['Dev message 1'],
                'info'    => ['Info message 1', 'Info message 2'],
                'warning' => ['Warning message 1'],
                'error'   => ['Error message 1'],
            ],
            $responseFormat
        );

        // prod env should not have dev messages
        $transformService = $this->getSut($messageBagMock, $flashBag, 'prod');

        // Transform and check FlashBag contents
        $responseFormat = $transformService->transformMessageBagToResponseFormat();

        $this->assertEquals(
            [
                'info'    => ['Info message 1', 'Info message 2'],
                'warning' => ['Warning message 1'],
                'error'   => ['Error message 1'],
            ],
            $responseFormat
        );
    }

    private function getSut($messageBag, $flashBag, $env): TransformMessageBagService
    {
        $sessionMethods = [
            new MockMethodDefinition('getFlashBag', $flashBag),
        ];
        $sessionMock = $this->getMock(Session::class, $sessionMethods);
        $requestMethods = [
            new MockMethodDefinition('getSession', $sessionMock),
        ];

        $requestStackMock = $this->getMock(RequestStack::class, $requestMethods);
        $kernelMethods = [
            new MockMethodDefinition('getEnvironment', $env),
        ];
        $kernelMock = $this->getMock(KernelInterface::class, $kernelMethods);

        return new TransformMessageBagService(
            $kernelMock,
            $messageBag,
            $requestStackMock,
            $this->createMock(RouterInterface::class)
        );
    }

    private function getMessageBagMock()
    {
        $returnValues = collect([
            'dev'  => collect([MessageSerializable::createMessage('dev', 'Dev message 1')]),
            'info' => collect([
                MessageSerializable::createMessage('info', 'Info message 1'),
                MessageSerializable::createMessage('info', 'Info message 2'),
            ]),
            'warning' => collect([MessageSerializable::createMessage('warning', 'Warning message 1')]),
            'error'   => collect([MessageSerializable::createMessage('error', 'Error message 1')]),
        ]);
        $messageBagMethods = [
            new MockMethodDefinition('get', $returnValues),
        ];

        return $this->getMock(MessageBagInterface::class, $messageBagMethods);
    }
}
