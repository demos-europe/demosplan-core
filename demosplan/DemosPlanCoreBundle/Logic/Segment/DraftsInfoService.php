<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;

class DraftsInfoService extends CoreService
{
    public function __construct(private readonly StatementHandler $statementHandler)
    {
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
        if (null === $statement) {
            $this->getLogger()->error('Error: No Statement found for Id: '.$statementId);
            throw StatementNotFoundException::createFromId($statementId);
        }
        $statement->setDraftsListJson($data);
        $this->statementHandler->updateStatementObject($statement);
    }
}
