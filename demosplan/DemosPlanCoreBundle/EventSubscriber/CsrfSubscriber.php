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
    public const TOKEN_ID = 'csrf';

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly MessageBagInterface $messageBag,
        private readonly LoggerInterface $logger,
        private readonly HeaderSanitizerService $headerSanitizer,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->isMethod('GET')) {
            return;
        }

        // The form field / header carries the token *value*, not its id. The id
        // is the well-known 'csrf' constant matching the value emitted by
        // `csrf_token('csrf')` in twig templates.
        $tokenValue = $request->request->get('_token');
        if ($request->headers->has('x-csrf-token')) {
            $tokenValue = $this->headerSanitizer->sanitizeCsrfToken(
                $request->headers->get('x-csrf-token')
            );
        }

        if (null === $tokenValue || '' === $tokenValue) {
            $this->messageBag->add('dev', 'error.csrf.missing', ['uri' => $request->getRequestUri()]);
            $this->logger->info('CSRF token missing', ['uri' => $request->getRequestUri()]);

            return;
        }

        if ($this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $tokenValue))) {
            return;
        }

        $this->logger->info('CSRF token invalid', ['uri' => $request->getRequestUri()]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest'],
        ];
    }
}
