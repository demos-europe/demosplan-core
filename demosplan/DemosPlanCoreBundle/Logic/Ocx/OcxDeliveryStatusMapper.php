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

use DateTimeInterface;
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryFailure;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;

/**
 * Describes a delivery to its sender.
 */
class OcxDeliveryStatusMapper
{
    private const FAILURE_DETAILS = [
        OcxDeliveryFailure::NO_LONGER_ACCEPTED->value => 'The procedure no longer accepts deliveries from this sender.',
        OcxDeliveryFailure::STALLED->value            => 'Processing stopped and was given up.',
        OcxDeliveryFailure::INTERNAL_ERROR->value     => 'The delivery could not be processed.',
    ];

    /**
     * The delivery as described by `delivery.schema.json`.
     *
     * @return array<string, mixed>
     */
    public function toStatus(OcxDelivery $delivery): array
    {
        $isOpen = OcxDeliveryStatus::OPEN === $delivery->getStatus();

        return [
            'deliveryId'         => $delivery->getId(),
            'procedureId'        => $delivery->getProcedure()->getId(),
            'status'             => $delivery->getStatus()->value,
            'failure'            => $this->getFailure($delivery),
            'missingParts'       => $isOpen ? $this->getMissingParts($delivery) : [],
            'missingAttachments' => $isOpen ? $this->getMissingAttachments($delivery) : [],
            'progress'           => $this->getProgress($delivery),
            'counts'             => $delivery->getCounts(),
            'results'            => $delivery->getResults(),
        ];
    }

    /**
     * @return array{code: string, detail: string}|null
     */
    private function getFailure(OcxDelivery $delivery): ?array
    {
        $failure = $delivery->getFailure();

        return null === $failure ? null : ['code' => $failure->value, 'detail' => self::FAILURE_DETAILS[$failure->value]];
    }

    /**
     * @return array{processedEntries: int, totalEntries: int, updatedAt: string}|null
     */
    private function getProgress(OcxDelivery $delivery): ?array
    {
        $updatedAt = $delivery->getProgressUpdatedAt();
        if (null === $updatedAt || OcxDeliveryStatus::ABORTED === $delivery->getStatus()) {
            return null;
        }

        return [
            'processedEntries' => $delivery->getProcessedEntries(),
            'totalEntries'     => (int) $delivery->getManifest()['counts']['entries'],
            'updatedAt'        => $updatedAt->format(DateTimeInterface::RFC3339),
        ];
    }

    /**
     * @return list<int>
     */
    public function getMissingParts(OcxDelivery $delivery): array
    {
        $totalParts = (int) $delivery->getManifest()['counts']['parts'];

        return array_values(array_diff(range(1, $totalParts), array_keys($delivery->getReceivedParts())));
    }

    /**
     * @return list<string>
     */
    public function getMissingAttachments(OcxDelivery $delivery): array
    {
        $announced = array_column($delivery->getManifest()['attachments'], 'id');

        return array_values(array_diff($announced, array_keys($delivery->getReceivedAttachments())));
    }
}
