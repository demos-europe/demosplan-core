<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventDispatcher;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdfPage;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceCreationEvent;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceUpdateEvent;
use demosplan\DemosPlanCoreBundle\Event\CheckFileIsUsed;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Exception\ConcurrentEditionException;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AnnotatedStatementPdfPageResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AnnotatedStatementPdfResourceType;
use demosplan\DemosPlanStatementBundle\Exception\InvalidStatusTransitionException;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiBoxRecognitionRequester;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiTextRecognitionRequester;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

class AnnotatedStatementPdfEventSubscriber extends BaseEventSubscriber
{
    /**
     * @var AnnotatedStatementPdfHandler
     */
    private $annotatedStatementPdfHandler;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var PiBoxRecognitionRequester
     */
    private $piBoxRecognitionRequester;

    /**
     * @var PiTextRecognitionRequester
     */
    private $piTextRecognitionRequester;

    /**
     * @var \Doctrine\Persistence\ManagerRegistry
     */
    private $managerRegistry;

    public function __construct(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        CurrentUserInterface $currentUser,
        ManagerRegistry $managerRegistry,
        PiBoxRecognitionRequester $piBoxRecognitionRequester,
        PiTextRecognitionRequester $piTextRecognitionRequester
    ) {
        $this->annotatedStatementPdfHandler = $annotatedStatementPdfHandler;
        $this->currentUser = $currentUser;
        $this->managerRegistry = $managerRegistry;
        $this->piBoxRecognitionRequester = $piBoxRecognitionRequester;
        $this->piTextRecognitionRequester = $piTextRecognitionRequester;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterResourceCreationEvent::class => 'piBoxRecognitionRequest',
            AfterResourceUpdateEvent::class   => 'checkAnnotatedStatementPdfReviewed',
            CheckFileIsUsed::class            => 'checkAnnotatedStatementPdfUsed',
        ];
    }

    /**
     * Requests PI for box recognition in the AnnotatedStatementPdf.
     */
    public function piBoxRecognitionRequest(AfterResourceCreationEvent $event): void
    {
        $targetResourceType = $event->getResourceChange()->getTargetResourceType();
        if (!$targetResourceType instanceof AnnotatedStatementPdfResourceType) {
            return;
        }
        /** @var AnnotatedStatementPdf $annotatedStatementPdf */
        $annotatedStatementPdf = $event->getResourceChange()->getTargetResource();
        $this->piBoxRecognitionRequester->request($annotatedStatementPdf);
    }

    /**
     * Checks if with this Page's update the whole Document is reviewed.
     * If so requests PI for text recognition based on the reviewed boxes.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws InvalidStatusTransitionException
     * @throws UserNotFoundException
     * @throws ConcurrentEditionException
     */
    public function checkAnnotatedStatementPdfReviewed(AfterResourceUpdateEvent $event): void
    {
        $targetResourceType = $event->getResourceChange()->getTargetResourceType();
        if (!$targetResourceType instanceof AnnotatedStatementPdfPageResourceType) {
            return;
        }

        /** @var AnnotatedStatementPdfPage $annotatedStatementPdfPage */
        $annotatedStatementPdfPage = $event->getResourceChange()->getTargetResource();
        $annotatedStatementPdf = $annotatedStatementPdfPage->getAnnotatedStatementPdf();
        if (AnnotatedStatementPdf::BOX_REVIEW !== $annotatedStatementPdf->getStatus()
            || null == $annotatedStatementPdf->getReviewer()
        ) {
            $this->annotatedStatementPdfHandler->setBoxReviewStatus($annotatedStatementPdf);
        }
        if ($annotatedStatementPdf->getReviewer()->getId() !== $this->currentUser->getUser()->getId()) {
            throw new ConcurrentEditionException();
        }
        if ($annotatedStatementPdf->allPagesReviewed()) {
            $annotatedStatementPdf->setReviewedDate(new DateTime());
            $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::REVIEWED);
            $annotatedStatementPdf->setReviewer(null);
            $this->managerRegistry->getManager()->flush();

            $this->piTextRecognitionRequester->request($annotatedStatementPdf);
        }
    }

    public function checkAnnotatedStatementPdfUsed(CheckFileIsUsed $event)
    {
        $class = AnnotatedStatementPdf::class;
        $field = 'file';
        $fileId = $event->fileId;
        /** @var EntityRepository $repos */
        $repos = $this->managerRegistry->getRepository($class);
        $result = $repos->createQueryBuilder('e')
            ->select('IDENTITY(e.'.$field.')')
            ->where('IDENTITY(e.'.$field.') = :fileId')
            ->setParameter(':fileId', $fileId)
            ->getQuery()
            ->getResult();
    }
}
