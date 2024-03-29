<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class CheckOrgadataMissingSubscriber extends BaseEventSubscriber
{
    /**
     * Some routes may not be checked as it leads to infinite loops and the like.
     */
    private const EXCLUDED_ROUTES = [
        'core_file',
        'core_file_procedure',
        'DemosPlan_user_complete_data',
        'DemosPlan_user_logout',
        'user_update_additional_information',
    ];

    public function __construct(private readonly CurrentUserInterface $currentUser, LoggerInterface $logger, private readonly RouterInterface $router)
    {
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $user = $this->currentUser->getUser();

        // citizens should never need to complete orga data
        if ($user->hasRole(Role::CITIZEN)) {
            return;
        }

        // check whether all mandatory organisation data is given
        // ignore routes, that need to be called even if not all data is provided
        $route = $request->attributes->get('_route');
        $this->logger->debug('checkUser: Route to check OrgadataMissing', [$route]);
        $this->logger->debug('checkUser: User from Session to check OrgadataMissing', ['userName' => $user->getName()]);
        if (!in_array($route, self::EXCLUDED_ROUTES, true) && false === $user->isProfileCompleted()) {
            $this->logger->info('checkUser: Userdata not completed', ['userName' => $user->getName()]);
            $event->setResponse(new RedirectResponse($this->router->generate('DemosPlan_user_complete_data')));
        }
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
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
