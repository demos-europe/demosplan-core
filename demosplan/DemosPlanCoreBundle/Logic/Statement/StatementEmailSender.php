<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PrepareReportFromProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Doctrine\DBAL\Exception;

class StatementEmailSender extends CoreService
{
    public function __construct(
        protected AssignService                            $assignService,
        protected PermissionsInterface                     $permissions,
        protected StatementFragmentService                 $statementFragmentService,
        protected ConsultationTokenService                 $consultationTokenService,
        protected StatementAttachmentService               $statementAttachmentService,
        protected CurrentProcedureService                  $currentProcedureService,
        protected StatementService                         $statementService,
        private readonly PrepareReportFromProcedureService $prepareReportFromProcedureService,
        private readonly UserService                       $userService,
        private readonly MessageBagInterface               $messageBag, private readonly MailService $mailService, private readonly FileService $fileService, private readonly CurrentUserService $currentUserService,
    ) {
    }

    public function sendStatementMail($statementId, $subject, $body, $sendEmailCC, $emailAttachments): bool
    {
        try {
            $statement = $this->statementService->getStatement($statementId);
            if (null === $statement) {
                $this->messageBag->add('error', 'error.statement.final.send');
                return false;
            }

            $emailVariables = $this->populateEmailVariables($subject, $body);
            $ccEmailAddresses = $this->detectCCEmailAddresses($sendEmailCC);
            $successMessageTranslationParams = [];


            $attachments = array_map($this->createSendableAttachment(...), $emailAttachments);
            $attachmentNames = array_column($attachments, 'name');


           if (Statement::EXTERNAL === $statement->getPublicStatement()) {
               if ('email' === $statement->getFeedback()) {
                   $successMessageTranslationParams['sent_to'] = 'citizen_only';
                   $this->sendFinalStatementEmail(
                       $statement,
                       $subject,
                       $ccEmailAddresses,
                       $emailVariables,
                       $attachments,
                       $attachmentNames,
                       $statement->getMeta()->getOrgaEmail(),
                   );
                   // If the mail is sent once in CC, it doesn't need to be sent in CC again later.
                   $ccEmailAddresses = [];
               }
           } elseif ('' != $statement->getMeta()->getOrgaEmail()) {
                   $successMessageTranslationParams['sent_to'] = 'institution_only';
                   $this->sendFinalStatementEmail(
                    $statement,
                    $subject,
                    $ccEmailAddresses,
                    $emailVariables,
                    $attachments,
                    $attachmentNames,
                    $statement->getMeta()->getOrgaEmail(),
                );
                // If the mail is sent once in CC, it doesn't need to be sent in CC again later.
                $ccEmailAddresses = [];
            } else {
                /** @var User $user */
                $user = $this->userService->getSingleUser($statement->getUId());
                $recipientEmailAddress = $this->determineRecipientEmailAddressInstitution($statement,$user);
                if (!empty($recipientEmailAddress)) {
                    $successMessageTranslationParams['sent_to'] = 'institution_only';
                    $this->sendFinalStatementEmail(
                        $statement,
                        $subject,
                        $ccEmailAddresses,
                        $emailVariables,
                        $attachments,
                        $attachmentNames,
                        $recipientEmailAddress
                    );
                    // If the mail is sent once in CC, it doesn't need to be sent in CC again later.
                    //If we dont do this, it will spam the $ccEmailAddresses when sending email to voters will happen
                    $ccEmailAddresses = [];
                }

                $recipientEmailAddress = $this->determineRecipientEmailAddressInstitutionCoordinator($statement, $user);

                if (!empty($recipientEmailAddress)) {
                    $successMessageTranslationParams['sent_to'] = 'institution_and_coordination';
                    $this->sendFinalStatementEmail(
                        $statement,
                        $subject,
                        '',
                        $emailVariables,
                        $attachments,
                        $attachmentNames,
                        $recipientEmailAddress
                    );
                }
            }


            if (!$statement->getVotes()->isEmpty()) {
                $this->sendEmailToVoters($statement, $subject, $ccEmailAddresses, $emailVariables, $attachments, $attachmentNames);
                $successMessageTranslationParams['voters_count'] = count($statement->getVotes());
                if (Statement::EXTERNAL === $statement->getPublicStatement() && 'email' === $statement->getFeedback()) {
                    $successMessageTranslationParams['sent_to'] = 'citizen_and_voters';
                } else {
                    $successMessageTranslationParams['sent_to'] = 'voters_only';
                }
            }

        }
        catch (InvalidDataException) {
            $this->messageBag->add('error', 'error.statement.final.send.syntax.email.cc');
            return false;
        }

        catch (InvalidArgumentException) {
            $this->messageBag->add('error', 'error.statement.final.send.noemail');
            return false;
        }


        $this->messageBag->add('confirm', 'confirm.statement.final.sent', $successMessageTranslationParams);
        $this->messageBag->add('confirm', 'confirm.statement.final.sent.emailCC');
        return true;
    }

    private function determineRecipientEmailAddressInstitution($statement,$user): array
    {
        // Regular submitted statement (ToeB)
        if ('' === $statement->getUId()) {
            throw new InvalidArgumentException('UserId must be set');
        }

        if (!$user->hasAnyOfRoles([Role::GUEST, Role::CITIZEN])) {
            // Detect participation email addresses of the orga that the user belongs to
            // when the user is not a guest or a citizen

            return $this->detectRecipientParticipationEmailAddresses($user);
        }

        return [];

    }

