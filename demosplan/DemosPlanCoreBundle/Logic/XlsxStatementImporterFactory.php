<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\StatementSpreadsheetImporterInterface;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\XlsxStatementImport;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class XlsxStatementImporterFactory
{
    public function __construct(
        private readonly EventDispatcherPostInterface $eventDispatcher,
        protected readonly LoggerInterface $logger,
        private readonly StatementRepository $statementRepository,
        private readonly StatementService $statementService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createXlsxStatementImporter(StatementSpreadsheetImporterInterface $excelImporter): XlsxStatementImport
    {
        return new XlsxStatementImport(
            $this->eventDispatcher,
            $excelImporter,
            $this->logger,
            $this->statementRepository,
            $this->statementService,
            $this->entityManager
        );
    }
}
