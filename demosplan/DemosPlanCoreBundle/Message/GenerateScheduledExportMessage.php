<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Message;

/**
 * Tells the background worker which {@see \demosplan\DemosPlanCoreBundle\Entity\Statement\ScheduledExportJob}
 * to run. Everything else the worker needs (parameters, owning user, procedure) is loaded from that
 * job and its parent schedule, rather than carried on the message.
 */
class GenerateScheduledExportMessage
{
    public function __construct(private readonly string $jobId)
    {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
