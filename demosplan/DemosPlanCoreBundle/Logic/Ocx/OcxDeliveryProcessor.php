<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\OcxEntryFailedException;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushOutcome;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushService;
use demosplan\DemosPlanCoreBundle\Types\OcxEntryKind;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function in_array;

/**
 * Applies the entries of a completed delivery and records one result per entry, in entry order.
 *
 * Each entry is its own transaction, so a failing entry fails alone. The entity manager is cleared
 * after every part to keep memory flat on large deliveries.
 */
class OcxDeliveryProcessor
{
    private const PROGRESS_EVERY_ENTRIES = 25;
    private const PROGRESS_EVERY_SECONDS = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly OcxDeliveryFiles $files,
        private readonly OcxStatementCreator $statementCreator,
        private readonly OcxWorkerContext $workerContext,
        private readonly RecommendationPushService $recommendationPushService,
    ) {
    }

    /**
     * @return list<OcxEntryResult> in entry order
     *
     * @throws Throwable when the database is no longer usable; the delivery then fails as a whole
     */
    public function process(OcxDelivery $delivery): array
    {
        $deliveryId = $delivery->getId();
        $procedure = $delivery->getProcedure();
        $context = [
            'userId'         => $delivery->getUser()->getId(),
            'customerId'     => $procedure->getCustomer()->getId(),
            'procedureId'    => $procedure->getId(),
            'organisationId' => $delivery->getOrganisation()?->getId(),
        ];
        $permittedKinds = $delivery->getPermittedKinds();
        $receivedAttachments = $delivery->getReceivedAttachments();
        $usedFileIds = [];
        $parts = $delivery->getReceivedParts();
        ksort($parts);

        $results = [];
        $seenSenders = [];
        $seenTargets = [];
        $reported = ['entries' => 0, 'at' => microtime(true)];

        try {
            foreach ($parts as $part) {
                [$procedure, $sender] = $this->enterContext($context);
                foreach ($this->readEntries($part['path']) as $entry) {
                    $reported = $this->reportProgress($deliveryId, count($results), $reported);
                    $senderId = $entry['senderId'];
                    $kind = OcxEntryKind::from($entry['kind']);

                    if (isset($seenSenders[$senderId])) {
                        $results[] = OcxEntryResult::skipped($senderId, 'duplicate_in_delivery');
                        continue;
                    }
                    $seenSenders[$senderId] = true;

                    if (!in_array($kind->value, $permittedKinds, true)) {
                        $results[] = OcxEntryResult::failed($senderId, 'not_permitted');
                        continue;
                    }

                    try {
                        if (OcxEntryKind::STATEMENT === $kind) {
                            $statement = $this->statementCreator->create($procedure, $sender, $entry, $receivedAttachments);
                            foreach ($statement->fileIds as $fileId) {
                                $usedFileIds[] = $fileId;
                            }
                            $results[] = OcxEntryResult::fromStatement($senderId, $statement);
                        } elseif (isset($seenTargets[$entry['targetId']])) {
                            $results[] = OcxEntryResult::skipped($senderId, 'duplicate_in_delivery');
                        } else {
                            $seenTargets[$entry['targetId']] = true;
                            $results[] = $this->applyResponse($procedure, $senderId, $entry['targetId'], $entry['response']);
                        }
                    } catch (OcxEntryFailedException $e) {
                        $results[] = OcxEntryResult::failed($senderId, $e->reason, $e->getMessage());
                    } catch (Throwable $e) {
                        if (!$this->entityManager->isOpen()) {
                            throw $e;
                        }
                        $this->logger->error('OCX entry failed', ['deliveryId' => $deliveryId, 'senderId' => $senderId, 'exception' => $e]);
                        $results[] = OcxEntryResult::failed($senderId, 'internal_error');
                    }
                    // keeps every flush small and drops whatever a failed entry left unflushed
                    $this->entityManager->clear();
                    [$procedure, $sender] = $this->enterContext($context);
                }
            }
        } finally {
            $this->workerContext->leave();
        }

        // attachments no created statement took over (entry skipped or failed) would stay forever
        $this->files->deleteParts($deliveryId, array_column($parts, 'path'));
        $this->files->deleteAttachments(
            $deliveryId,
            array_diff(array_column($receivedAttachments, 'fileId'), $usedFileIds)
        );

        return $results;
    }

    private function applyResponse(Procedure $procedure, string $senderId, string $targetId, string $response): OcxEntryResult
    {
        $result = $this->recommendationPushService->push($procedure, $targetId, $response);

        return match ($result->outcome) {
            RecommendationPushOutcome::UPDATED                  => OcxEntryResult::updated($senderId, $result->targetId, $result->externId),
            RecommendationPushOutcome::SKIPPED_EMPTY            => OcxEntryResult::skipped($senderId, 'empty_response', $result->targetId, $result->externId),
            RecommendationPushOutcome::FAILED_UNKNOWN_TARGET    => OcxEntryResult::failed($senderId, 'unknown_target'),
            RecommendationPushOutcome::FAILED_FOREIGN_PROCEDURE => OcxEntryResult::failed($senderId, 'foreign_procedure'),
            RecommendationPushOutcome::FAILED_NOT_UPDATABLE     => OcxEntryResult::failed($senderId, 'target_not_updatable'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Throwable
     */
    private function readEntries(string $path): array
    {
        return json_decode($this->files->readPart($path), true, 512, JSON_THROW_ON_ERROR)['entries'];
    }

    /**
     * Writes the progress past the entity manager, which is cleared after every entry.
     *
     * @param array{entries: int, at: float} $reported last reported state
     *
     * @return array{entries: int, at: float} the state reported now, or $reported if it was too early
     */
    private function reportProgress(string $deliveryId, int $processedEntries, array $reported): array
    {
        $now = microtime(true);
        if ($processedEntries - $reported['entries'] < self::PROGRESS_EVERY_ENTRIES
            && $now - $reported['at'] < self::PROGRESS_EVERY_SECONDS
        ) {
            return $reported;
        }

        $this->entityManager->createQueryBuilder()
            ->update(OcxDelivery::class, 'delivery')
            ->set('delivery.processedEntries', ':processedEntries')
            ->set('delivery.progressUpdatedAt', ':updatedAt')
            ->where('delivery.id = :deliveryId')
            ->setParameter('processedEntries', $processedEntries)
            ->setParameter('updatedAt', new DateTime(), Types::DATETIME_MUTABLE)
            ->setParameter('deliveryId', $deliveryId)
            ->getQuery()
            ->execute();

        return ['entries' => $processedEntries, 'at' => $now];
    }

    /**
     * The restored user and procedure must be managed: entries persisted afterwards refer to them.
     *
     * @param array{userId: string, customerId: string, procedureId: string, organisationId: ?string} $context
     *
     * @return array{Procedure, User}
     */
    private function enterContext(array $context): array
    {
        $this->workerContext->enter($context['userId'], $context['customerId'], $context['procedureId'], $context['organisationId']);

        return [
            $this->entityManager->find(Procedure::class, $context['procedureId'])
                ?? throw new RuntimeException('Procedure of the OCX delivery is gone'),
            $this->entityManager->find(User::class, $context['userId'])
                ?? throw new RuntimeException('Sender of the OCX delivery is gone'),
        ];
    }
}
