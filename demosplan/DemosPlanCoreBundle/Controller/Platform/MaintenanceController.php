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
use DemosEurope\DemosplanAddon\Contracts\Events\DailyMaintenanceEventInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Event\DailyMaintenanceEvent;
use demosplan\DemosPlanCoreBundle\Exception\NoDesignatedStateException;
use demosplan\DemosPlanCoreBundle\Logic\EmailAddressService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\News\ProcedureNewsService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementHandler;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

class MaintenanceController extends BaseController
{
    public function __construct(private readonly DraftStatementHandler $draftStatementHandler, private readonly EmailAddressService $emailAddressService, private readonly EntityContentChangeService $entityContentChangeService, private readonly FileService $fileService, private readonly MailService $mailService, private readonly ParameterBagInterface $parameterBag, private readonly PermissionsInterface $permissions, private readonly ProcedureHandler $procedureHandler, private readonly ProcedureNewsService $procedureNewsService)
    {
    }

    /**
     * User facing page for active service mode.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(path: '/servicemode', name: 'core_service_mode')]
    public function serviceModeAction(GlobalConfigInterface $globalConfig)
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
     *
     * @DplanPermissions("area_demosplan")
     */
    #[Route(path: '/_heartbeat', name: 'core_server_heartbeat')]
    public function heartbeatAction(): Response
    {
        return new Response('OK');
    }

    /**
     * Maintenance tasks run as cron job.
     *
     * These tasks are run regularily *and* require a session which is
     * why they are currently managed in this action
     *
     * @DplanPermissions("area_demosplan")
     *
     * @param string $key
     *
     * @throws Throwable
     */
    #[Route(path: '/maintenance/{key}', name: 'core_maintenance')]
    public function maintenanceTasksAction(
        EventDispatcherInterface $eventDispatcher,
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

        $logger->info('Maintenance key is valid');
        $frequency = $request->get('frequency');

        switch ($frequency) {
            case 'daily':
                $logger->info('Starting daily maintenance Tasks');
                try {
                    $event = new DailyMaintenanceEvent();
                    $eventDispatcher->dispatch($event, DailyMaintenanceEventInterface::class);
                } catch (Exception $exception) {
                    $this->logger->error('Daily maintenance task failed for: event subscriber(s).', [$exception]);
                }

                try {
                    // Notfication-Email for public agencies regarding soon ending  phases
                    $logger->info('Maintenance: sendNotificationEmailOfDeadlineForPublicAgencies');
                    $this->procedureHandler->sendNotificationEmailOfDeadlineForPublicAgencies();
                } catch (Exception $exception) {
                    $this->logger->error('Daily maintenance task failed for: sendNotificationEmailOfDeadlineForPublicAgencies.', [$exception]);
                }

                if ($this->permissions->hasPermission('feature_send_email_on_procedure_ending_phase_send_mails')) {
                    try {
                        // Create Mails for all unsubmitted draftstatemetns of soon ending procedures.
                        $logger->info('Maintenance: createMailsForUnsubmittedDraftsInSoonEndingProcedures()');
                        $numberOfCreatedMails = $this->createMailsForUnsubmittedDraftsInSoonEndingProcedures(7);
                        $logger->info('Maintenance: createMailsForUnsubmittedDraftsInSoonEndingProcedures(). Number of created mail_send entries:', [$numberOfCreatedMails]);
                    } catch (Exception $exception) {
                        $this->logger->error('Daily maintenance task failed for: createMailsForUnsubmittedDraftsInSoonEndingProcedures.', [$exception]);
                    }
                }

                $logger->info('Maintenance: switchStatesOfNewsOfToday');
                $this->setStateOfNewsOfToday();

                if ($this->permissions->hasPermission('feature_auto_switch_to_procedure_end_phase')) {
                    try {
                        $logger->info('Maintenance: switchToEvaluationPhasesOnEndOfParticipationPhase()');
                        $this->procedureHandler->switchToEvaluationPhasesOnEndOfParticipationPhase();
                    } catch (Exception $exception) {
                        $this->logger->error('Daily maintenance task failed for: switchToEvaluationPhasesOnEndOfParticipationPhase.', [$exception]);
                    }
                }

                // Create notification mails with newly assigned tasks/segments to users
                if ($this->permissions->hasPermission('feature_send_assigned_task_notification_email')) {
                    try {
                        $logger->info('Maintenance: sendAssignedTaskNotificationMails()');
                        $numberOfCreatedNotificationMails = $this->entityContentChangeService->sendAssignedTaskNotificationMails(Segment::class);
                        $logger->info('Maintenance: sendAssignedTaskNotificationMails(). Number of created mail_send entries:', [$numberOfCreatedNotificationMails]);
                    } catch (Exception $exception) {
                        $this->logger->error('Daily maintenance task failed for: sendAssignedTaskNotificationMails.', [$exception]);
                    }
                }

                // delete orphan email addresses
                $this->deleteOrphanEmailAddresses($logger);

                $this->purgeSentEmails();

                if ($globalConfig->doDeleteRemovedFiles()) {
                    try {
                        $logger->info('Maintenance: remove soft deleted Files');
                        $filesDeleted = $this->fileService->deleteSoftDeletedFiles();
                        $logger->info('Maintenance: Soft deleted files deleted: ', [$filesDeleted]);

                        $logger->info('Maintenance: remove orphaned Files');
                        $filesDeleted = $this->fileService->removeOrphanedFiles();
                        $logger->info('Maintenance: Orphaned Files deleted: ', [$filesDeleted]);

                        $logger->info('Maintenance: remove temporary upload Files');
                        $filesDeleted = $this->fileService->removeTemporaryUploadFiles();
                        $logger->info('Maintenance: Temporary Uploaded Files deleted: ', [$filesDeleted]);

                        $logger->info('Maintenance: check for deleted Files');
                        $this->fileService->checkDeletedFiles();
                    } catch (Exception $exception) {
                        $this->logger->error('Daily maintenance task failed for: delete obsolete files.', [$exception]);
                    }
                }
                break;

            default:
                break;
        }

        // return result as JSON
        return new JsonResponse(
            [
                'code'    => 100,
                'success' => 'true',
            ]
        );
    }

