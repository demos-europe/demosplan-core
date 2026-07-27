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
            $this->sendAbschnitt($sendMailTo, $sentFrom, $ccEmailAddresses, $emailVariables, []);
        } catch (InvalidDataException) {
            $this->messageBag->add('error', 'error.segment.send.syntax.email');

            return false;
        }
        $this->messageBag->add('confirm', 'confirm.segment.sent');

        return true;
    }

    /**
     * @throws InvalidDataException if the address is not a valid email
     */
    private function validateRecipientEmail(string $recipientEmail): string
    {
        $recipient = trim($recipientEmail);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidDataException('Invalid recipient email address provided.');
        }

        return $recipient;
    }

    /**
     * splits the cc field on comma/semicolon and validates every address.
     *
     * @throws InvalidDataException if any address is invalid
     */
    private function extractCcEmailAddresses(?string $sendEmailCC): array
    {
        if (null === $sendEmailCC || '' === trim($sendEmailCC)) {
            return [];
        }
        $syntaxEmailErrors = [];
        $emailCC = [];
        // split the string into individual email addresses.
        $mailsCC = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', $sendEmailCC);

        foreach ($mailsCC as $mail) {
            $mailForCc = trim((string) $mail);
            if (filter_var($mailForCc, FILTER_VALIDATE_EMAIL)) {
                $emailCC[] = $mailForCc;
            } else {
                $syntaxEmailErrors[] = $mailForCc;
            }
        }
        if ([] !== $syntaxEmailErrors) {
            throw new InvalidDataException('Invalid email address provided in CC field.');
        }

        return $emailCC;
    }

    private function populateEmailVariables(?string $subject, ?string $body): array
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
