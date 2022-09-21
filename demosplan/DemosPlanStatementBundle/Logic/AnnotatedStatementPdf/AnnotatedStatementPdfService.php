<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\ValueObject\PercentageDistribution;
use demosplan\DemosPlanStatementBundle\Repository\AnnotatedStatementPdf\AnnotatedStatementPdfRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

class AnnotatedStatementPdfService
{
    /**
     * @var AnnotatedStatementPdfRepository
     */
    private $annotatedStatementPdfRepository;

    public function __construct(AnnotatedStatementPdfRepository $annotatedStatementPdfRepository)
    {
        $this->annotatedStatementPdfRepository = $annotatedStatementPdfRepository;
    }

    /**
     * @return AnnotatedStatementPdf
     */
    public function findById(string $id): ?AnnotatedStatementPdf
    {
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->annotatedStatementPdfRepository->find($id);

        return $annotatedStatementPdf;
    }

    public function getPercentageDistribution(Procedure $procedure): PercentageDistribution
    {
        $pendingCount = $procedure->getAnnotatedStatementPdfsByStatusCount(
            AnnotatedStatementPdf::PENDING
        );
        $readyToReviewCount = $procedure->getAnnotatedStatementPdfsByStatusCount(
            AnnotatedStatementPdf::READY_TO_REVIEW
        );
        $boxesReviewCount = $procedure->getAnnotatedStatementPdfsByStatusCount(
            AnnotatedStatementPdf::BOX_REVIEW
        );
        $reviewedCount = $procedure->getAnnotatedStatementPdfsByStatusCount(
            AnnotatedStatementPdf::REVIEWED
        );
        $readyToConvertCount = $procedure->getAnnotatedStatementPdfsByStatusCount(
            AnnotatedStatementPdf::READY_TO_CONVERT
        );
        $textReviewCount = $procedure->getAnnotatedStatementPdfsByStatusCount(
            AnnotatedStatementPdf::TEXT_REVIEW
        );
        $convertedCount = $procedure->getAnnotatedStatementPdfsByStatusCount(
            AnnotatedStatementPdf::CONVERTED
        );
        $totalCount = $procedure->getAnnotatedStatementPdfsCount();

        return new PercentageDistribution(
            $totalCount,
            [
                AnnotatedStatementPdf::PENDING          => $pendingCount,
                'readyToReview'                         => $readyToReviewCount,
                AnnotatedStatementPdf::BOX_REVIEW       => $boxesReviewCount,
                AnnotatedStatementPdf::REVIEWED         => $reviewedCount,
                'readyToConvert'                        => $readyToConvertCount,
                AnnotatedStatementPdf::TEXT_REVIEW      => $textReviewCount,
                AnnotatedStatementPdf::CONVERTED        => $convertedCount,
            ]
        );
    }

    public function findByOriginalStatementId(string $originalStatementId): ?AnnotatedStatementPdf
    {
        return $this->annotatedStatementPdfRepository->findOneBy([
            'statement' => $originalStatementId,
        ]);
    }

    /**
     * @param AnnotatedStatementPdf[] $annotatedStatementPdfs
     *
     * @return AnnotatedStatementPdf[]
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObjects(array $annotatedStatementPdfs): array
    {
        return
            $this
                ->annotatedStatementPdfRepository
                ->updateObjects($annotatedStatementPdfs);
    }

    /**
     * Sets all AnnotatedStatementPdf in {@link AnnotatedStatementPdf::BOX_REVIEW} status back
     * to {@link AnnotatedStatementPdf::READY_TO_REVIEW} status.
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function rollbackBoxReviewStatus()
    {
        return $this->annotatedStatementPdfRepository->rollbackBoxReviewStatus();
    }

    /**
     * Sets all AnnotatedStatementPdf in {@link AnnotatedStatementPdf::TEXT_REVIEW} status back
     * to {@link AnnotatedStatementPdf::READY_TO_CONVERT} status.
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function rollbackTextReviewStatus()
    {
        return $this->annotatedStatementPdfRepository->rollbackTextReviewStatus();
    }

    /**
     * @return AnnotatedStatementPdf[]
     */
    public function findAll(): array
    {
        return $this->annotatedStatementPdfRepository->findAll();
    }

    /**
     * @return array<int, AnnotatedStatementPdf>
     */
    public function findByStatus(string $status): array
    {
        return $this->annotatedStatementPdfRepository->findByStatus($status);
    }
}
