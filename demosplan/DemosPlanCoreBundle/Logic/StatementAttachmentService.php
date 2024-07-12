<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Repository\StatementAttachmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class StatementAttachmentService extends CoreService
{
    public function __construct(private readonly StatementAttachmentRepository $attachmentRepository)
    {
    }

    public function createOriginalAttachment(Statement $statement, File $file): StatementAttachment
    {
        return $this->createAttachment(
            $statement,
            $file,
            StatementAttachment::SOURCE_STATEMENT
        );
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteOriginalAttachment(Statement $statement): Statement
    {
        $remainingAttachments = new ArrayCollection();
        $attachmentsToDelete = [];
        /** @var StatementAttachment $attachment */
        foreach ($statement->getAttachments() as $attachment) {
            if (StatementAttachment::SOURCE_STATEMENT === $attachment->getType()) {
                $attachmentsToDelete[] = $attachment;
                continue;
            }
            $remainingAttachments->add($attachment);
        }
        $this->attachmentRepository->persistAndDelete([], $attachmentsToDelete);
        $statement->setAttachments($remainingAttachments);

        return $statement;
    }

    /**
     * Will create a new {@link Collection} and new {@link StatementAttachment} entries but
     * will keep the same {@link File} reference in each copied attachment as was set in
     * the original attachment.
     *
     * @param Collection<int, StatementAttachment> $originalAttachments
     *
     * @return Collection<int, StatementAttachment>
     */
    public function copyAttachmentEntries(
        Collection $originalAttachments,
        Statement $targetStatement,
        ?Procedure $targetProcedure = null
    ): Collection {
        $copiedAttachments = new ArrayCollection();
        foreach ($originalAttachments as $originalAttachment) {
            $copiedAttachments->add($this->copyToStatement(
                $originalAttachment,
                $targetStatement,
                $targetProcedure
            ));
        }

        return $copiedAttachments;
    }

    private function copyToStatement(
        StatementAttachment $attachment,
        Statement $statement,
        ?Procedure $targetProcedure
    ): StatementAttachment {
        $type = $attachment->getType();
        $file = $attachment->getFile();
        if (null !== $targetProcedure) {
            $file->setProcedure($targetProcedure);
        }

        return $this->createAttachment(
            $statement,
            $file,
            $type
        );
    }

    /**
     * @param array<int|string, StatementAttachment> $attachments
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteStatementAttachments(array $attachments): void
    {
        $this->attachmentRepository->persistAndDelete([], $attachments);
    }

    public function createAttachment(Statement $statement, File $file, string $type): StatementAttachment
    {
        $attachment = new StatementAttachment();
        $attachment->setFile($file);
        $attachment->setType($type);
        $attachment->setStatement($statement);

        return $attachment;
    }
}
