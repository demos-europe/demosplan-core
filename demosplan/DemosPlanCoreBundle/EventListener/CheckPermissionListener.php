<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions as AnnotationDplanPermissions;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions as AttributeDplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\InitializeService;
use Doctrine\Common\Annotations\Reader;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * Perform initial Permissionchecks (former initialize()).
 */
class CheckPermissionListener
{
    /** @var Reader */
    protected $reader;
    /**
     * @var InitializeService
     */
    protected $initializeService;
    /**
     * @var RouterInterface
     */
    protected $router;

    public function __construct(Reader $reader, InitializeService $initializeService, RouterInterface $router)
    {
        $this->initializeService = $initializeService;
        $this->reader = $reader;
        $this->router = $router;
    }

    /**
     * @throws ReflectionException
     */
    public function onControllerRequest(ControllerEvent $event)
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

            // perform initialize with permissions from attribute or annotation
            $this->initializeService->initialize($dplanPermissions);
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
}
