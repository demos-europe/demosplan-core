<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\SendMailException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedureReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanProcedureBundle\ValueObject\PreparationMailVO;
use Doctrine\ORM\NoResultException;
use Exception;

class SubmitterService extends CoreService
{
    /**
     * @var MailService
     */
    protected $mailService;
    /**
     * @var ReportService
     */
    private $reportService;

    /**
     * @var ProcedureReportEntryFactory
     */
    private $procedureReportEntryFactory;
    /**
     * @var ProcedureRepository
     */
    private $procedureRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(
        MailService $mailService,
        ProcedureReportEntryFactory $procedureReportEntryFactory,
        ProcedureRepository $procedureRepository,
        ReportService $reportService,
        UserRepository $userRepository
    ) {
        $this->mailService = $mailService;
        $this->procedureReportEntryFactory = $procedureReportEntryFactory;
        $this->procedureRepository = $procedureRepository;
        $this->reportService = $reportService;
        $this->userRepository = $userRepository;
    }

    /**
     * @param User              $fromUser
     * @param PreparationMailVO $preparationMail
     * @param string            $procedureId
     *
     * @throws Exception
     */
    public function sendPreparationMailToStatementSubmittersFromUserId(
        $fromUser,
        $preparationMail,
        $procedureId
    ) {
        $userMailAddress = $fromUser->getEmail();
        $subject = $preparationMail->getMailSubject();
        $body = $preparationMail->getMailBody();
        $sendMailVars = [
            'mailsubject' => $subject,
            'mailbody'    => $body,
        ];
        $statementMailAddresses = $this->procedureRepository->getStatementMailAddressesForProcedure($procedureId);
        $this->mailService->sendMails(
            'dm_stellungnahme',
            'de_DE',
            $statementMailAddresses,
            $userMailAddress,
            '',
            '',
            'extern',
            $sendMailVars
        );

        // create report entry
        // what we're sending is not exactly a 'finalMail', but close enough
        $reportEntry = $this->procedureReportEntryFactory->createFinalMailEntry(
            $procedureId,
            $fromUser,
            $preparationMail,
            $statementMailAddresses
        );
        $this->reportService->persistAndFlushReportEntries($reportEntry);
    }

    /**
     * @param string            $userId
     * @param PreparationMailVO $preparationMail
     *
     * @throws SendMailException
     * @throws Exception
     */
    public function sendPreparationMailToUserId($userId, $preparationMail)
    {
        try {
            $user = $this->userRepository->get($userId);
        } catch (NoResultException $e) {
            $user = null;
        }
        $userMailAddress = $user->getEmail();
        $sendMailVars = [
            'mailbody'    => $preparationMail->getMailBody(),
            'mailsubject' => $preparationMail->getMailSubject(),
        ];
        $this->mailService->sendMail(
            'dm_stellungnahme',
            'de_DE',
            $userMailAddress,
            $userMailAddress,
            '',
            '',
            'extern',
            $sendMailVars
        );
    }

    /**
     * @param string $procedureId
     *
     * @return int
     */
    public function getStatementMailAddressesCountForProcedure($procedureId)
    {
        return $this->procedureRepository->getStatementMailAddressesCountForProcedure($procedureId);
    }
}
