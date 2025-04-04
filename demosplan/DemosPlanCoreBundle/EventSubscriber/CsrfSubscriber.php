<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly MessageBagInterface $messageBag,
        private readonly LoggerInterface $logger,
        private readonly HeaderSanitizerService $headerSanitizer
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->isMethod('GET')) {
            return;
        }

        $tokenId = $request->request->get('_token');
        if ($request->headers->has('x-csrf-token')) {
            $tokenId = $this->headerSanitizer->sanitizeCsrfToken(
                $request->headers->get('x-csrf-token')
            );
        }

        if (null === $tokenId) {
            $this->messageBag->add('dev', 'error.csrf.missing', ['uri' => $request->getRequestUri()]);
            $this->logger->info('CSRF token missing', ['uri' => $request->getRequestUri()]);

            return;
        }

        $token = $this->csrfTokenManager->getToken($tokenId);

        if ($token instanceof CsrfToken && $this->csrfTokenManager->isTokenValid($token)) {
            // all clear, token is set and valid
            return;
        }

        $this->logger->info('CSRF token invalid', ['uri' => $request->getRequestUri(), 'token' => $tokenId]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest'],
        ];
    }
}
