<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\StatementAnonymizeRpcEvent;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;

class StatementReportEntryFactory extends AbstractReportEntryFactory
{
    public function createFinalMailEntry(
        Statement $statement,
        string $mailSubject,
        array $emailAttachmentNames
    ): ReportEntry {
        $procedureId = $statement->getPId();
        $statementId = $statement->getId();
        $externId = $statement->getExternId();

        $data = [
            'procedureId'          => $procedureId,
            'statementId'          => $statementId,
            'externId'             => $externId,
            'ident'                => $procedureId,
            'mailSubject'          => $mailSubject,
            'emailAttachmentNames' => $emailAttachmentNames,
        ];

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_FINAL_MAIL);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifier($procedureId);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_FINAL_MAIL);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createSubmittedStatementEntry(array $statement): ReportEntry
    {
        $statement = $this->stripStatementReportData($statement);
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_ADD);
        $entry->setUser($statement['user']);
        $entry->setIdentifier($statement['procedure']['id']);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setMessage(Json::encode($statement, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createStatementSynchronizationInSource(User $user, Statement $sourceStatement): ReportEntry
    {
        $message = [
            'externId' => $sourceStatement->getExternId(),
        ];

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_STATEMENT_SYNC_INSOURCE);
        $entry->setUser($user);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_STATEMENT);
        $entry->setIdentifier($sourceStatement->getProcedure()->getId());
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createStatementSynchronizationInTarget(Statement $targetStatement): ReportEntry
    {
        $message = [
            'externId' => $targetStatement->getExternId(),
        ];

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_STATEMENT_SYNC_INTARGET);
        $entry->setUser(null);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_STATEMENT);
        $entry->setIdentifier($targetStatement->getProcedure()->getId());
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createStatementCopiedEssentialsEntry(
        Statement $sourceStatement,
        Statement $copiedStatement,
        string $identifier
    ): ReportEntry {
        $sourceProcedure = $sourceStatement->getProcedure();
        $targetProcedure = $copiedStatement->getProcedure();

        $message = [
            'sourceProcedureId'       => $sourceProcedure->getId(),
            'sourceProcedureName'     => $sourceProcedure->getName(),
            'targetProcedureId'       => $targetProcedure->getId(),
            'targetProcedureName'     => $targetProcedure->getName(),
            'copiedStatement'         => $copiedStatement->getId(),
            'copiedStatementExternId' => $copiedStatement->getExternId(),
            'sourceStatement'         => $sourceStatement->getId(),
            'sourceStatementExternId' => $sourceStatement->getExternId(),
        ];

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_COPY);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($identifier);
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createStatementCopiedEntry(Statement $statement): ReportEntry
    {
        $message = $this->generateMessageForCopyReport($statement);

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_COPY);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));
        $entry->setIdentifier($statement->getPId());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_STATEMENT);

        return $entry;
    }

    public function createDeletionEntry(Statement $statement): ReportEntry
    {
        $message = $this->generateMessageForCopyReport($statement);
        $message['mapFile'] = $statement->getMapFile();
        $message['oId'] = $statement->getOId();

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_DELETE);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));
        $entry->setIdentifier($statement->getPId());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_STATEMENT);

        return $entry;
    }

    public function createMovedStatementEntry(Statement $movedStatement, string $identifier): ReportEntry
    {
        $message = [
            'sourceProcedureId'            => $movedStatement->getMovedFromProcedureId(),
            'sourceProcedureName'          => $movedStatement->getMovedFromProcedureName(),
            'targetProcedureId'            => $movedStatement->getProcedureId(),
            'targetProcedureName'          => $movedStatement->getProcedure()->getName(),
            'movedStatementId'             => $movedStatement->getId(),
            'movedStatementExternId'       => $movedStatement->getExternId(),
            'placeholderStatementId'       => $movedStatement->getPlaceholderStatement()->getId(),
            'placeholderStatementExternId' => $movedStatement->getFormerExternId(),
        ];

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_MOVE);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifier($identifier);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createStatementCreatedEntry(array $statement): ReportEntry
    {
        $statement = $this->stripStatementReportData($statement);
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_ADD);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($statement['procedure']['id']);
        $entry->setMessage(Json::encode($statement, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createUpdateEntry(Statement $statement): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setMessage($this->generateMessageForUpdateReport($statement));
        $entry->setIdentifier($statement->getPId());
        // Note added by 50df631e80d3ff7468393db105469953dd4d33e7: "this should be procedure, right?"
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_STATEMENT);

        return $entry;
    }

    public function createViewedEntry(
        $statementId,
        $procedureId,
        $accessMap
    ): ReportEntry {
        $message = ['statementId' => $statementId];
        $user = '';
        if (!empty($accessMap)) {
            $user = $accessMap['user'];
        }

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_VIEW);
        $entry->setUser($user);
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));
        $entry->setIdentifier($procedureId);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);

        return $entry;
    }

    /**
     * @throws CustomerNotFoundException
     * @throws UserNotFoundException
     */
    public function createAnonymizationEntry(string $category, StatementAnonymizeRpcEvent $event): ReportEntry
    {
        $statement = $event->getStatement();
        $procedureId = $statement->getProcedureId();

        $entry = $this->createReportEntry();
        $entry->setCustomer($this->currentCustomerProvider->getCurrentCustomer());
        $entry->setCategory($category);
        $entry->setIdentifier($procedureId);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_STATEMENT);
        $entry->setUser($event->getCurrentUser()->getUser());
        $entry->setMessage(
            [
                'id'        => $statement->getId(),
                'procedure' => ['id' => $procedureId],
                'externId'  => $statement->getExternId(),
            ]
        );

        return $entry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $reportEntry = parent::createReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_STATEMENT);

        return $reportEntry;
    }

    /**
     * Generates a message for a {@link ReportEntry}.
     * This message is holding relevant information about the state of the statement before and after the update.
     * Because the context of the call of this method, is an update, the given statement will always have a parent (original statement).
     */
    private function generateMessageForUpdateReport(Statement $statement): array
    {
        $meta = $statement->getMeta();

        $metaArray = [
            'orgaName'           => $meta->getOrgaName(),
            'orgaDepartmentName' => $meta->getOrgaDepartmentName(),
            'orgaStreet'         => $meta->getOrgaStreet(),
            'orgaPostalCode'     => $meta->getOrgaPostalCode(),
            'orgaCity'           => $meta->getOrgaCity(),
            'orgaEmail'          => $meta->getOrgaEmail(),
            'caseWorkerName'     => $meta->getCaseWorkerName(),
            'authorName'         => $meta->getAuthorName(),
            'submitName'         => $meta->getSubmitName(),
        ];

        $message = [
            'ident'             => $statement->getIdent(),
            'title'             => $statement->getTitle(),
            'text'              => $statement->getText(),
            'priority'          => $statement->getPriority(),
            'phase'             => $statement->getPhase(),
            'status'            => $statement->getStatus(),
            'file'              => $statement->getFile(),
            'mapFile'           => $statement->getMapFile(),
            'externId'          => $statement->getExternId(),
            'uId'               => $statement->getUserId(),
            'oId'               => $statement->getOId(),
            'polygon'           => $statement->getPolygon(),
            'planningDocument'  => $statement->getPlanningDocument(),
            'reasonParagraph'   => $statement->getReasonParagraph(),
            'recommendation'    => $statement->getRecommendation(),
            'memo'              => $statement->getMemo(),
            'toSendPerMail'     => $statement->getToSendPerMail(),
            'negativeStatement' => $statement->getNegativeStatement(),
            'deleted'           => $statement->getDeleted(),
            'publcAllowed'      => $statement->getPublicAllowed(),
            'publicVerified'    => $statement->getPublicVerified(),
            'elementId'         => $statement->getElementId(),
            'created'           => $statement->getCreated()->getTimestamp(),
            'modified'          => $statement->getModified()->getTimestamp(),
            'send'              => $statement->getSend()->getTimestamp(),
            'submit'            => $statement->getSubmit(),
            'meta'              => $metaArray,
        ];

        $element = $statement->getElement();
        if (null !== $element) {
            $message['element'] = [
                'ident'     => $element->getIdent(),
                'title'     => $element->getTitle(),
                'text'      => $element->getText(),
                'icon'      => $element->getIcon(),
                'category'  => $element->getCategory(),
                'order'     => $element->getOrder(),
                'pId'       => $element->getPId(),
                'documents' => $element->getDocuments(),
                'children'  => $element->getChildren(),
                'enabled'   => $element->getEnabled(),
                'deleted'   => $element->getDeleted(),
            ];
        }

        return $message;
    }

    /**
     * Generates a message for the report of copying a statement.
     *
     * @return array message for the reportentry
     */
    private function generateMessageForCopyReport(Statement $newStatement): array
    {
        $meta = $newStatement->getMeta();

        $metaArray = [
            'orgaName'           => null,
            'orgaDepartmentName' => null,
            'orgaStreet'         => null,
            'orgaPostalCode'     => null,
            'orgaCity'           => null,
            'orgaEmail'          => null,
            'caseWorkerName'     => null,
            'authorName'         => null,
            'submitName'         => null,
        ];

        if (null !== $meta) {
            $metaArray = [
                'orgaName'           => $meta->getOrgaName(),
                'orgaDepartmentName' => $meta->getOrgaDepartmentName(),
                'orgaStreet'         => $meta->getOrgaStreet(),
                'orgaPostalCode'     => $meta->getOrgaPostalCode(),
                'orgaCity'           => $meta->getOrgaCity(),
                'orgaEmail'          => $meta->getOrgaEmail(),
                'caseWorkerName'     => $meta->getCaseWorkerName(),
                'authorName'         => $meta->getAuthorName(),
                'submitName'         => $meta->getSubmitName(),
            ];
        }

        return [
            'ident'             => $newStatement->getIdent(),
            'title'             => $newStatement->getTitle(),
            'text'              => $newStatement->getText(),
            'created'           => $newStatement->getCreated()->getTimestamp(),
            'modified'          => $newStatement->getModified()->getTimestamp(),
            'send'              => $newStatement->getSend()->getTimestamp(),
            'submit'            => $newStatement->getSubmit(),
            'file'              => $newStatement->getFile(),
            'externId'          => $newStatement->getExternId(),
            'uId'               => $newStatement->getUserId(),
            'polygon'           => $newStatement->getPolygon(),
            'meta'              => $metaArray,
            'planningDocument'  => $newStatement->getPlanningDocument(),
            'reasonParagraph'   => $newStatement->getReasonParagraph(),
            'recommendation'    => $newStatement->getRecommendation(),
            'priority'          => $newStatement->getPriority(),
            'procedure'         => ['id' => $newStatement->getProcedureId()],
            'phase'             => $newStatement->getPhase(),
            'status'            => $newStatement->getStatus(),
            'memo'              => $newStatement->getMemo(),
            'toSendPerMail'     => $newStatement->getToSendPerMail(),
            'negativeStatement' => $newStatement->getNegativeStatement(),
            'deleted'           => $newStatement->getDeleted(),
        ];
    }

    /**
     * Strip some data that is not needed for logging but bloats database size.
     */
    private function stripStatementReportData(array $statement): array
    {
        // remove orga information
        if (array_key_exists('organisation', $statement) && null !== $statement['organisation']) {
            $statement['organisation'] = [
                'id'   => $statement['organisation']['id'],
                'name' => $statement['organisation']['name'],
            ];
        }

        // log only necessary procedure information
        if (array_key_exists('procedure', $statement)) {
            $statement['procedure'] = [
                'id'                       => $statement['procedure']['id'],
                'name'                     => $statement['procedure']['name'],
                'phase'                    => $statement['procedure']['phase'],
                'publicParticipationPhase' => $statement['procedure']['publicParticipationPhase'],
            ];
        }

        return $statement;
    }
}
