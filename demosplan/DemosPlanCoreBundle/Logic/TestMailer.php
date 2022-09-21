<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class TestMailer implements MailerInterface
{
    /**
     * @var string
     */
    protected $emailSystem;

    /**
     * @var bool
     */
    protected $emailIsLiveSystem;

    /**
     * @var string
     */
    protected $emailTestFrom;

    /**
     * @var string
     */
    protected $emailTestTo;

    /**
     * @var MailerInterface
     */
    private $mailer;

    public function __construct(GlobalConfigInterface $config, MailerInterface $mailer)
    {
        $this->emailIsLiveSystem = $config->isEmailIsLiveSystem();
        $this->emailSystem = $config->getEmailSystem();
        $this->emailTestFrom = $config->getEmailTestFrom();
        $this->emailTestTo = $config->getEmailTestTo();
        $this->mailer = $mailer;
    }

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        $message = $this->adjustForTestingEnvironment($message);
        $this->mailer->send($message);
    }

    private function adjustForTestingEnvironment(RawMessage $message): RawMessage
    {
        // build testmailbody if necessary
        if (false === $this->emailIsLiveSystem) {
            $fromOrig = $this->getStringFromAddresses($message->getFrom());
            $replyToOrigString = $this->getStringFromAddresses($message->getReplyTo());
            $toOrigString = $this->getStringFromAddresses($message->getTo());
            $ccOrigString = $this->getStringFromAddresses($message->getCc());
            $bccOrigString = $this->getStringFromAddresses($message->getBcc());

            $testMailbody = <<<EOT
MailService Test aktiviert.
Alle ausgehenden Nachrichten werden umgeleitet.
-----------------------------------------------
Empfänger: $toOrigString
Absender: $fromOrig
ReplyTo: $replyToOrigString
CC: $ccOrigString (Für Tests werden keine separaten CC-Mails versendet)
BCC: $bccOrigString
------------------------------------------------
Nachricht:
------------------------------------------------

EOT;

            $testMessage = (new Email())
                ->from($this->emailTestFrom)
                ->to($this->emailTestTo)
                ->subject($message->getSubject())
                ->text($testMailbody.$message->getTextBody())
                ->html(nl2br($testMailbody).$message->getHtmlBody());

            foreach ($message->getAttachments() as $attachment) {
                $testMessage->attachPart($attachment);
            }
            // overwrite Original Object with Testmail
            $message = $testMessage;
        }

        return $message;
    }

    /**
     * @param Address[] $address
     */
    private function getStringFromAddresses(array $address): string
    {
        return implode(',', collect($address)->transform(static function (Address $address) {
            return $address->toString();
        })->toArray());
    }
}
