<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Application\Header;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DetermineProcedureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly ProcedureService $procedureService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 10]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        // variable name in route might be procedure or procedureId
        $procedureId = $this->determineProcedureId($request);
        // Assessment table uses ident
        if (null === $procedureId) {
            $procedureId = $request->get('ident');
        }

        $this->logger->debug('Procedure Id found: '.$procedureId);

        if (!empty($procedureId)) {
            $procedure = $this->procedureService->getProcedure($procedureId);

            if ($procedure instanceof Procedure) {
                $this->currentProcedureService->setProcedure($procedure);
            }
        }

        // save current procedure
        $this->permissions->setProcedure($procedure ?? null);
    }

    /**
     * Determine the procedure ID to use in the session from the given URL or the HTTP request header.
     * If both are set they must be equal. If only one of them is set the procedure ID from the URL
     * has precedence.
     */
    protected function determineProcedureId(Request $request): ?string
    {
        // try to get it from the URL
        $urlProcedureId = $request->get('procedure', $request->get('procedureId'));
        $headerProcedureId = $request->headers->get(Header::PROCEDURE_ID);
        // if both are set with different value then log it
        if ((null !== $urlProcedureId && '' !== $urlProcedureId)
            && (null !== $headerProcedureId && '' !== $headerProcedureId)
            && $urlProcedureId !== $headerProcedureId) {
            $compact = compact('urlProcedureId', 'headerProcedureId');
            $this->logger->info('procedure ID mismatch', [$compact, debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5)]);
        }

        // when called from outside a procedure header might be set as "undefined"
        if ('undefined' === $headerProcedureId) {
            $headerProcedureId = null;
        }

        return $urlProcedureId ?? $headerProcedureId;
    }
}
