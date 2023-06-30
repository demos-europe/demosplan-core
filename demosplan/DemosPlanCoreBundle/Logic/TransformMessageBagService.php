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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Tightenco\Collect\Support\Collection;

class TransformMessageBagService
{
    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    public function __construct(
        private readonly MessageBagInterface $messageBag,
        RequestStack $requestStack,
        private readonly RouterInterface $router
    ) {
        $this->flashBag = $requestStack->getSession()->getFlashBag();
    }

    public function transformMessageBagToFlashes(): void
    {
        $this->messageBag->get()->each(function (Collection $messages, $severity) {
            $messages->each(function ($message) use ($severity) {
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
        return $this->messageBag->get()->mapWithKeys(
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
