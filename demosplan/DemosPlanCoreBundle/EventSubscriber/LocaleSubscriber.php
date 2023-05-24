<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Traits\DI\RequiresLoggerTrait;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber extends BaseEventSubscriber
{
    use RequiresLoggerTrait;

    private $defaultLocale;

    private const LOCALE_REQUEST_KEY = '_locale';

    /**
     * @param string $defaultLocale
     */
    public function __construct($defaultLocale = 'de')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        // in case of switching language and stay in dplan space:
        // is $local set on this request?
        if ($locale = $request->attributes->get(self::LOCALE_REQUEST_KEY)) {
            // set into session
            $request->getSession()->set(self::LOCALE_REQUEST_KEY, $locale);
        } else {
            // if no explicit locale has been set on this request, use one from the session
            $locale = $request->getSession()->get(self::LOCALE_REQUEST_KEY, $this->defaultLocale);
            $request->setLocale($locale);
        }

        /**
         * Get Variable DPLAN_LOCALE from apache or nginx config if available
         * Otherwise use "calculated" locale
         * DPLAN_LOCALE will contains "de_plain" or "de" depending on incoming URL.
         * This is used to identifiy the selected language on the client side.
         */
        $localeToSet = $request->server->get('DPLAN_LOCALE', $locale);
        $request->setLocale($localeToSet);
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // must be registered before (i.e. with a higher priority than) the default Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
