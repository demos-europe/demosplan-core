<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions as AnnotationDplanPermissions;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions as AttributeDplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;
use Doctrine\Common\Annotations\Reader;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;

/**
 * Perform initial Permission checks (former initialize()).
 * Permissions are initially set in {@link ConfigurePermissionsListener},
 * Procedure permissions are enhanced in {@link AccessProcedureListener}, general
 * procedure access check is also done in {@link AccessProcedureListener}.
 */
class CheckPermissionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly Reader $reader,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function onControllerRequest(ControllerEvent $event): void
    {
        /*
         * $controller passed can be either a class or a Closure.
         * This is not usual in Symfony but it may happen.
         * If it is a class, it comes in array format
         *
         */
        if (!is_array($controllers = $event->getController())) {
            return;
        }

        [$controller, $methodName] = $controllers;

        if (!$controller instanceof AbstractController) {
            return;
        }

        $reflectionClass = new ReflectionClass($controller);

        // Method
        $reflectionMethod = $reflectionClass->getMethod($methodName);
        try {
            $dplanPermissions = $this->getDplanPermissions($reflectionMethod);

            // check permission with permissions from attribute or annotation
            $this->checkPermission($dplanPermissions);
        } catch (Exception $e) {
            // fallback if everything fails
            $redirectResponse = new RedirectResponse($this->router->generate('core_home'));

            try {
                if ($controller instanceof APIController) {
                    $redirectResponse = $controller->handleApiError($e);
                } elseif ($controller instanceof BaseController) {
                    $redirectResponse = $controller->handleError($e);
                }
            } catch (Exception) {
                // could be thrown in dev mode only
            }

            $event->setController(static fn () => $redirectResponse);
        }
    }

    private function getDplanPermissions(ReflectionMethod $reflectionMethod): array
    {
        $dplanPermissions = [];

        // Check if there is a DplanPermissions-Attribute. If so, get the permissions
        $dplanPermissionsAttributes = $reflectionMethod->getAttributes(AttributeDplanPermissions::class);
        if (0 < count($dplanPermissionsAttributes)) {
            $dplanPermissions = $dplanPermissionsAttributes[0]->newInstance()->getPermissions();
        }

        // If dplanPermissions is still empty, check for annotation
        if ([] === $dplanPermissions) {
            /** @var AnnotationDplanPermissions $dplanPermissionsAnnotation */
            $dplanPermissionsAnnotation = $this->reader->getMethodAnnotation($reflectionMethod, AnnotationDplanPermissions::class);

            if (null !== $dplanPermissionsAnnotation) {
                $dplanPermissions = $dplanPermissionsAnnotation->getPermissions();
            }
        }

        return $dplanPermissions;
    }

    private function checkPermission($dplanPermissions): void
    {
        try {
            $this->permissions->checkPermissions($dplanPermissions);
        } catch (AccessDeniedException $e) {
            // Wenn der User vorher keine Session hatte, ist eher die Session abgelaufen,
            // als dass es ein echtes AccessDenied ist
            if (null === $this->requestStack->getCurrentRequest()?->getSession()->getId()) {
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
