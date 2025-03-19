<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\MailAttachment;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\SendMailException;
use demosplan\DemosPlanCoreBundle\Repository\MailRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToRetrieveMetadata;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailService extends CoreService
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
    protected $emailSubjectPrefix;

    /**
     * @var MailerInterface
     */
    protected $mailer;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        MailerInterface $mailer,
        private readonly MailRepository $mailRepository,
        private readonly TranslatorInterface $translator,
    ) {
        $this->emailIsLiveSystem = $globalConfig->isEmailIsLiveSystem();
        $this->emailSubjectPrefix = $globalConfig->getEmailSubjectPrefix();
        $this->emailSystem = $globalConfig->getEmailSystem();
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->mailer = $mailer;
    }

    /**
     * Speichere eine Email in der Queue zum Versenden.
     *
     * @param string       $template
     * @param string       $lang
     * @param string|array $to
     * @param string|array $from        this will be used as replyTo on send this mail on sendMailsFromQueue(), because
     *                                  sending mail has to be from systemmail to avoid spam problems with spf
     * @param string|array $cc
     * @param string|array $bcc
     * @param string       $scope
     * @param array        $vars
     * @param array        $attachments
     *
     * Attachments: You can specify attachments in two ways:
     *    Either you supply a simple string that contains a filename.
     *    XOR you supply an array that contains 'name' as filename
     *    and the contents of the attachment in 'content'. You can mix
     *    both types.
     *
     * @throws Exception
     */
    public function sendMail(
        $template,
        $lang,
        $to,
        $from,
        $cc,
        $bcc,
        $scope,
        $vars = [],
        $attachments = [],
    ): MailSend {
        if (!is_string($from) || '' === $from || (is_array($from) && 0 === count($from))) {
            $from = $this->emailSystem;
        }

        $emailTo = $this->checkEMailField($to);
        $emailFrom = $this->checkEMailField($from);
        $emailCc = $this->checkEMailField($cc);
        $emailBcc = $this->checkEMailField($bcc);

        $emailTemplate = $this->mailRepository->getTemplate($template);
        if (null === $emailTemplate) {
            throw new InvalidArgumentException("No template entity found for the given template label: '$template'");
        }
        $emailTitle = $this->mailRepository->replacePlaceholder($emailTemplate->getTitle(), $vars);
        $emailContent = $this->mailRepository->replacePlaceholder($emailTemplate->getContent(), $vars);

        $mail = new MailSend();
        $mail->setContent($emailContent);
        $mail->setTemplate($emailTemplate->getLabel());
        $mail->setTo($emailTo);
        $mail->setCc($emailCc);
        $mail->setBcc($emailBcc);
        $mail->setFrom($emailFrom);
        $mail->setScope($scope);
        $mail->setTitle($this->emailSubjectPrefix.$emailTitle);

        // persist all supplied attachments
        foreach ($attachments as $descriptor) {
            if (!isset($descriptor['content'], $descriptor['name'])) {
                continue;
            }
            $filename = DemosPlanPath::getSystemFilesPath(random_int(1000, 9999).'-'.$descriptor['name']);

            try {
                $this->defaultStorage->write($filename, $descriptor['content']);
            } catch (FilesystemException $e) {
                $this->logger->warning("Attachment $filename could not be created: ", [$e]);
            }

            if (preg_match("/^\w:/", $filename)) {
                $this->logger->warning("Ignoring attachment created on a Windows™ system: $filename");
            }

            $attachment = $this->mailRepository->createAttachment($filename);
            $mail->getAttachments()->add($attachment);
            $attachment->setMailSend($mail);
        }

        // Mark this email als ready to send.
        return $this->mailRepository->addObject($mail);

        // Alle überprüften Methoden kommen mit einer Exception als Rückgabewert klar,
        // wenn ein Fehler aufgetreten ist
    }

    /**
     * Works like sendMail but sends the same mail to all given values in the $to parameter inside a single transaction.
     * <p>
     * The only only difference regarding the parameter list is the $to parameter which must be an array.
     * <p>
     * Returns nothing, if no exception was thrown, everything is assumed to be ok.
     *
     * @param string       $template
     * @param string       $lang
     * @param array        $to          for each value in this array the sendMail function will be called with the respective value
     * @param string|array $from
     * @param string|array $cc
     * @param string|array $bcc
     * @param string       $scope
     * @param array        $vars
     * @param array        $attachments
     *
     * @throws SendMailException
     */
    public function sendMails(
        $template,
        $lang,
        $to,
        $from,
        $cc,
        $bcc,
        $scope,
        $vars = [],
        $attachments = [],
    ) {
        $em = $this->getDoctrine()->getManager();
        $em->getConnection()->beginTransaction();
        try {
            foreach ($to as $receiver) {
                $this->sendMail(
                    $template,
                    $lang,
                    $receiver,
                    $from,
                    $cc,
                    $bcc,
                    $scope,
                    $vars,
                    $attachments
                );
            }
            $em->flush();
            $em->getConnection()->commit();
        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            throw SendMailException::mailListFailed($to, $e);
        }
    }

    /**
     * Get single Mail from Queue.
     *
     * @param string $id
     *
     * @return MailSend|null
     */
    public function getMailFromQueue($id)
    {
        try {
            return $this->mailRepository->findOneBy(['id' => $id]);
        } catch (Exception $e) {
            $this->logger->warning('Get Mail from Queue failed: ', [$e]);

            return null;
        }
    }

    /**
     * Send Emails from Queue
     * many variables unsetted to prevent memoryleaks in long running console commands.
     *
     * @param int $limit
     *
     * @return int|null Number of emails sent
     */
    public function sendMailsFromQueue($limit = 200)
    {
        $em = $this->getDoctrine()->getManager();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);
        $emailsSent = 0;
        try {
            try {
                $mailList = $this->getMailsToSend($limit);
            } catch (Exception $e) {
                $this->logger->warning('Get Maillist from Queue failed: ', [$e]);
                $mailList = null;

                return null;
            }

            /** @var MailSend $mail */
            foreach ($mailList as $mail) {
                // when sender's email address has the same domain as the system
                // email it is safe to send the email from original from address
                // send from system email to avoid spam problems with spf
                $from = $this->emailSystem;

                // in some cases it may be possible to us sender email address
                // when sender email address has same domain (without subdomain) as
                // system email it may be used as from address without spf issues
                if (!$this->globalConfig->isEmailUseSystemMailAsSender()) {
                    $fromInitial = $this->explodeEmailAddresses($mail->getFrom());
                    if (0 === count($fromInitial)) {
                        $fromInitial = [$this->emailSystem];
                    }
                    $isValidFromAddress = preg_match(
                        '/'.$this->globalConfig
                            ->getEmailFromDomainValidRegex().'/',
                        (string) $fromInitial[0]
                    );
                    if (1 === $isValidFromAddress) {
                        $from = $fromInitial[0];
                    }
                }

                // set real sender as reply to
                $replyTo = $this->explodeEmailAddresses($mail->getFrom());
                if (0 === count($replyTo)) {
                    $replyTo = $this->emailSystem;
                }
                $to = $this->explodeEmailAddresses($mail->getTo());
                $cc = $this->explodeEmailAddresses($mail->getCc());
                $bcc = $this->explodeEmailAddresses($mail->getBcc());
                $content = nl2br($mail->getContent());

                $message = (new Email())
                    ->from($from)
                    ->to(...$to)
                    ->replyTo(...$replyTo)
                    ->subject($mail->getTitle())
                    ->text($this->convertHtmlToCustomMarkdown($content))
                    ->html($content);

                // Setze den Returnpath, falls erwünscht
                $message = $this->setReturnPath($message, $mail);

                if (0 < count($cc)) {
                    $message->cc(...$cc);
                }
                if (0 < count($bcc)) {
                    $message->bcc(...$bcc);
                }

                /** @var MailAttachment $attachment */
                foreach ($mail->getAttachments() as $attachment) {
                    // attach file only if it really exists
                    try {
                        if ($this->defaultStorage->fileExists($attachment->getFilename())) {
                            $mimeType = $this->defaultStorage->mimeType($attachment->getFilename());
                            $resource = $this->defaultStorage->readStream($attachment->getFilename());
                            $fileName = basename($attachment->getFilename());
                            $this->logger->info(
                                'extracted name for attachment and its contentType is',
                                ['fileName' => $fileName, 'mimeType' => $mimeType]
                            );
                            $mimeType = '' !== $mimeType ? $mimeType : null;
                            $fileName = '' !== $fileName ? $fileName : null;
                            $message->attach(
                                $resource,
                                $fileName,
                                $mimeType
                            );
                        } else {
                            $this->logger->warning('Tried to add non existing attachment to Email', [$attachment->getFilename()]);
                        }
                    } catch (UnableToCheckExistence $e) {
                        $this->logger->warning(
                            'Failed to check the existence of attachment to Email',
                            ['attachment' => $attachment->getFilename(), 'ExceptionMessage' => $e->getMessage()]
                        );
                    } catch (UnableToRetrieveMetadata $e) {
                        $this->logger->warning(
                            'Failed to retrieve mimeType of attachment to Email',
                            ['attachment' => $attachment->getFilename(), 'ExceptionMessage' => $e->getMessage()]
                        );
                    } catch (Exception $e) {
                        $this->logger->warning(
                            'Failed to append attachment to Email',
                            ['attachment' => $attachment->getFilename(), 'ExceptionMessage' => $e->getMessage()]
                        );
                    }
                }

                // Do not send mails if entity manager is closed because
                // mail send status would not be written which leads to
                // infinite sending of mails
                if (!$em->isOpen()) {
                    $this->logger->error('sendMailsFromQueue failed as entity manager is closed');

                    return null;
                }

                // send mail
                try {
                    $this->mailer->send($message);
                    $mail->setStatus('sent');
                    $mail->setSendDate(new DateTime());
                } catch (TransportExceptionInterface $e) {
                    $this->logger->warning('Could not send Mail',
                        [
                            'title'        => $mail->getTitle(),
                            'to'           => $to,
                            'cc'           => $cc,
                            'bcc'          => $bcc,
                            'from'         => $from,
                            'isLiveSystem' => $this->emailIsLiveSystem,
                            'exception'    => $e,
                        ]
                    );
                    // update number of send attempts
                    $mail->setSendAttempt($mail->getSendAttempt() + 1);
                    $em->persist($mail);

                    continue;
                } catch (Exception $e) {
                    $this->logger->error('General exception on sending e-mail.', [$e]);
                    $mail->setSendAttempt($mail->getSendAttempt() + 1);
                    $em->persist($mail);

                    continue;
                }

                $em->persist($mail);

                $this->logger->info('Mail Sent',
                    [
                        'title'        => $mail->getTitle(),
                        'to'           => $to,
                        'cc'           => $cc,
                        'bcc'          => $bcc,
                        'from'         => $from,
                        'isLiveSystem' => $this->emailIsLiveSystem,
                    ]
                );

                /** @var MailAttachment $attachment */
                foreach ($mail->getAttachments() as $attachment) {
                    try {
                        if ($attachment->getDeleteOnSent()
                            && 'sent' === $mail->getStatus()
                            && $this->defaultStorage->fileExists($attachment->getFilename())
                        ) {
                            $this->defaultStorage->delete($attachment->getFilename());
                        }
                    } catch (Exception $exception) {
                        $this->logger->warning('failed to remove email attachment', [$exception]);
                    }
                }

                ++$emailsSent;
                $message = null;
                $mail = null;
            }

            $em->flush();
            $em->clear();
            $em = null;
            $logger = null;

            return $emailsSent;
        } catch (Exception $e) {
            // flush Entities, if any are processed before Exception has been thrown
            $em->flush();
            $em->clear();
            $this->logger->warning('Get Mail from Queue failed: ', [$e]);

            return null;
        }
    }

    /**
     * Wandle den String aus der Datenbank in ein Array um.
     *
     * @param string $addressString
     *
     * @return array
     */
    protected function explodeEmailAddresses($addressString)
    {
        $return = [];
        $addresses = explode(',', $addressString);

        // check for valid Emailaddresses
        foreach ($addresses as $address) {
            $email = trim($address);
            if ('' === $email) {
                continue;
            }
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            if (false !== filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $return[] = $email;
            } else {
                $this->getLogger()->warning('Deleted invalid Email ', [$address]);
            }
        }

        return $return;
    }

    /**
     * Get Mails from Queue to send.
     *
     * @return array<int, MailSend>
     *
     * @throws Exception
     */
    public function getMailsToSend(int $limit = 200): array
    {
        $conditions = Criteria::create()
            ->where(Criteria::expr()->eq('status', 'new'))
            ->andWhere(Criteria::expr()->lte('sendAttempt', 20))
            ->orderBy(['createdDate' => Criteria::DESC])
            ->setMaxResults($limit);

        return $this->mailRepository->matching($conditions)->toArray();
    }

    /**
     * Verarbeite einkommende Adressfelder.
     *
     * Erlaubte Formate:
     *
     * eine E-Mail Adresse als String
     * mehrere E-Mail Adresse als Strig kommasepariert
     * mehrere E-Mail Adresse als Array mit Einzel E-Mail Adressen
     *
     * @param string|array|Collection $field
     * @param bool                    $addEmailAsName
     */
    private function checkEMailField($field, $addEmailAsName = false): string
    {
        $res = [];

        if ($field instanceof EmailAddress) {
            $field = $field->getFullAddress();
        }

        if ($field instanceof stdClass) {
            $field = Json::decodeToArray(Json::encode($field));
        }

        if (is_array($field) || $field instanceof Collection) {
            foreach ($field as $email) {
                $checkResult = $this->checkEMail($email);
                if (null !== $checkResult) {
                    if ($addEmailAsName) {
                        $checkResult = '"'.$checkResult.'" <'.$checkResult.'>';
                    }
                    $res[] = $checkResult;
                }
            }
        } else {
            if (empty($field)) {
                return '';
            }

            if (str_contains((string) $field, ',')) {
                return $this->checkEMailField(explode(',', (string) $field), $addEmailAsName);
            }

            $checkResult = $this->checkEMail($field);
            if (null !== $checkResult) {
                if ($addEmailAsName) {
                    $checkResult = '"'.$checkResult.'" <'.$checkResult.'>';
                }
                $res[] = $checkResult;
            }
        }

        return implode(', ', $res);
    }

    /**
     * Check validity of email addresses.
     *
     * @param string $email
     */
    public function checkEMail($email)
    {
        if (null === $email) {
            return null;
        }

        $email = trim($email);

        $res = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($res) {
            return $email;
        }
        // Check if it has unicode chars.
        $l = mb_strlen($email);
        if ($l !== strlen($email)) {
            // Replace wide chars by “X”.
            $s = str_repeat(' ', $l);
            for ($i = 0; $i < $l; ++$i) {
                $ch = mb_substr($email, $i, 1);
                $s[$i] = strlen($ch) > 1 ? 'X' : $ch;
            }
            // Re-check now.
            $res = filter_var($s, FILTER_VALIDATE_EMAIL);
            if ($res) {
                return $email;
            }
        }

        return null;
    }

    /**
     * Set a return path if necessary.
     *
     * This method can be used for overrides in project specific mail services.
     */
    protected function setReturnPath(Email $email, MailSend $mailToSend): Email
    {
        $config = $this->globalConfig;
        if (!$config->isEmailDataportBounceSystem()) {
            return $email;
        }

        // ReturnPath muss die Form haben Prefix-MailToSendId@domain
        $returnPath = $config->getEmailBouncePrefix().'-'.$mailToSend->getId().'@'.$config->getEmailBounceDomain();
        $email->returnPath($returnPath);

        return $email;
    }

    public function deleteAfterDays(int $days): int
    {
        return $this->mailRepository->deleteAfterDays($days);
    }

    /**
     * Transforms a html string to markdown. For business logic reasons, we needed to add a few modifications,
     * so it's no longer strict markdown.
     */
    public function convertHtmlToCustomMarkdown(string $html): string
    {
        $translator = $this->translator;
        $underline = $translator->trans('editor.underline');
        $strikethrough = $translator->trans('editor.strikethrough');
        $manualTransformation = [
            [
                '<u>',
                '<'.$underline.'>',
            ],
            [
                '</u>',
                '</'.$underline.'>',
            ],
            [
                '<s>',
                '<'.$strikethrough.'>',
            ],
            [
                '</s>',
                '</'.$strikethrough.'>',
            ],
        ];
        foreach ($manualTransformation as $transformation) {
            $html = str_replace($transformation[0], $transformation[1], $html);
        }

        $markdownConverter = new HtmlConverter(
            [
                'strip_tags'   => false,
                'header_style' => 'atx',
            ]
        );

        return $markdownConverter->convert($html);
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findWithCertainty(string $id): MailSend
    {
        $mailSend = $this->mailRepository->find($id);

        if (null === $mailSend) {
            $this->logger->error("No MailSend entity with id: '$id' was found.");
            throw new EntityNotFoundException();
        }

        return $mailSend;
    }
}
