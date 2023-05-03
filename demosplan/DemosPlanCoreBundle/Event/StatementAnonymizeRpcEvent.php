<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementAnonymizeHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;

class StatementAnonymizeRpcEvent extends RpcEvent
{
    /**
     * Updated but not persisted Statement.
     *
     * @var Statement
     */
    protected $statement;

    /**
     * CurrentUserInterface, which holds the user which is executing the anonymizsation.
     *
     * @var CurrentUserInterface
     */
    protected $currentUser;

    public function __construct(
        array $actions,
        Statement $statement,
        CurrentUserInterface $currentUser
    ) {
        parent::__construct($actions);
        $this->statement = $statement;
        $this->currentUser = $currentUser;
    }

    public function getCurrentUser(): CurrentUserInterface
    {
        return $this->currentUser;
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    /**
     * Returns true if incoming actions contains the request to anonymize text of statement.
     * Returns also true if no text-part is selected or the selected part is already anonymized.
     * Therefore this method does not contains information if actually a part of the statement-text will be anonymized.
     */
    public function isAnonymizeStatementText(): bool
    {
        return array_key_exists(StatementAnonymizeHandler::ANONYMIZE_STATEMENT_TEXT, $this->getActions());
    }

    /**
     * Returns true if incoming actions contains the request to delete attachments of of statement.
     * Returns also true if no attachment is existing to be deleted.
     * Therefore this method does not contains information if actually a attachments will be deleted.
     */
    public function isDeleteStatementAttachments(): bool
    {
        return array_key_exists(StatementAnonymizeHandler::DELETE_STATEMENT_ATTACHMENTS, $this->getActions())
            && true === $this->actions[StatementAnonymizeHandler::DELETE_STATEMENT_ATTACHMENTS]
        ;
    }

    /**
     * Returns true if incoming actions contains the request to anonymize metadata of statement.
     * Returns also true if metadata of statement is already anonymized or empty.
     * Therefore this method does not contains information if actually metadata of the statement will be anonymized.
     */
    public function isAnonymizeStatementMeta(): bool
    {
        return array_key_exists(StatementAnonymizeHandler::ANONYMIZE_STATEMENT_META, $this->getActions())
            && true === $this->actions[StatementAnonymizeHandler::ANONYMIZE_STATEMENT_META]
        ;
    }

    /**
     * Returns true if incoming actions contains the request to delete attachments of of statement.
     * Returns also true if no attachment is existing to be deleted.
     * Therefore this method does not contains information if actually a attachments will be deleted.
     *
     * @throws UserNotFoundException
     */
    public function isDeleteStatementTextHistory(): bool
    {
        return array_key_exists(StatementAnonymizeHandler::DELETE_STATEMENT_TEXT_HISTORY, $this->getActions())
            && true === $this->actions[StatementAnonymizeHandler::DELETE_STATEMENT_TEXT_HISTORY]
            && $this->currentUser->hasPermission('feature_statement_text_history_delete')
        ;
    }
}
