<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Import\ImportJob;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Types\ImportJobType;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

readonly class ImportJobQueue
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private MessageBagInterface $messageBag,
    ) {
    }

    /**
     * Creates an {@link ImportJob} for the given upload and persists it, so {@link ImportJobProcessor}
     * can pick it up. On failure, the job is marked as failed instead of being left dangling.
     */
    public function queue(
        Procedure $procedure,
        User $user,
        string $uploadHash,
        string $fileName,
        ImportJobType $importType,
        string $confirmTranslationKey,
        string $errorTranslationKey,
    ): ImportJob {
        $job = $this->buildJob($procedure, $user, $uploadHash, $fileName, $importType);

        try {
            $this->entityManager->persist($job);
            $this->entityManager->flush();
        } catch (Exception $e) {
            $this->handleQueueFailure($job, $fileName, $importType, $errorTranslationKey, $e);

            return $job;
        }

        $this->logger->info('Import job queued', [
            'jobId'       => $job->getId(),
            'fileName'    => $fileName,
            'procedureId' => $procedure->getId(),
            'importType'  => $importType->value,
        ]);

        $this->messageBag->add(
            'confirm',
            $confirmTranslationKey,
            [
                'fileName' => $fileName,
                'jobId'    => $job->getId(),
            ]
        );

        return $job;
    }

    private function buildJob(
        Procedure $procedure,
        User $user,
        string $uploadHash,
        string $fileName,
        ImportJobType $importType,
    ): ImportJob {
        $job = new ImportJob();
        $job->setProcedure($procedure);
        $job->setUser($user);
        $job->setImportType($importType);
        $job->setFilePath($uploadHash);
        $job->setFileName($fileName);

        // capture the current organisation context for background processing
        $currentOrga = $user->getCurrentOrganisation();
        if ($currentOrga instanceof Orga) {
            $job->setOrganisation($currentOrga);
        }

        return $job;
    }

    private function handleQueueFailure(
        ImportJob $job,
        string $fileName,
        ImportJobType $importType,
        string $errorTranslationKey,
        Exception $e,
    ): void {
        $this->logger->error('Failed to queue import job', [
            'fileName'   => $fileName,
            'importType' => $importType->value,
            'exception'  => $e->getMessage(),
            'trace'      => $e->getTraceAsString(),
        ]);

        // Mark job as failed if it was created
        $job->markAsFailed($e->getMessage());
        $this->entityManager->flush();

        $this->messageBag->add('error', $errorTranslationKey, ['fileName' => $fileName]);
    }
}
