<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Repository\AnnotatedStatementPdf;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;

class AnnotatedStatementPdfRepository extends CoreRepository
{
    /**
     * @return mixed
     *
     * @throws Exception
     */
    public function rollbackBoxReviewStatus()
    {
        try {
            $em = $this->getEntityManager();

            $query = $em->createQueryBuilder()
                ->update(AnnotatedStatementPdf::class, 'a')
                ->set('a.status', ':newStatus')
                ->set('a.reviewer', ':reviewer')
                ->where('a.status = :currentStatus')
                ->setParameter('newStatus', AnnotatedStatementPdf::READY_TO_REVIEW)
                ->setParameter('reviewer', null)
                ->setParameter('currentStatus', AnnotatedStatementPdf::BOX_REVIEW)
                ->getQuery();

            return $query->execute();
        } catch (Exception $e) {
            $this->logger->error(
                'Error when rolling back Text Review Status in AnnotatedStatementPdfs',
                [$e]
            );
            throw $e;
        }
    }

    /**
     * @return mixed
     *
     * @throws Exception
     */
    public function rollbackTextReviewStatus()
    {
        try {
            $em = $this->getEntityManager();

            $query = $em->createQueryBuilder()
                ->update(AnnotatedStatementPdf::class, 'a')
                ->set('a.status', ':newStatus')
                ->set('a.reviewer', ':reviewer')
                ->where('a.status = :currentStatus')
                ->setParameter('newStatus', AnnotatedStatementPdf::READY_TO_CONVERT)
                ->setParameter('reviewer', null)
                ->setParameter('currentStatus', AnnotatedStatementPdf::TEXT_REVIEW)
                ->getQuery();

            return $query->execute();
        } catch (Exception $e) {
            $this->logger->error(
                'Error when rolling back Text Review Status in AnnotatedStatementPdfs',
                [$e]
            );
            throw $e;
        }
    }

    /**
     * Returns those AnnotatedStatementPdfs which are in Reviewed status and not blocked,
     * filtered by Procedure id.
     *
     * @return array<int, AnnotatedStatementPdf>
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('pdf')
            ->where('pdf.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();
    }

    public function getAnnotatedStatementsPdfWithProcedureId(string $procedureId): Collection
    {
        $query = $this->createQueryBuilder('pdf')
            ->where('pdf.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery()
            ->getResult();

        return new ArrayCollection($query);
    }

    public function getAnnotatedStatementPdfsByStatus(string $procedureId,string $status): Collection
    {
        return $this->getAnnotatedStatementsPdfWithProcedureId($procedureId)->filter(
            function (AnnotatedStatementPdf $annotatedStatementPdf) use ($status) {
                return $status === $annotatedStatementPdf->getStatus();
            }
        );
    }

    public function getAnnotatedStatementPdfsByStatusCount(string $procedureId, string $status): int
    {
        return $this->getAnnotatedStatementPdfsByStatus($procedureId,$status)->count();
    }

    public function getAnnotatedStatementPdfsCount(string $procedureId): int
    {
        return $this->getAnnotatedStatementsPdfWithProcedureId($procedureId)->count();
    }

    /**
     * Returns next AnnotatedStatementPdf to be reviewed.
     *
     * @return string
     */
    public function getNextAnnotatedStatementPdfToReview(string $procedureId): ?string
    {
        return 0 < $this->getAnnotatedStatementPdfsByStatusCount($procedureId, AnnotatedStatementPdf::READY_TO_REVIEW)
            ? $this
                ->getAnnotatedStatementPdfsByStatus($procedureId, AnnotatedStatementPdf::READY_TO_REVIEW)
                ->current()
                ->getId()
            : null;
    }


    /**
     * Returns next AnnotatedStatementPdf ready to be converted to a Statement.
     *
     * @return string
     */
    public function getNextAnnotatedStatementPdfsReadyToConvert(): ?string
    {
        return 0 < $this->getAnnotatedStatementPdfsByStatusCount(AnnotatedStatementPdf::READY_TO_CONVERT)
            ? $this
                ->getAnnotatedStatementPdfsByStatus(AnnotatedStatementPdf::READY_TO_CONVERT)
                ->current()
                ->getId()
            : null;
    }
}
