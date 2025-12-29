<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Message\PurgeDeletedProceduresMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeDeletedProceduresMessageHandler
{
    public function __construct(
        private readonly ProcedureHandler $procedureHandler,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeDeletedProceduresMessage $message): void
    {
        $this->logger->info('Purge deleted procedures... ');
        $purgedProcedures = 0;
        try {
            if (true === $this->globalConfig->getUsePurgeDeletedProcedures()) {
                $this->logger->info('PurgeDeletedProcedures');
                $purgedProcedures = $this->procedureHandler->purgeDeletedProcedures(5);
            } else {
                $this->logger->info('Purge deleted procedures is disabled.');
            }
        } catch (Exception $e) {
            $this->logger->error('Purge Procedures failed', [$e]);
        }
        if ($purgedProcedures > 0) {
            $this->logger->info('Purged procedures: '.$purgedProcedures);
        }
    }
}