    private function determineRecipientEmailAddressInstitutionCoordinator ($statement, $user): string {

        // Detect email address of the submitting institution coordinator, if not identical to the submitter
        if (null !== $statement->getMeta()->getSubmitUId()) {
            $submitUser = $this->userService->getSingleUser($statement->getMeta()->getSubmitUId());

            if (false === stripos($user->getEmail(), $submitUser->getEmail())) {
                return $submitUser->getEmail();
            }
        }

        return '';
    }

    private function detectRecipientParticipationEmailAddresses($user): array {
        $recipients = [];
        /** @var User $user */

        //Participation email address is found on Statement details view > Grundeinstellungen > Intern section > E-Mail Verfahrensträger
        if (0 < strlen($user->getOrga()->getParticipationEmail())) {
            $recipients[] = $user->getOrga()->getParticipationEmail();
        }

        //CcEmail2 addresses are found on Statement details view > Grundeinstellungen > Intern section > Weitere Empfänger*innen
        if (null !== $user->getOrga()->getCcEmail2()) {
            $ccUsersEmail = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', $user->getOrga()->getCcEmail2());
            $recipients = array_merge($recipients, $ccUsersEmail);
        }
        return $recipients;
    }

    private function sendEmailToVoters($statement, $subject, $emailcc, $vars, $attachments, $attachmentNames): void {
        /** @var StatementVote $vote */
        foreach ($statement->getVotes() as $vote) {
            $voteEmailAddress = $vote->getUserMail();
            if (null !== $voteEmailAddress) {
                $this->sendFinalStatementEmail(
                    $statement,
                    $subject,
                    $emailcc,
                    $vars,
                    $attachments,
                    $attachmentNames,
                    $voteEmailAddress
                );

            }
        }
    }

    private function sendFinalStatementEmail ($statement, $subject, $emailcc, $vars, $attachments, $attachmentNames, $recipientEmailAddress): void {

        $procedure = $this->currentProcedureService->getProcedureWithCertainty();
        $from = $procedure->getAgencyMainEmailAddress();

        $this->sendDmSchlussmitteilung(
            $recipientEmailAddress,
            $from,
            $emailcc,
            $vars,
            $attachments
        );

        // Save when the final notice was sent
        $this->statementService->setSentAssessment($statement->getId());

        if (is_array($recipientEmailAddress)) {
            foreach ($recipientEmailAddress as $email) {
                $this->prepareReportFromProcedureService->addReportFinalMail(
                    $statement,
                    $subject ?? '',
                    $attachmentNames
                );
            }
        } else {
            $this->prepareReportFromProcedureService->addReportFinalMail(
                $statement,
                $subject ?? '',
                $attachmentNames
            );
        }

    }

    private function detectCCEmailAddresses($sendEmailCC): array {
        $ccEmailAddresses = [];

        if ($this->permissions->hasPermission('feature_send_final_email_cc_to_self')) {
            $ccEmailAddresses[] = $this->currentUserService->getUser()->getEmail();
        }

        // Check if emails are entered in the CC field

        if (!empty($sendEmailCC) && 0 !== strlen((string) $sendEmailCC)) {
            $ccEmailAddresses = array_merge($ccEmailAddresses, $this->extractAndValidateCcEmails($sendEmailCC));
        }

        return $ccEmailAddresses;
    }

    private function populateEmailVariables($subject, $body): array
    {
        $emailVariables = [];
        if (!empty($body)) {
            $emailVariables['mailbody'] = $body;
        }

        if (!empty($subject)) {
            $emailVariables['mailsubject'] = $subject;
        }

        return $emailVariables;
    }

    /**
     * @throws InvalidDataException
     */
    private function extractAndValidateCcEmails($sendEmailCC): array {
        $syntaxEmailErrors = [];
        $emailcc = [];
        // Split string into individual email addresses
        $mailsCC = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', $sendEmailCC);
        // Check each email address for validity
        foreach ($mailsCC as $mail) {
            // Remove all whitespace at the beginning and end
            $mailForCc = trim((string) $mail);
            // Check if the email address is correct
            if (filter_var($mailForCc, FILTER_VALIDATE_EMAIL)) {
                // if yes, add it to the array
                $emailcc[] = $mailForCc;
            } else {
                // if not, added to error message array
                $syntaxEmailErrors[] = $mailForCc;
            }
        }

        // if email addresses are incorrect, generate an error message
        if (0 < count($syntaxEmailErrors)) {
            throw new InvalidDataException('Invalid Emails provided in CC field.');
        }

        return $emailcc;
    }

    /**
     * @param string|array         $to
     * @param string|array         $from
     * @param string|array         $emailcc
     * @param array                $vars
     * @param array<string,string> $attachments
     *
     * @throws Exception
     */
    public function sendDmSchlussmitteilung($to, $from, $emailcc, $vars, array $attachments): void
    {
        $this->mailService->sendMail(
            'dm_schlussmitteilung',
            'de_DE',
            $to,
            $from,
            $emailcc,
            '',
            'extern',
            $vars,
            $attachments
        );
    }

    /**
     * @return array<string,string> An array consisting of two keys: `name` and `content`. The
     *                              former contains the name of the file. The latter contains the
     *                              file content loaded from the file system. This format is needed
     *                              by {@link MailService::sendMail}.
     */
    public function createSendableAttachment(string $fileString): array
    {
        $file = $this->fileService->getFileFromFileString($fileString);
        if (null === $file) {
            throw new InvalidArgumentException("File not found for ID: $fileString");
        }

        return [
            'name'    => $file->getFilename(),
            'content' => $this->fileService->getContent($file),
        ];
    }
}
