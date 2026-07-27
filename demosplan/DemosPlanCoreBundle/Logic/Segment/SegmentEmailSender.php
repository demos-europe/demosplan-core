<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PrepareReportFromProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Doctrine\DBAL\Exception;

class SegmentEmailSender
{
    public function __construct(
    }

    public function sendSegmentMail(): bool
    {

    }

    private function determineRecipientEmailAddressInstitution($segment, $user): array
    {
        // Regular submitted statement (ToeB)
        if ('' === $segment->getUId()) {
            throw new InvalidArgumentException('UserId must be set');
        }

        if (!$user->hasAnyOfRoles([Role::GUEST, Role::CITIZEN])) {
            // Detect participation email addresses of the orga that the user belongs to
            // when the user is not a guest or a citizen

            return $this->detectRecipientParticipationEmailAddresses($user);
        }

        return [];
    }

    private function determineRecipientEmailAddressInstitutionCoordinator($statement, $user): string
    {
        // Detect email address of the submitting institution coordinator, if not identical to the submitter
        if (null !== $statement->getMeta()->getSubmitUId()) {
            $submitUser = $this->userService->getSingleUser($statement->getMeta()->getSubmitUId());

            if (false === stripos((string) $user->getEmail(), $submitUser->getEmail())) {
                return $submitUser->getEmail();
            }
        }

        return '';
    }

    private function detectRecipientParticipationEmailAddresses($user): array
    {
        $recipients = [];
        /** @var User $user */

        // Participation email address is found on Statement details view > Grundeinstellungen > Intern section > E-Mail Verfahrensträger
        if ('' !== (string) $user->getOrga()->getParticipationEmail()) {
            $recipients[] = $user->getOrga()->getParticipationEmail();
        }

        // CcEmail2 addresses are found on Statement details view > Grundeinstellungen > Intern section > Weitere Empfänger*innen
        if (null !== $user->getOrga()->getCcEmail2()) {
            $ccUsersEmail = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', $user->getOrga()->getCcEmail2());
            $recipients = array_merge($recipients, $ccUsersEmail);
        }

        return $recipients;
    }



    private function detectCCEmailAddresses($sendEmailCC): array
    {
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
    private function extractAndValidateCcEmails($sendEmailCC): array
    {
        $syntaxEmailErrors = [];
        $emailcc = [];
        // Split string into individual email addresses
        $mailsCC = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', (string) $sendEmailCC);
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
        if ([] !== $syntaxEmailErrors) {
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
    public function sendAbschnitt($to, $from, $emailcc, $vars, array $attachments): void
    {
        $this->mailService->sendMail(
            'dm_abschnitt_versand',
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

}
