<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;

/**
 * @template-extends CoreRepository<StatementAttachment>
 */
class StatementAttachmentRepository extends CoreRepository
{
    /**
     * Load the source-statement attachments (files with their metadata) of many
     * statements with a single query instead of lazy-loading the attachment
     * collection of every single hydrated statement.
     *
     * @param string[] $statementIds
     *
     * @return array<string, StatementAttachment[]> source attachments keyed by statement id
     */
    public function findSourceAttachmentsByStatementIds(array $statementIds): array
    {
        if ([] === $statementIds) {
            return [];
        }

        $attachments = $this->createQueryBuilder('attachment')
            ->addSelect('file')
            ->join('attachment.file', 'file')
            ->where('attachment.statement IN (:statementIds)')
            ->andWhere('attachment.type = :type')
            ->setParameter('statementIds', $statementIds)
            ->setParameter('type', StatementAttachmentInterface::SOURCE_STATEMENT)
            ->getQuery()
            ->getResult();

        $attachmentsByStatementId = [];
        /** @var StatementAttachment $attachment */
        foreach ($attachments as $attachment) {
            // reading the id off the proxy does not initialize it
            $attachmentsByStatementId[$attachment->getStatement()->getId()][] = $attachment;
        }

        return $attachmentsByStatementId;
    }
}
