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
 * Tests the method that brings an AnnotatedStatementPdf from ready-to-review to box-review.
 */
class SetTextReviewStatusTest extends UnitTestCase
{
    /** @var AnnotatedStatementPdfHandler */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(AnnotatedStatementPdfHandler::class);
    }

    public function testInvalidSetTextReviewStatusPending(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::PENDING);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->setTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidSetTextReviewStatusReadyToReview(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::READY_TO_REVIEW);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->setTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidSetTextReviewStatusReviewed(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::REVIEWED);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->setTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidSetTextReviewStatusTextReview(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::TEXT_REVIEW);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->setTextReviewStatus($annotatedStatementPdf);
    }

    public function testInvalidSetTextReviewStatusConverted(): void
    {
        $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::CONVERTED);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->sut->setTextReviewStatus($annotatedStatementPdf);
    }

    public function testAccessDeniedSetTextReviewStatus(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN10);
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::READY_TO_CONVERT);

        $this->expectException(AccessDeniedException::class);
        $this->sut->setTextReviewStatus($annotatedStatementPdf);
    }

    public function testValidSetTextReviewStatus(): void
    {
        $user = $this->loginUser();
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $this->getReference('annotatedStatementPdf9');
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::READY_TO_CONVERT);
        $this->sut->setTextReviewStatus($annotatedStatementPdf);
        $this->assertEquals($user->getId(), $annotatedStatementPdf->getReviewer()->getId());
        $this->assertEquals(AnnotatedStatementPdf::TEXT_REVIEW, $annotatedStatementPdf->getStatus());
    }

    private function loginUser(): User
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions(['feature_import_statement_pdf']);

        return $user;
    }
}
