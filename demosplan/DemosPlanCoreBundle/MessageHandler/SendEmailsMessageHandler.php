<?php

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Message\SendEmailsMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendEmailsMessageHandler
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly LoggerInterface $logger
    )
    {

    }

    public function __invoke(SendEmailsMessage $message): void
    {
        $mailsSent = 0;
        try {
            $mailsSent = $this->mailService->sendMailsFromQueue();
        } catch (Exception $e) {
            $this->logger->error('Error sending mails', [$e]);
        }
        if ($mailsSent > 0) {
            $this->logger->info('Mails sent: '.$mailsSent);
        }
    }
}
