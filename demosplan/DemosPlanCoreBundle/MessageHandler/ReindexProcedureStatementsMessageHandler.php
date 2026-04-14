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

use demosplan\DemosPlanCoreBundle\Message\ReindexProcedureStatementsMessage;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReindexProcedureStatementsMessageHandler
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly StatementRepository $statementRepository,
        private readonly ObjectPersisterInterface $statementPersister,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReindexProcedureStatementsMessage $message): void
    {
        $procedureId = $message->procedureId;
        $statementIds = $this->statementRepository->getStatementIdsByProcedureId($procedureId);
        $total = count($statementIds);

        if (0 === $total) {
            return;
        }

        $this->logger->info('Reindexing statements for procedure custom fields', [
            'procedureId' => $procedureId,
            'total'       => $total,
        ]);

        foreach (array_chunk($statementIds, self::BATCH_SIZE) as $batchNum => $batchIds) {
            $statements = $this->statementRepository->findBy(['id' => $batchIds]);
            $this->statementPersister->insertMany($statements);
            unset($statements);

            $this->logger->info('Reindex batch complete', [
                'batch'   => $batchNum + 1,
                'indexed' => count($batchIds),
            ]);
        }

        $this->logger->info('Reindex complete', ['procedureId' => $procedureId, 'total' => $total]);
    }
}
