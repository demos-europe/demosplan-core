<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;
use Illuminate\Support\Collection;

class TransformMessageBagService
{
    private FlashBagInterface $flashBag;
    private readonly string $env;

    public function __construct(
        KernelInterface $kernel,
        private readonly MessageBagInterface $messageBag,
        RequestStack $requestStack,
        private readonly RouterInterface $router
    ) {
        $this->env = $kernel->getEnvironment();
        try {
            // in some cases like console commands, the request stack is not available
            $this->flashBag = $requestStack->getSession()->getFlashBag();
        } catch (Throwable) {
            $this->flashBag = new FlashBag();
        }
    }

    public function transformMessageBagToFlashes(): void
    {
        $this->messageBag->get()->each(function (Collection $messages, $severity) {
            $messages->each(function ($message) use ($severity) {
                // dev messages should only be shown in dev environment
                if (DemosPlanKernel::ENVIRONMENT_DEV === $severity
                    && DemosPlanKernel::ENVIRONMENT_DEV !== $this->env) {
                    return;
                }
                if ($message instanceof LinkMessageSerializable) {
                    $message->prepareUrl($this->router);
                }
                $this->flashBag->add($severity, $message);
            });
        });
    }

    /**
     * @return string[][]
     */
    public function transformMessageBagToResponseFormat(): array
    {
        return $this->messageBag->get()
            // dev messages should only be shown in dev environment
            ->filter(function (Collection $messages, string $severity) {
                // always return any but dev messages
                if (DemosPlanKernel::ENVIRONMENT_DEV !== $severity) {
                    return true;
                }

                // return dev messages only if we are in dev environment
                return DemosPlanKernel::ENVIRONMENT_DEV === $this->env;
            })
            ->mapWithKeys(
                function (Collection $messages, string $severity) {
                    $convertedMessages = $messages->map(
                        function (MessageSerializable $message) {
                            if ($message instanceof LinkMessageSerializable) {
                                $message->prepareUrl($this->router);
                            }

                            return $message->getText();
                        }
                    )->toArray();

                    return [$severity => $convertedMessages];
                }
            )->toArray();
    }
}
