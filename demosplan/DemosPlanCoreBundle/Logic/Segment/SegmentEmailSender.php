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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use Doctrine\DBAL\Exception;

class SegmentEmailSender
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly MessageBagInterface $messageBag,
        private readonly SegmentService $segmentService,
    ) {
    }

    /**
     * Send a segment to an external mail recipient, optionally with cc addresses.
     *
     * @return bool whether the mail was queued successfully
     */
    public function sendSegmentMail(
        string $segmentId,
        string $recipientEmail,
        ?string $subject,
        ?string $body,
        ?string $sendEmailCC,
    ): bool {
        try {
            // load the segment. prove it exists and give us the procedure it belongs to
            $segment = $this->segmentService->findByIdWithCertainty($segmentId);
            // validate the recipients email address
            $sendMailTo = $this->validateRecipientEmail($recipientEmail);
            $ccEmailAddresses = $this->extractCcEmailAddresses($sendEmailCC);
            // build the placeholder values the mail template expects:
            $emailVariables = $this->populateEmailVariables($subject, $body);
            // the sender address is the procedures agency mailbox
            $sentFrom = $segment->getProcedure()->getAgencyMainEmailAddress();
            // Queue the mail, no attachments as of now
            $this->sendAbschnitt($sendMailTo, $ccEmailAddresses, $emailVariables, []);
        } catch (InvalidDataException) {
            $this->messageBag->add('error', 'error.segment.send.syntax.email');
        }

        return false;
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
