<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Psr\Log\LoggerInterface;

class DraftsInfoService
{
    public function __construct(
        private readonly StatementHandler $statementHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Given a string in json format implementing a draftsList, saves it as a Field for
     * their Statement.
     *
     * @throws StatementNotFoundException
     */
    public function save(string $statementId, string $data): void
    {
        $statement = $this->statementHandler->getStatement($statementId);
        if (!$statement instanceof Statement) {
            $this->logger->error('Error: No Statement found for Id: '.$statementId);
            throw StatementNotFoundException::createFromId($statementId);
        }
        $statement->setDraftsListJson($data);
        $this->statementHandler->updateStatementObject($statement);
    }
}
