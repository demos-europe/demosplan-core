<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Repository\AnnotatedStatementPdf;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;
use Exception;

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
}
