<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class MaintenanceController extends BaseController
{
    /**
     * User facing page for active service mode.
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[DplanPermissions('area_demosplan')]
    #[Route(path: '/servicemode', name: 'core_service_mode')]
    public function serviceMode(GlobalConfigInterface $globalConfig)
    {
        /** @var GlobalConfig $globalConfig */
        if (false === $globalConfig->getPlatformServiceMode()) {
            return $this->redirectToRoute('core_home');
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanCore/servicemode.html.twig',
            [
                'templateVars' => [
                    'title' => 'service.mode',
                ],
                'serviceMode'  => true,
            ]
        );
    }

    /**
     * Simple Action to evaluate response code for heartbeat monitoring.
     */
    #[DplanPermissions('area_demosplan')]
    #[Route(path: '/_heartbeat', name: 'core_server_heartbeat')]
    public function heartbeat(): Response
    {
        return new Response('OK');
    }

    /**
     * Maintenance tasks run as cron job.
     *
     * DEPRECATED: This endpoint is deprecated and maintained only for backward compatibility
     * with existing cron jobs. New maintenance tasks are now handled through Symfony Scheduler
     * and Messenger (see DailyMaintenanceScheduler).
     *
     * @deprecated Use Symfony Scheduler (DailyMaintenanceScheduler) instead
     *
     * These tasks are run regularly *and* require a session which is
     * why they are currently managed in this action
     *
     * @param string $key
     *
     * @throws Throwable
     */
    #[DplanPermissions('area_demosplan')]
    #[Route(path: '/maintenance/{key}', name: 'core_maintenance')]
    public function maintenanceTasks(
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        Request $request,
        $key,
    ): JsonResponse {
        // @improve T17071

        /** @var GlobalConfig $globalConfig */
        // Überprüfen des Maintenance Keys (parameters.yml)
        if ($key !== $globalConfig->getMaintenanceKey()) {
            $logger->warning('Maintenance key is NOT valid');

            return new JsonResponse(
                [
                    'code'    => 404,
                    'success' => 'false',
                ]
            );
        }

        $logger->warning('DEPRECATED: /maintenance endpoint called. Please migrate to Symfony Scheduler (DailyMaintenanceScheduler).');
        $logger->info('Maintenance key is valid');
        $frequency = $request->get('frequency');

        switch ($frequency) {
            case 'daily':
                $logger->warning('Daily maintenance tasks are now handled by DailyMaintenanceScheduler at 1:00 AM.');
                $logger->warning('Cronjob should be disabled - tasks are scheduled automatically via Symfony Scheduler.');
                $logger->info('No messages dispatched - tasks will run via scheduler to avoid duplicate execution.');
                break;

            default:
                $logger->warning("Unknown frequency: {$frequency}");
                break;
        }

        // Return immediately - tasks are handled by DailyMaintenanceScheduler
        // NOT dispatching messages to avoid duplicate execution
        return new JsonResponse(
            [
                'code'    => 100,
                'success' => 'true',
                'message' => 'DEPRECATED: Maintenance tasks are now handled by Symfony Scheduler (DailyMaintenanceScheduler). Please disable this cronjob.',
            ]
        );
    }
}