    /**
     * Checks if any EmailAddress entities are not referenced anymore and if so deletes them.
     */
    protected function deleteOrphanEmailAddresses(LoggerInterface $logger): void
    {
        try {
            $numberOfDeletedEmailAddresses = $this->emailAddressService->deleteOrphanEmailAddresses();
            $logger->info("Deleted $numberOfDeletedEmailAddresses orphan email addresses");
        } catch (Exception $e) {
            $logger->error('Daily maintenance task failed for: removing orphan email addresses.', [$e]);
        }
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    protected function createMailsForUnsubmittedDraftsInSoonEndingProcedures(int $exactlyDaysToGo): int
    {
        // handle externals:
        $numberOfCreatedExternalMails = $this->draftStatementHandler
            ->createEMailsForUnsubmittedDraftStatementsOfProcedureOfUser($exactlyDaysToGo, false);

        // handle internals:
        $numberOfCreatedInternalMails = $this->draftStatementHandler
            ->createEMailsForUnsubmittedDraftStatementsOfProcedureOfUser($exactlyDaysToGo, true);

        return $numberOfCreatedExternalMails + $numberOfCreatedInternalMails;
    }

    /**
     * Set the state of all news, which are "prepared" to set state today, to the determined state.
     *
     * @throws Exception
     */
    protected function setStateOfNewsOfToday(): void
    {
        try {
            $newsToSetState = $this->procedureNewsService->getNewsToSetStateToday();

            $successfulSwitches = 0;
            foreach ($newsToSetState as $news) {
                try {
                    $this->procedureNewsService->setState($news);
                    ++$successfulSwitches;
                } catch (NoDesignatedStateException $e) {
                    $this->getLogger()->error('Set state of news failed, because designated state is not defined.', [$e]);
                } catch (Exception $e) {
                    $this->getLogger()->error("Set state of the news with ID {$news->getId()} failed", [$e]);
                }
            }

            $this->getLogger()->info("Set states of {$successfulSwitches} news.");
        } catch (Exception $e) {
            $this->getLogger()->error('Daily maintenance task failed for: switching of news state.', [$e]);
        }
    }

    private function purgeSentEmails(): void
    {
        try {
            $deleted = $this->mailService->deleteAfterDays((int) $this->parameterBag->get('email_delete_after_days'));
            $this->logger->info("Deleted $deleted old emails");
        } catch (Exception $e) {
            $this->logger->error('Daily maintenance task failed for: Delete old emails', [$e]);
        }
    }
}
