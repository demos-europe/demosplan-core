<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanStatementBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;

class DraftsInfoService extends CoreService
{
    /** @var StatementHandler */
    private $statementHandler;

    public function __construct(StatementHandler $statementHandler)
    {
        $this->statementHandler = $statementHandler;
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
            $this->getLogger()->error('PI-Communication-Error: No Statement found for Id: '.$statementId);
            throw StatementNotFoundException::createFromId($statementId);
        }
        $statement->setDraftsListJson($data);
        $this->statementHandler->updateStatementObject($statement);
    }
}
