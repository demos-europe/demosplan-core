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
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Message\FetchStatementGeoDataMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class FetchStatementGeoDataMessageHandler
{
    public function __construct(
        private readonly StatementService $statementService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FetchStatementGeoDataMessage $message): void
    {
        try {
            if (true === $this->globalConfig->getUseFetchAdditionalGeodata()) {
                $this->logger->info('Fetch Statement Geodata... ');
                $geoDataFetched = $this->statementService->processScheduledFetchGeoData();
                if ($geoDataFetched > 0) {
                    $this->logger->info('Statement Geodata fetched: '.$geoDataFetched);
                }
            }
        } catch (Exception $e) {
            $this->logger->error('FetchGeodata failed', [$e]);
        }
    }
}
