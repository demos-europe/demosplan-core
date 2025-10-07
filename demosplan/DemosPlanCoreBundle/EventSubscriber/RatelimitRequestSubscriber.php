<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RatelimitRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly HeaderSanitizerService $headerSanitizer,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
        private readonly RateLimiterFactory $jwtTokenLimiter,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->getRequest()->headers->has('X-JWT-Authorization')) {
            // Sanitize header values to prevent header injection
            $authHeader = $this->headerSanitizer->sanitizeAuthHeader(
                $event->getRequest()->headers->get('X-JWT-Authorization')
            );

            $limiter = $this->jwtTokenLimiter->create(md5($authHeader));

            // avoid brute force attacks with captured JWT tokens
            // token is reset on every request
            if (true === $limiter->consume(1)->isAccepted()) {
                if (false === $this->parameterBag->get('ratelimit_api_enable')) {
                    throw new TooManyRequestsHttpException();
                }
                $this->logger->warning('Rate limiting for api is disabled but would have been active now.');
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest'],
        ];
    }
}
