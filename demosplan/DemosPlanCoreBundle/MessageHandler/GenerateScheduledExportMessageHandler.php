<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ExportSchedule;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ScheduledExportJob;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobContextRestorer;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFailureReason;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobStatusWriter;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportResponseFileStore;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\FileNameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\Message\GenerateScheduledExportMessage;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;

/**
 * Runs one firing of a recurring Synopse export in the background, stores the result as a file and
 * records the outcome on the {@see ScheduledExportJob} so the completion/failure mail and the
 * download page can act on it.
 *
 * The existing synchronous exporter is reused unchanged; this handler only re-establishes the
 * request-scoped context (query parameters, current user, current procedure, permissions) that the
 * exporter would normally get from the HTTP request that a manual export runs inside of.
 */
#[AsMessageHandler]
class GenerateScheduledExportMessageHandler
{
    private const DELETE_AFTER_INTERVALS = [
        ExportSchedule::FREQUENCY_DAILY   => '+2 days',
        ExportSchedule::FREQUENCY_WEEKLY  => '+2 weeks',
        ExportSchedule::FREQUENCY_MONTHLY => '+2 months',
    ];

    private const MAIL_TEMPLATE = 'dm_stellungnahme';

    private const MAIL_LANGUAGE = 'de_DE';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportJobContextRestorer $contextRestorer,
        private readonly ExportJobFailureReason $failureReason,
        private readonly ExportJobStatusWriter $statusWriter,
        private readonly ExportResponseFileStore $fileStore,
        private readonly FileNameGenerator $fileNameGenerator,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly JsonApiActionService $jsonApiActionService,
        private readonly LoggerInterface $logger,
        private readonly MailService $mailService,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly SegmentsByStatementsExporter $exporter,
        private readonly StatementExportTagFilter $statementExportTagFilter,
        private readonly StatementResourceType $statementResourceType,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(GenerateScheduledExportMessage $message): void
    {
        $job = $this->entityManager->find(ScheduledExportJob::class, $message->getJobId());
        if (!$job instanceof ScheduledExportJob) {
            $this->logger->error('Scheduled export job not found', ['jobId' => $message->getJobId()]);

            return;
        }

        $job->setStatus(ScheduledExportJob::STATUS_PROCESSING);
        $job->setModifiedDate(new DateTime());
        $this->entityManager->flush();

        $requestPushed = false;
        try {
            $schedule = $this->entityManager->find(ExportSchedule::class, $job->getScheduleId());
            if (!$schedule instanceof ExportSchedule) {
                throw new ProcedureNotFoundException("Export schedule not found: {$job->getScheduleId()}");
            }

            $procedure = $this->entityManager->find(Procedure::class, $schedule->getProcedureId());
            if (!$procedure instanceof Procedure) {
                throw ProcedureNotFoundException::createFromId($schedule->getProcedureId());
            }

            $queryParameters = [];
            parse_str($schedule->getParameters(), $queryParameters);
            $request = new Request($queryParameters);
            $this->requestStack->push($request);
            $requestPushed = true;

            $this->contextRestorer->restore(
                $schedule->getUserId(),
                $procedure->getCustomer()->getId(),
                $schedule->getProcedureId()
            );

            $statementEntities = $this->loadFilteredStatements($request, $schedule->getProcedureId());

            $response = new StreamedResponse(function () use ($statementEntities): void {
                $exportedDoc = $this->exporter->exportAllXlsx($this->statementExportTagFilter, ...$statementEntities);
                $exportedDoc->save('php://output');
            });

            $storedFile = $this->fileStore->store(
                $response,
                $schedule->getUserId(),
                $schedule->getProcedureId(),
                $this->fileNameGenerator->getSynopseFileName($procedure, 'xlsx')
            );

            $job->setFileHash($storedFile->getFileHash());
            $job->setFileName($storedFile->getFileName());
            $job->setStatus(ScheduledExportJob::STATUS_COMPLETED);
            $this->sendCompletionMail($job);
        } catch (Throwable $e) {
            $this->logger->error('Scheduled Synopse export failed', ['jobId' => $message->getJobId(), 'exception' => $e]);
            $job->setStatus(ScheduledExportJob::STATUS_FAILED);
            $job->setErrorMessage($this->failureReason->forThrowable($e));
            $this->sendFailureMail($job);
        } finally {
            if ($requestPushed) {
                $this->requestStack->pop();
            }
            $job->setDeleteAfter($this->calculateDeleteAfter(isset($schedule) ? $schedule->getFrequency() : null));
            $job->setModifiedDate(new DateTime());
            $this->statusWriter->persist($job);
        }
    }

