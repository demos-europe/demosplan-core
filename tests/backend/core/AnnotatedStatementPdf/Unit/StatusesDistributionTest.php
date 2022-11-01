<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AnnotatedStatementPdf\Unit;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfService;
use Tests\Base\UnitTestCase;

/**
 * Tests the method that calculates the statutes distributions for AnnotatedStatementPdfs in
 * a Procedure.
 */
class StatusesDistributionTest extends UnitTestCase
{
    /**
     * @var AnnotatedStatementPdfService
     */
    protected $sut;

    /**
     * @var Procedure
     */
    private $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(AnnotatedStatementPdfService::class);
        $this->procedure = $this->getReference('testProcedure');
    }

    public function testStatusesDistribution(): void
    {
        $annotatedStatementPdf1 = $this->getAnnotatedStatementPdf('pendingBlockedAnnotatedStatementPdf1');
        $annotatedStatementPdf2 = $this->getAnnotatedStatementPdf('notPendingBlockedAnnotatedStatementPdf');
        $annotatedStatementPdf3 = $this->getAnnotatedStatementPdf('reviewedBlockedAnnotatedStatementPdf');
        $annotatedStatementPdf4 = $this->getAnnotatedStatementPdf('annotatedStatementPdf4');
        $annotatedStatementPdf5 = $this->getAnnotatedStatementPdf('annotatedStatementPdf5');
        $annotatedStatementPdf6 = $this->getAnnotatedStatementPdf('annotatedStatementPdf6');
        $annotatedStatementPdf7 = $this->getAnnotatedStatementPdf('annotatedStatementPdf7');
        $annotatedStatementPdf8 = $this->getAnnotatedStatementPdf('annotatedStatementPdf8');
        $annotatedStatementPdf9 = $this->getAnnotatedStatementPdf('annotatedStatementPdf9');

        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());

        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(9, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(0, $absolutes['readyToReview']);
        $this->assertEquals(0, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(0, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(0, $absolutes['readyToConvert']);
        $this->assertEquals(0, $absolutes[AnnotatedStatementPdf::TEXT_REVIEW]);
        $this->assertEquals(0, $absolutes[AnnotatedStatementPdf::CONVERTED]);

        $annotatedStatementPdf1->setStatus(AnnotatedStatementPdf::READY_TO_REVIEW);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(8, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(1, $absolutes['readyToReview']);

        $annotatedStatementPdf2->setStatus(AnnotatedStatementPdf::BOX_REVIEW);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(7, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(1, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);

        $annotatedStatementPdf3->setStatus(AnnotatedStatementPdf::REVIEWED);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(6, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(1, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::REVIEWED]);

        $annotatedStatementPdf4->setStatus(AnnotatedStatementPdf::READY_TO_CONVERT);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(5, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(1, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(1, $absolutes['readyToConvert']);

        $annotatedStatementPdf5->setStatus(AnnotatedStatementPdf::TEXT_REVIEW);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(4, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(1, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(1, $absolutes['readyToConvert']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::TEXT_REVIEW]);

        $annotatedStatementPdf6->setStatus(AnnotatedStatementPdf::CONVERTED);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(3, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(1, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(1, $absolutes['readyToConvert']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::TEXT_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::CONVERTED]);

        $annotatedStatementPdf7->setStatus(AnnotatedStatementPdf::READY_TO_REVIEW);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(2, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(2, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(1, $absolutes['readyToConvert']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::TEXT_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::CONVERTED]);

        $annotatedStatementPdf8->setStatus(AnnotatedStatementPdf::REVIEWED);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(2, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(2, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(1, $absolutes['readyToConvert']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::TEXT_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::CONVERTED]);

        $annotatedStatementPdf9->setStatus(AnnotatedStatementPdf::REVIEWED);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(0, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(2, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(3, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(1, $absolutes['readyToConvert']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::TEXT_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::CONVERTED]);

        $annotatedStatementPdf9->setStatus(AnnotatedStatementPdf::READY_TO_CONVERT);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(0, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(2, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(2, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(2, $absolutes['readyToConvert']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::TEXT_REVIEW]);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::CONVERTED]);

        $annotatedStatementPdf5->setStatus(AnnotatedStatementPdf::CONVERTED);
        $percentageDistribution = $this->sut->getPercentageDistribution($this->procedure);
        $this->assertEquals(9, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        $this->assertEquals(0, $absolutes[AnnotatedStatementPdf::PENDING]);
        $this->assertEquals(2, $absolutes['readyToReview']);
        $this->assertEquals(1, $absolutes[AnnotatedStatementPdf::BOX_REVIEW]);
        $this->assertEquals(2, $absolutes[AnnotatedStatementPdf::REVIEWED]);
        $this->assertEquals(2, $absolutes['readyToConvert']);
        $this->assertEquals(0, $absolutes[AnnotatedStatementPdf::TEXT_REVIEW]);
        $this->assertEquals(2, $absolutes[AnnotatedStatementPdf::CONVERTED]);
    }

    private function getAnnotatedStatementPdf(string $reference): AnnotatedStatementPdf
    {
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference($reference);
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::PENDING);
        $annotatedStatementPdf->setProcedure($this->procedure);

        return $annotatedStatementPdf;
    }
}
