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
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\InitializeServiceInterface;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Traits\IsProfilableTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;

class InitializeService implements InitializeServiceInterface
{
    use IsProfilableTrait;

    public function __construct(
        private readonly CurrentUserService $currentUserService,
        private readonly LoggerInterface $logger,
        private readonly MessageBagInterface $messageBag,
        private readonly PermissionsInterface $permissions,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function initialize(array $context): void
    {
        try {
            $request = $this->requestStack->getCurrentRequest();

            if (!$request instanceof Request) {
                return;
            }

            $user = $this->currentUserService->getUser();
            $this->permissions->initPermissions($user, $context);
            $this->permissions->checkProcedurePermission();
            $this->permissions->checkPermissions($context);
        } catch (AccessDeniedException $e) {
            // Wenn der User vorher keine Session hatte, ist eher die Session abgelaufen,
            // als dass es ein echtes AccessDenied ist
            if (null === $request->getSession()->getId()) {
                $this->logger->info('Access Denied nach nicht vorhandener Session: ', [$e]);
                throw new AccessDeniedGuestException();
            }
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Session Initialization not successful', [$e]);
            throw new SessionUnavailableException('Session Initialization not successful: '.$e);
        }
    }
}
