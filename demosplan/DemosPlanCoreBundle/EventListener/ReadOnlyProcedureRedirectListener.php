<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * Inside a read-only procedure the dashboard permission is reduced away, so a bookmarked
 * dashboard link would end in an access denied bounce to the home page. This listener
 * redirects such links to the statement list, the landing page of a read-only procedure.
 *
 * It is scoped to the dashboard route on purpose: the permission set stays the security
 * boundary, this is only a convenience redirect, not a parallel route allowlist.
 */
#[AsEventListener(event: 'kernel.controller', priority: 5)]
readonly class ReadOnlyProcedureRedirectListener
{
    public function __construct(
        private CurrentProcedureService $currentProcedureService,
        private RouterInterface $router,
    ) {
    }

    public function onKernelController(ControllerEvent $controllerEvent): void
    {
        if (!$controllerEvent->isMainRequest()) {
            return;
        }

        if ('DemosPlan_procedure_dashboard' !== $controllerEvent->getRequest()->attributes->get('_route')) {
            return;
        }

        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure || !$procedure->isReadOnly()) {
            return;
        }

        $redirectResponse = new RedirectResponse(
            $this->router->generate('dplan_procedure_statement_list', ['procedureId' => $procedure->getId()])
        );
        $controllerEvent->setController(fn () => $redirectResponse);
    }
}
