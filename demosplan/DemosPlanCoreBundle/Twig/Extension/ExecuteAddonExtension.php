<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Event\Plugin\TwigExtensionFormElementsAdminListEvent;
use demosplan\DemosPlanCoreBundle\Event\Plugin\TwigExtensionFormExtraFieldsEvent;
use demosplan\DemosPlanCoreBundle\Event\Plugin\TwigExtensionFormNewProcedureEvent;
use demosplan\DemosPlanCoreBundle\Event\Plugin\TwigExtensionFormParagraphAdminImportOptionEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use Exception;
use Twig\Environment;
use Twig\TwigFunction;

class ExecuteAddonExtension extends ExtensionBase
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('executeAddonFunction', [$this, 'executeAddonFunction']),
            new TwigFunction('extensionPointMarkup', [$this, 'extensionPointMarkup']), // Is this really secure enough?
            new TwigFunction('extensionPointData', [$this, 'extensionPointData']),
        ];
    }

    /**
     * Checks whether plugin is enabled and executes function.
     *
     * @param string $extensionName Extensionname to be called like honeypot_extension
     * @param string $function      Function to be called
     * @param array  $args          Optional Arguments
     *
     * @return string
     */
    public function executeAddonFunction($extensionName, $function, $args = [])
    {
        $hasExtension = $this->getTwig()->hasExtension($extensionName);
        if (!$hasExtension) {
            return '';
        }

        $extension = $this->getTwig()->getExtension($extensionName);
        if (method_exists($extension, $function)) {
            return call_user_func([$extension, $function], $args);
        }

        return '';
    }

    /**
     * Provides an extension point to trigger events to collect markup or
     * alter variables within plugins.
     *
     * @param string $name      event to dispatch
     * @param array  $variables
     *
     * @return string
     *
     * @throws InvalidDataException
     * @throws Exception
     */
    public function extensionPointMarkup($name, $variables = [])
    {
        $eventDispatcher = $this->getEventDispatcher();
        switch ($name) {
            case 'formExtraFields':
                $event = new TwigExtensionFormExtraFieldsEvent();
                $eventDispatcher->post($event);

                return $event->getMarkup();
            case 'formParagraphAdminImportOption':
                $event = new TwigExtensionFormParagraphAdminImportOptionEvent($variables);
                $eventDispatcher->post($event);

                return $event->getMarkup();
            case 'formNewProcedure':
                $event = new TwigExtensionFormNewProcedureEvent();
                $this->getEventDispatcher()->post($event);

                return $event->getMarkup();
            case 'showElementsAdminList':
                $event = new TwigExtensionFormElementsAdminListEvent();
                $this->getEventDispatcher()->post($event);

                return $event->getMarkup();
        }

        return '';
    }

    /**
     * Provide an extension point to trigger events to alter variables within plugins.
     *
     * @param string $name      event to dispatch
     * @param array  $variables
     *
     * @return array
     */
    public function extensionPointData($name, $variables = [])
    {
        return $variables;
    }

    /**
     * @return Environment
     */
    protected function getTwig()
    {
        return $this->container->get('twig');
    }

    /**
     * @return TraceableEventDispatcher
     */
    protected function getEventDispatcher()
    {
        return $this->container->get(TraceableEventDispatcher::class);
    }

    public static function getSubscribedServices(): array
    {
        return [
            TraceableEventDispatcher::class,
            'twig' => Environment::class,
        ];
    }
}
