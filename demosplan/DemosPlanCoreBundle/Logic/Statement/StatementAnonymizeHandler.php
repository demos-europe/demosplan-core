<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

class StatementAnonymizeHandler extends CoreHandler
{
    final public const ANONYMIZE_STATEMENT_TEXT = 'anonymizeStatementText';

    final public const DELETE_STATEMENT_ATTACHMENTS = 'deleteStatementAttachments';

    final public const ANONYMIZE_STATEMENT_META = 'anonymizeStatementMeta';

    final public const DELETE_STATEMENT_TEXT_HISTORY = 'deleteStatementTextHistory';

    final public const STATEMENT_ID = 'statementId';

    final public const FIELDS = [
        'actions' => [
            self::ANONYMIZE_STATEMENT_TEXT      => 'string',
            self::DELETE_STATEMENT_ATTACHMENTS  => 'bool',
            self::ANONYMIZE_STATEMENT_META      => 'bool',
            self::DELETE_STATEMENT_TEXT_HISTORY => 'bool',
        ],
        'data'    => [
            self::STATEMENT_ID => 'UUID',
        ],
    ];

    /** @var CurrentUserInterface */
    protected $currentUserInterface;

    public function __construct(
        CurrentUserInterface $currentUserInterface,
        private readonly ManagerRegistry $doctrine,
        MessageBagInterface $messageBag,
        private readonly PermissionsInterface $permissions,
        private readonly StatementAnonymizeService $statementAnonymizeService
    ) {
        parent::__construct($messageBag);
        $this->currentUserInterface = $currentUserInterface;
    }

    /**
     * Handles the RCP related validation and calls the various sub-steps which are required to anonymize a statement
     * and all related content.
     *
     * @param array<string,mixed> $actions
     *
     * @throws InvalidDataException
     * @throws Exception
     */
    public function anonymizeStatement(Statement $statement, array $actions): void
    {
        $em = $this->doctrine->getManager();
        $em->getConnection()->beginTransaction();
        try {
            $this->anonymizeText($actions, $statement);
            $this->anonymizeAttachments($actions, $statement);
            $this->anonymizeMetaData($actions, $statement);
            $this->deleteHistory($actions, $statement);

            $em->getConnection()->commit();
        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * @throws InvalidDataException
     */
    private function anonymizeText(array $actions, Statement $statement): void
    {
        if (array_key_exists(self::ANONYMIZE_STATEMENT_TEXT, $actions)) {
            $this->statementAnonymizeService->anonymizeTextOfStatement(
                $statement,
                $actions[self::ANONYMIZE_STATEMENT_TEXT]
            );
        }
    }

    /**
     * @throws Exception
     */
    private function anonymizeAttachments(array $actions, Statement $statement): void
    {
        if (array_key_exists(self::DELETE_STATEMENT_ATTACHMENTS, $actions)
            && true === $actions[self::DELETE_STATEMENT_ATTACHMENTS]
        ) {
            $this->statementAnonymizeService->deleteAttachments($statement);
        }
    }

    /**
     * @throws InvalidDataException
     */
    private function anonymizeMetaData(array $actions, Statement $statement): void
    {
        if (array_key_exists(self::ANONYMIZE_STATEMENT_META, $actions)
            && true === $actions[self::ANONYMIZE_STATEMENT_META]
        ) {
            $this->statementAnonymizeService->anonymizeUserDataOfStatement(
                $statement,
                true,
                true,
                User::ANONYMOUS_USER_ID,
                false
            );
        }
    }

    /**
     * @throws Exception
     */
    private function deleteHistory(array $actions, Statement $statement): void
    {
        if (array_key_exists(self::DELETE_STATEMENT_TEXT_HISTORY, $actions)
            && true === $actions[self::DELETE_STATEMENT_TEXT_HISTORY]
            && $this->permissions->hasPermission('feature_statement_text_history_delete')
        ) {
            $this->statementAnonymizeService->deleteHistoryOfTextsRecursively($statement, true);
        }
    }
}
