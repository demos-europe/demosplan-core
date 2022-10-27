<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AnnotatedStatementPdf\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanStatementBundle\Repository\AnnotatedStatementPdf\AnnotatedStatementPdfRepository;
use Tests\Base\FunctionalTestCase;

/**
 * Tests the method that calculates the statutes distributions for AnnotatedStatementPdfs in
 * a Procedure.
 */
class RollbackAllBoxReviewStatusTest extends FunctionalTestCase
{
    /**
     * @var AnnotatedStatementPdfHandler
     */
    protected $sut;

    /**
     * @var AnnotatedStatementPdfRepository
     */
    protected $annotatedStatementPdfRepository;

    /**
     * @var Procedure
     */
    private $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(AnnotatedStatementPdfHandler::class);
        $this->procedure = $this->getReference('testProcedure');
        $this->annotatedStatementPdfRepository = self::$container->get(AnnotatedStatementPdfRepository::class);
    }

    public function testRollbackAllBoxReviewStatus(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions(['feature_import_statement_pdf']);

        $annotatedStatementPdfs = $this->sut->findAll();
        foreach ($annotatedStatementPdfs as $annotatedStatementPdf) {
            $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::PENDING);
            $annotatedStatementPdf->setProcedure($this->procedure);
        }
        $this->assertStatuses(0, 0);
        $this->assertReviewer(null, $annotatedStatementPdfs);

        $this->sut->rollbackBoxReviewStatus();
        $this->assertStatuses(0, 0);
        $this->assertReviewer(null, $annotatedStatementPdfs);

        foreach ($annotatedStatementPdfs as $annotatedStatementPdf) {
            $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::READY_TO_REVIEW);
        }
        $this->assertStatuses(count($annotatedStatementPdfs), 0);
        $this->assertReviewer(null, $annotatedStatementPdfs);

        $this->sut->rollbackBoxReviewStatus();
        $this->assertStatuses(count($annotatedStatementPdfs), 0);
        $this->assertReviewer(null, $annotatedStatementPdfs);

        foreach ($annotatedStatementPdfs as $annotatedStatementPdf) {
            $this->sut->setBoxReviewStatus($annotatedStatementPdf);
        }

        $this->assertStatuses(0, count($annotatedStatementPdfs));
        $this->assertReviewer($user->getId(), $annotatedStatementPdfs);

        $this->sut->rollbackBoxReviewStatus();
        foreach ($annotatedStatementPdfs as $annotatedStatementPdf) {
            $this->getEntityManager()->refresh($annotatedStatementPdf);
        }
        $this->getReference('pendingBlockedAnnotatedStatementPdf1');
        $annotatedStatementPdfs = $this->sut->findByStatus(AnnotatedStatementPdf::READY_TO_REVIEW);
        $this->assertStatuses(count($annotatedStatementPdfs), 0);
        $this->assertReviewer(null, $annotatedStatementPdfs);
    }

    private function assertStatuses(
        int $expectedReadyToReviewCount,
        int $expectedBoxReviewCount
    ): void {
        $readyToReviewCount = $this->annotatedStatementPdfRepository->getAnnotatedStatementPdfsByStatusCount(
            $this->procedure->getId(),
            AnnotatedStatementPdf::READY_TO_REVIEW
        );

        $boxReviewCount = $this->annotatedStatementPdfRepository->getAnnotatedStatementPdfsByStatusCount(
            $this->procedure->getId(),
            AnnotatedStatementPdf::BOX_REVIEW
        );
        $this->assertEquals($expectedReadyToReviewCount, $readyToReviewCount);
        $this->assertEquals($expectedBoxReviewCount, $boxReviewCount);
    }

    /**
     * @param AnnotatedStatementPdf[] $annotatedStatementPdfs
     */
    private function assertReviewer(
        ?string $expectedReviewerId,
        array $annotatedStatementPdfs
    ): void {
        foreach ($annotatedStatementPdfs as $annotatedStatementPdf) {
            $reviewerId = null === $annotatedStatementPdf->getReviewer()
                ? null
                : $annotatedStatementPdf->getReviewer()->getId();

            $this->assertEquals($expectedReviewerId, $reviewerId);
        }
    }
}
