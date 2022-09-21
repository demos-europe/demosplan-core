<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiErrorManagement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Logic\ProductIntelligence\PiErrorManagerAbstract;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiBoxRecognitionRequester;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Psr\Log\LoggerInterface;

class PiBoxRecognitionErrorManager extends PiErrorManagerAbstract
{
    /**
     * @var AnnotatedStatementPdfHandler
     */
    private $annotatedStatementPdfHandler;

    public function __construct(
        PiBoxRecognitionRequester $piCommunication,
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        LoggerInterface $logger,
        int $maxPiRetries
    ) {
        parent::__construct(
            $piCommunication,
            $logger,
            $maxPiRetries
        );
        $this->annotatedStatementPdfHandler = $annotatedStatementPdfHandler;
    }

    /**
     * @param AnnotatedStatementPdf $annotatedStatementPdf
     */
    protected function getNumberOfRetries($annotatedStatementPdf): int
    {
        return $annotatedStatementPdf->getBoxRecognitionPiRetries();
    }

    /**
     * @param AnnotatedStatementPdf $annotatedStatementPdf
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function incrementNumberOfRetries($annotatedStatementPdf): void
    {
        $annotatedStatementPdf->incrementBoxRecognitionPiRetries();
        $this->annotatedStatementPdfHandler->updateObjects([$annotatedStatementPdf]);
    }
}
