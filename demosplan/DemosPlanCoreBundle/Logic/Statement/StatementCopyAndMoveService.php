<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\StatementVoteRepository;
use Doctrine\ORM\EntityNotFoundException;

class StatementCopyAndMoveService extends CoreService
{
    /** @var ReportService */
    protected $reportService;

    /** @var StatementService */
    private $statementService;
    /**
     * @var StatementVoteRepository
     */
    private $statementVoteRepository;

    public function __construct(
        ReportService $reportService,
        StatementService $statementService,
        StatementVoteRepository $statementVoteRepository
    ) {
        $this->reportService = $reportService;
        $this->statementService = $statementService;
        $this->statementVoteRepository = $statementVoteRepository;
    }

    /**
     * @improve this is the last method remaining in this service and therefore the last reason for this service to exist.
     * In order to get rid of this service, this method can be split into two: one for moving a statement and one for copying a statement.
     *
     * To be compatible with move and copy of statements,
     * the $statementToMove, have to be set the values of the source statement at this point.
     *
     * @param Statement $sourceStatement
     *                                   in case of statementToMove is a copy of a statement,
     *                                   (like in process of copy statement and then move it),
     *                                   $sourceStatement stores the information about is publication allowed.
     *                                   In case of getting this flag from the incmoing $statementToMove, there may be not the value of the source statement.
     *
     * @throws EntityNotFoundException
     */
    public function handlePublicationOfStatement(Statement $statementToMove, Procedure $targetProcedure, Statement $sourceStatement): Statement
    {
        // improve: because of T15936 this logic can be significantly simplified
        // previously a bug was caused here: T12744
        if (false === $targetProcedure->getPublicParticipationPublicationEnabled()) {
            $statementToMove = $this->statementService->setPublicVerified(
                $statementToMove,
                Statement::PUBLICATION_PENDING
            );

            // Votes ("Mitzeichner"):
            foreach ($statementToMove->getVotes() as $vote) {
                $this->statementVoteRepository->delete($vote);
            }
            $statementToMove->setVotes([]);
            $statementToMove->setNumberOfAnonymVotes(0);
        } elseif ($sourceStatement->getPublicAllowed() || $sourceStatement->isManual()) {
            $statementToMove = $this->statementService->setPublicVerified(
                $statementToMove,
                Statement::PUBLICATION_PENDING
            );
        } else {
            // detach only!?:
            $statementToMove->setNumberOfAnonymVotes(0);
            foreach ($statementToMove->getVotes() as $vote) {
                $this->statementVoteRepository->delete($vote);
            }
            $statementToMove->setVotes([]);
        }

        return $statementToMove;
    }
}
