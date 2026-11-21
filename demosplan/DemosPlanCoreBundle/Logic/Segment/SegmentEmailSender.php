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

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use Doctrine\DBAL\Exception;

class SegmentEmailSender
{
    /**
     * Upper bound on CC recipients per mail to limit spam / accidental mass-mailing.
     */
    private const MAX_CC_RECIPIENTS = 20;

    public function __construct(
        private readonly MailService $mailService,
        private readonly MessageBagInterface $messageBag,
        private readonly SegmentService $segmentService,
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly EditorService $editorService,
    ) {
    }

    /**
     * Send a segment or several segments to an external mail recipient, optionally with cc addresses.
     *
     * @param string[] $segmentIds
     *
     * @return bool whether the mail was queued successfully
     */
    public function sendSegmentsMail(
        array $segmentIds,
        string $procedureId,
        string $recipientEmail,
        SegmentMailContent $content,
        ?string $sendEmailCC,
        ?string $replyTo,
    ): bool {
        try {
            $segmentIds = array_values(array_unique($segmentIds));
            $segments = $this->segmentService->findByIds($segmentIds);
            if ([] === $segments || count($segments) !== count($segmentIds)) {
                throw new InvalidDataException('Not all segments were found for the given IDs.');
            }
            // ensure every segment belongs to the current procedure
            foreach ($segments as $segment) {
                if ($segment->getProcedure()->getId() !== $procedureId) {
                    throw new InvalidDataException('Segment does not belong to current procedure.');
                }
            }
            // validate the recipients email address
            $sendMailTo = $this->validateRecipientEmail($recipientEmail);
            $ccEmailAddresses = $this->extractCcEmailAddresses($sendEmailCC);
            // the sender address is the procedures agency mailbox
            $sentFrom = $segments[0]->getProcedure()->getAgencyMainEmailAddress();
            // handle anonymized text before building email variables
            $obscuredBody = null === $content->body ? null : $this->editorService->obscureString($content->body);
            $emailVariables = $this->populateEmailVariables($content->subject, $obscuredBody);
            // Queue the mail and write the version-history audit entries together. The caller
            // (RpcSegmentEmailSender::execute) wraps this in a transaction so a queued mail can
            // never end up without its GDPR audit entry. No attachments as of now.
            $this->sendSegment($sendMailTo, $sentFrom, $ccEmailAddresses, $emailVariables, [], $replyTo);
            // The separate parts allow labelling them in the version history. Without them the whole body is logged as the message.
            $hasParts = null !== $content->messageText;
            $loggedMessage = $this->toLogText($hasParts ? $content->messageText : $content->body);
            $loggedSegmentText = $hasParts ? $this->toLogText($content->segmentText) : null;
            $loggedRecommendation = $hasParts ? $this->toLogText($content->recommendationText) : null;
            foreach ($segments as $segment) {
                $this->entityContentChangeService->createSegmentSentByMailChangeEntry(
                    $segment,
                    $sendMailTo,
                    $loggedMessage,
                    new DateTime(),
                    $loggedSegmentText,
                    $loggedRecommendation,
                );
            }
        } catch (InvalidDataException) {
            $this->messageBag->add('error', 'error.segment.queued');

            return false;
        }
        $this->messageBag->add('confirm', 'confirm.segment.queued');

        return true;
    }

    /**
     * Obscures the text like the mail body and flattens it to a single line for the version history.
     */
    private function toLogText(?string $text): string
    {
        if (null === $text || '' === $text) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($this->editorService->obscureString($text))));
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
        $mailsCC = preg_split('/ *[;,] */', $sendEmailCC);
        if (count($mailsCC) > self::MAX_CC_RECIPIENTS) {
            throw new InvalidDataException('Too many CC recipients provided.');
        }

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
     * @param string|array         $sendMailTo
     * @param string|array         $sentFrom
     * @param string|array         $emailCC
     * @param array                $vars
     * @param array<string,string> $attachments
     *
     * @throws Exception
     */
    private function sendSegment($sendMailTo, $sentFrom, $emailCC, $vars, array $attachments, $replyTo): void
    {
        $this->mailService->sendMail(
            'dm_schlussmitteilung',
            'de_DE',
            $sendMailTo,
            $sentFrom,
            $emailCC,
            '',
            'extern',
            $vars,
            $attachments,
            $replyTo,
        );
    }
}
