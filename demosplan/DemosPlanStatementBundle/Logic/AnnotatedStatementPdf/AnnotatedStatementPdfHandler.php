<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanStatementBundle\Exception\InvalidStatusTransitionException;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use demosplan\DemosPlanUserBundle\Logic\UserHandler;
use demosplan\plugins\workflow\SegmentsManager\Entity\Segment;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Log\LoggerInterface;

class AnnotatedStatementPdfHandler
{
    /**
     * @var AnnotatedStatementPdfService
     */
    private $annotatedStatementPdfService;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var UserHandler
     */
    private $userHandler;

    /**
     * @var int
     */
    private $piMaxWaitingSeconds;

    /**
     * @var MessageBag
     */
    private $messageBag;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        AnnotatedStatementPdfService $annotatedStatementPdfService,
        CurrentUserInterface $currentUser,
        LoggerInterface $logger,
        UserHandler $userHandler,
        MessageBag $messageBag,
        int $piMaxWaitingSeconds
    ) {
        $this->annotatedStatementPdfService = $annotatedStatementPdfService;
        $this->currentUser = $currentUser;
        $this->logger = $logger;
        $this->userHandler = $userHandler;
        $this->piMaxWaitingSeconds = $piMaxWaitingSeconds;
        $this->messageBag = $messageBag;
    }

    public function findById(string $id): ?AnnotatedStatementPdf
    {
        return $this->annotatedStatementPdfService->findById($id);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function findOneById(string $id): AnnotatedStatementPdf
    {
        $annotatedStatementPdf = $this->findById($id);
        if (null === $annotatedStatementPdf) {
            $this->logger->error("No AnnotatedStatementPdf found with id: $id");
            throw new InvalidArgumentException("No AnnotatedStatementPdf found with id: $id");
        }

        return $annotatedStatementPdf;
    }

    public function delete(string $entityId): bool
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    public function deleteObject(Segment $segment): void
    {
        throw new NotYetImplementedException('Method not yet implemented.');
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
        return $this
            ->annotatedStatementPdfService
            ->updateObjects($annotatedStatementPdfs);
    }

    public function findByStatement(Statement $statement): ?AnnotatedStatementPdf
    {
        return $this
            ->annotatedStatementPdfService
            ->findByOriginalStatementId(
                $statement->getOriginalId()
            );
    }

    /**
     * @throws InvalidStatusTransitionException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotFoundException
     * @throws Exception
     */
    public function pauseBoxReviewStatus(AnnotatedStatementPdf $annotatedStatementPdf): void
    {
        if (AnnotatedStatementPdf::BOX_REVIEW !== $annotatedStatementPdf->getStatus()) {
            throw InvalidStatusTransitionException::create($annotatedStatementPdf->getStatus(), AnnotatedStatementPdf::READY_TO_REVIEW);
        }
        $this->updateReviewStatus(
            $annotatedStatementPdf,
            AnnotatedStatementPdf::READY_TO_REVIEW
        );
    }

    /**
     * @throws InvalidStatusTransitionException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotFoundException
     * @throws Exception
     */
    public function pauseTextReviewStatus(AnnotatedStatementPdf $annotatedStatementPdf): void
    {
        if (AnnotatedStatementPdf::TEXT_REVIEW !== $annotatedStatementPdf->getStatus()) {
            throw InvalidStatusTransitionException::create($annotatedStatementPdf->getStatus(), AnnotatedStatementPdf::READY_TO_CONVERT);
        }
        $this->updateReviewStatus(
            $annotatedStatementPdf,
            AnnotatedStatementPdf::READY_TO_CONVERT
        );
    }

    /**
     * @throws InvalidStatusTransitionException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotFoundException
     * @throws Exception
     */
    public function setBoxReviewStatus(AnnotatedStatementPdf $annotatedStatementPdf): void
    {
        if (AnnotatedStatementPdf::READY_TO_REVIEW !== $annotatedStatementPdf->getStatus()) {
            throw InvalidStatusTransitionException::create($annotatedStatementPdf->getStatus(), AnnotatedStatementPdf::BOX_REVIEW);
        }
        $this->updateReviewStatus(
            $annotatedStatementPdf,
            AnnotatedStatementPdf::BOX_REVIEW,
            $this->userHandler->getSingleUser($this->currentUser->getUser()->getId())
        );
    }

    /**
     * @throws InvalidStatusTransitionException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotFoundException
     * @throws Exception
     */
    public function setTextReviewStatus(AnnotatedStatementPdf $annotatedStatementPdf): void
    {
        if (AnnotatedStatementPdf::READY_TO_CONVERT !== $annotatedStatementPdf->getStatus()) {
            throw InvalidStatusTransitionException::create($annotatedStatementPdf->getStatus(), AnnotatedStatementPdf::TEXT_REVIEW);
        }
        $this->updateReviewStatus(
            $annotatedStatementPdf,
            AnnotatedStatementPdf::TEXT_REVIEW,
            $this->userHandler->getSingleUser($this->currentUser->getUser()->getId())
        );
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotFoundException
     */
    private function updateReviewStatus(
        AnnotatedStatementPdf $annotatedStatementPdf,
        string $status,
        ?User $user = null
    ): void {
        $userInSession = $this->currentUser->getUser();
        if (!$this->currentUser->hasPermission('feature_import_statement_pdf')) {
            $this->logger->error('The user '.$userInSession->getId().' has no permission to edit AnnotatedStatementPdfs');
            throw new AccessDeniedException();
        }

        $annotatedStatementPdf->setStatus($status);
        $annotatedStatementPdf->setReviewer($user);
        $this->updateObjects([$annotatedStatementPdf]);
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
        return $this->annotatedStatementPdfService->rollbackBoxReviewStatus();
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
        return $this->annotatedStatementPdfService->rollbackTextReviewStatus();
    }

    /**
     * @return AnnotatedStatementPdf[]
     */
    public function findAll(): array
    {
        return $this->annotatedStatementPdfService->findAll();
    }

    /**
     * @return array<int, AnnotatedStatementPdf>
     */
    public function findByStatus(string $status): array
    {
        return $this->annotatedStatementPdfService->findByStatus($status);
    }

    /**
     * @throws MessageBagException
     * @throws UserNotFoundException
     */
    public function validateBoxReview(AnnotatedStatementPdf $annotatedStatementPdf): bool
    {
        switch ($annotatedStatementPdf->getStatus()) {
            case AnnotatedStatementPdf::PENDING:
                $this->messageBag->add(
                    'error',
                    'error.annotated.statement.not.ready.to.review'
                );

                return false;
            case AnnotatedStatementPdf::BOX_REVIEW:
                if (null !== $annotatedStatementPdf->getReviewer()
                    && $this->currentUser->getUser()->getId() === $annotatedStatementPdf->getReviewer()->getId()) {
                    return true;
                }
                $this->messageBag->add(
                    'error',
                    'error.annotated.statement.boxes.already.being.reviewed',
                    ['%user%' => $annotatedStatementPdf->getReviewer()->getName()]
                );

                return false;
            case AnnotatedStatementPdf::READY_TO_REVIEW:
                if ($annotatedStatementPdf->allPagesReviewed()) {
                    $this->logger->error(
                        'Document with id '.$annotatedStatementPdf->getId().
                        ' is set as ready_to_review but has no pages. Please check the'.
                        ' inconsistency in the DB.');
                    $this->messageBag->add(
                            'error', 'error.annotated.statement.already.reviewed'
                        );

                    return false;
                }

                return true;
            default:
                $this->messageBag->add(
                    'error',
                    'error.annotated.statement.already.reviewed'
                );

                return false;
        }
    }
}
