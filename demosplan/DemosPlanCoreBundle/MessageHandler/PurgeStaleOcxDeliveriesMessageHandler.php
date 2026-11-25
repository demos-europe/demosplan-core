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
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxDeliveryHousekeeping;
use demosplan\DemosPlanCoreBundle\Message\PurgeStaleOcxDeliveriesMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Aborts OCX deliveries that were never completed and fails those whose processing never ends,
 * deleting what they leave behind.
 */
#[AsMessageHandler]
final readonly class PurgeStaleOcxDeliveriesMessageHandler
{
    private const MAX_OPEN_DAYS = 7;

    /**
     * Beyond any regular processing time. A delivery whose worker stopped is not taken up again:
     * the sender delivers it anew, and the entries already applied are skipped as existing.
     */
    private const MAX_UNFINISHED_HOURS = 2;

    /**
     * A delivery that reported progress since is still being worked on.
     */
    private const STALLED_AFTER = '-15 minutes';

    public function __construct(
        private OcxDeliveryHousekeeping $housekeeping,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeStaleOcxDeliveriesMessage $message): void
    {
        try {
            $aborted = $this->housekeeping->abortStale(new DateTime('-'.self::MAX_OPEN_DAYS.' days'));
        } catch (Throwable $e) {
            $this->logger->error('Error aborting stale OCX deliveries', ['exception' => $e]);

            return;
        }
        if ($aborted > 0) {
            $this->logger->info('Stale OCX deliveries aborted', ['count' => $aborted]);
        }

        try {
            $this->housekeeping->failStalled(
                new DateTime('-'.self::MAX_UNFINISHED_HOURS.' hours'),
                new DateTime(self::STALLED_AFTER)
            );
        } catch (Throwable $e) {
            $this->logger->error('Error failing stalled OCX deliveries', ['exception' => $e]);
        }
    }
}
