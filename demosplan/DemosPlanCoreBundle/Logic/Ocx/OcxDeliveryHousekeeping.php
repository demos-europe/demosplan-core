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
use demosplan\DemosPlanCoreBundle\Repository\OcxDeliveryRepository;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryFailure;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Cleans up after deliveries that were abandoned or failed.
 */
class OcxDeliveryHousekeeping
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly OcxDeliveryFiles $files,
        private readonly OcxDeliveryRepository $deliveryRepository,
    ) {
    }

    /**
     * Aborts deliveries that stayed open for too long and deletes what was uploaded.
     */
    public function abortStale(DateTime $openedBefore): int
    {
        $deliveries = $this->deliveryRepository->findOpenCreatedBefore($openedBefore);
        foreach ($deliveries as $delivery) {
            $this->files->discardReceived($delivery);
            $delivery->setStatus(OcxDeliveryStatus::ABORTED);
        }
        $this->entityManager->flush();

        return count($deliveries);
    }

    /**
     * Fails queued or processing deliveries no worker finishes any more: the message was lost or
     * the worker stopped. A delivery that still progresses is left alone.
     */
    public function failStalled(DateTime $completedBefore, DateTime $progressBefore): int
    {
        $deliveries = $this->deliveryRepository->findUnfinishedCompletedBefore($completedBefore, $progressBefore);
        foreach ($deliveries as $delivery) {
            $this->logger->warning('OCX delivery failed because its processing stalled', [
                'deliveryId'       => $delivery->getId(),
                'status'           => $delivery->getStatus()->value,
                'processedEntries' => $delivery->getProcessedEntries(),
            ]);
            $this->discardLeftovers($delivery);
            $delivery->fail(OcxDeliveryFailure::STALLED);
            $delivery->setProcessedAt(new DateTime());
        }
        $this->entityManager->flush();

        return count($deliveries);
    }

    /**
     * Deletes the parts and every attachment no statement took over, after the delivery failed as
     * a whole. Statements created before the failure keep their files.
     */
    public function discardLeftovers(OcxDelivery $delivery): void
    {
        $this->files->discardReceived($delivery, true);
    }
}