    private function loadFilteredStatements(Request $request, string $procedureId): array
    {
        $tagsFilter = $request->query->all('tagsFilter');
        $tagConditions = $this->statementExportTagFilter->buildStatementTagConditions(
            $tagsFilter,
            $this->statementResourceType,
            $procedureId
        );

        $statementEntities = array_values(
            $this->jsonApiActionService->getObjectsByQueryParams(
                $request->query,
                $this->statementResourceType,
                $tagConditions
            )->getList()
        );

        return $this->statementExportTagFilter->filterStatementsByTags($statementEntities, $tagsFilter);
    }

    /**
     * Retention window is double the schedule's own interval, per the export-deletion AC. Falls back
     * to the shortest interval if the schedule could not be loaded, so a job never stays undeletable.
     */
    private function calculateDeleteAfter(?string $frequency): DateTime
    {
        $interval = self::DELETE_AFTER_INTERVALS[$frequency] ?? self::DELETE_AFTER_INTERVALS[ExportSchedule::FREQUENCY_DAILY];

        return new DateTime($interval);
    }

    /**
     * A failed notification must not turn a completed export into a failed one, so this only logs.
     */
    private function sendCompletionMail(ScheduledExportJob $job): void
    {
        $recipient = $this->resolveRecipient($job);
        if (!$recipient instanceof User) {
            return;
        }

        $downloadUrl = $this->router->generate(
            'DemosPlan_scheduled_export_download_page',
            ['jobId' => $job->getId()],
            RouterInterface::ABSOLUTE_URL
        );

        $this->sendMail(
            $job,
            $recipient,
            '@DemosPlanCore/Export/email_scheduled_export_completed.html.twig',
            'email.subject.scheduled.export.completed',
            ['downloadUrl' => $downloadUrl],
            'Could not send scheduled export completion mail'
        );
    }

    /**
     * A failed notification must not mask the export failure itself - already logged by the caller -
     * so this only logs its own, separate failure.
     */
    private function sendFailureMail(ScheduledExportJob $job): void
    {
        $recipient = $this->resolveRecipient($job);
        if (!$recipient instanceof User) {
            return;
        }

        $this->sendMail(
            $job,
            $recipient,
            '@DemosPlanCore/Export/email_scheduled_export_failed.html.twig',
            'email.subject.scheduled.export.failed',
            ['reason' => $job->getErrorMessage()],
            'Could not send scheduled export failure mail'
        );
    }

    /**
     * @param array<string, mixed> $templateVars
     */
    private function sendMail(
        ScheduledExportJob $job,
        User $recipient,
        string $templateFile,
        string $subjectTranslationKey,
        array $templateVars,
        string $logMessageOnFailure,
    ): void {
        try {
            $mail = [];
            $mail['mailbody'] = $this->twig->load($templateFile)->renderBlock('body_plain', [
                'templateVars' => $templateVars,
                'projectName'  => $this->globalConfig->getProjectName(),
            ]);
            $mail['mailsubject'] = $this->translator->trans($subjectTranslationKey);

            $this->mailService->sendMail(
                self::MAIL_TEMPLATE,
                self::MAIL_LANGUAGE,
                $recipient->getEmail(),
                '',
                '',
                '',
                'extern',
                $mail
            );
        } catch (Exception $exception) {
            $this->logger->error($logMessageOnFailure, ['jobId' => $job->getId(), 'exception' => $exception]);
        }
    }

    private function resolveRecipient(ScheduledExportJob $job): ?User
    {
        $recipient = $this->entityManager->find(User::class, $job->getUserId());
        if (!$recipient instanceof User) {
            $this->logger->error('Scheduled export job references a user that no longer exists', [
                'jobId'  => $job->getId(),
                'userId' => $job->getUserId(),
            ]);

            return null;
        }

        return $recipient;
    }
}
