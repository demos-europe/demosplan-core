<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AnnotatedStatementPdf\Unit;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanStatementBundle\Exception\InvalidStatusTransitionException;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Tests\Base\UnitTestCase;

/**
 * Tests the method that brings an AnnotatedStatementPdf from text-review back to ready-to-convert.
 */
class PauseTextReviewStatusTest extends UnitTestCase
{
    /** @var AnnotatedStatementPdfHandler */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(AnnotatedStatementPdfHandler::class);
    }

    public function testInvalidPauseTextReviewStatusPending(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::PENDING);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->pauseTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidPauseTextReviewStatusReadyToReview(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::READY_TO_REVIEW);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->pauseTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidPauseTextReviewStatusBoxReview(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::BOX_REVIEW);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->pauseTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidPauseTextReviewStatusReviewed(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::REVIEWED);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->pauseTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidPauseTextReviewStatusReadyToConvert(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::READY_TO_CONVERT);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->pauseTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidPauseTextReviewStatusConverted(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::CONVERTED);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->pauseTextReviewStatus($annotatedStatementPdf);
    }

    public function testAccessDeniedPauseTextReviewStatus(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN10);
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::TEXT_REVIEW);

        $this->expectException(AccessDeniedException::class);
        $this->sut->pauseTextReviewStatus($annotatedStatementPdf);
    }

    public function testValidPauseTextReviewStatus(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::TEXT_REVIEW);
        $this->sut->pauseTextReviewStatus($annotatedStatementPdf);
        $this->assertNull($annotatedStatementPdf->getReviewer());
        $this->assertEquals(
            AnnotatedStatementPdf::READY_TO_CONVERT,
            $annotatedStatementPdf->getStatus()
        );
    }

    private function loginUser(): User
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions(['feature_import_statement_pdf']);

        return $user;
    }
}
