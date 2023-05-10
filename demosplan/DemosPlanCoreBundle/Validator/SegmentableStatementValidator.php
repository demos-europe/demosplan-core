<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\StatementAlreadySegmentedException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;

class SegmentableStatementValidator
{
    /** @var StatementHandler */
    private $statementHandler;

    /**
     * @var CurrentUserService
     */
    private $currentUser;

    public function __construct(CurrentUserService $currentUser, StatementHandler $statementHandler)
    {
        $this->currentUser = $currentUser;
        $this->statementHandler = $statementHandler;
    }

    /**
     * @throws LockedByAssignmentException
     * @throws StatementAlreadySegmentedException
     * @throws StatementNotFoundException
     */
    public function validate(string $statementId): void
    {
        $statement = $this->statementHandler->getStatement($statementId);
        if (null === $statement) {
            throw StatementNotFoundException::createFromId($statementId);
        }
        $statementAssignee = $statement->getAssignee();
        if (null !== $statementAssignee) {
            $currentUser = $this->currentUser->getUser();
            if ($currentUser->getId() !== $statementAssignee->getId()) {
                throw new LockedByAssignmentException('error.statement.not.assigned');
            }
        }
        if ($statement->isAlreadySegmented()) {
            throw new StatementAlreadySegmentedException('error.statement.already.segmented');
        }
    }
}
