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
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxDeliveryProcessor;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxDeliveryService;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxEntryResult;
use demosplan\DemosPlanCoreBundle\Message\OcxProcessDeliveryMessage;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryFailure;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Processes the entries of a completed OCX delivery.
 */
#[AsMessageHandler]
readonly class OcxProcessDeliveryMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ManagerRegistry $managerRegistry,
        private OcxDeliveryProcessor $processor,
        private OcxDeliveryService $deliveryService,
    ) {
    }

    public function __invoke(OcxProcessDeliveryMessage $message): void
    {
        $delivery = $this->entityManager->find(OcxDelivery::class, $message->deliveryId);
        if (!$delivery instanceof OcxDelivery || OcxDeliveryStatus::QUEUED !== $delivery->getStatus()) {
            $this->logger->warning('OCX delivery not found or not queued', ['deliveryId' => $message->deliveryId]);

            return;
        }

        $delivery->setStatus(OcxDeliveryStatus::PROCESSING);
        $delivery->setProgress(0, new DateTime());
        $this->entityManager->flush();

        $failure = null;
        try {
            if ($this->deliveryService->isProcessable($delivery)) {
                $results = $this->processor->process($delivery);
                // the processor clears the entity manager between parts
                $delivery = $this->findDelivery($message->deliveryId);
                $delivery->setResults(
                    array_map(static fn (OcxEntryResult $result): array => $result->toArray(), $results),
                    OcxEntryResult::countOutcomes($results)
                );
                $delivery->setProgress(count($results), new DateTime());
                $delivery->setStatus(OcxDeliveryStatus::DONE);
            } else {
                $this->logger->warning('OCX delivery is no longer accepted', ['deliveryId' => $message->deliveryId]);
                $failure = OcxDeliveryFailure::NO_LONGER_ACCEPTED;
            }
        } catch (Throwable $e) {
            $this->logger->error('OCX delivery processing failed', ['deliveryId' => $message->deliveryId, 'exception' => $e]);
            if (!$this->entityManager->isOpen()) {
                $this->managerRegistry->resetManager();
            }
            $failure = OcxDeliveryFailure::INTERNAL_ERROR;
        }
        if (null !== $failure) {
            $delivery = $this->findDelivery($message->deliveryId);
            $this->deliveryService->discardLeftovers($delivery);
            $delivery->fail($failure);
        }
        $delivery->setProcessedAt(new DateTime());
        $this->entityManager->flush();

        $this->logger->info('OCX delivery processed', [
            'deliveryId' => $message->deliveryId,
            'status'     => $delivery->getStatus()->value,
            'counts'     => $delivery->getCounts(),
        ]);
    }

    private function findDelivery(string $deliveryId): OcxDelivery
    {
        return $this->entityManager->find(OcxDelivery::class, $deliveryId)
            ?? throw new RuntimeException('OCX delivery is gone');
    }
}
