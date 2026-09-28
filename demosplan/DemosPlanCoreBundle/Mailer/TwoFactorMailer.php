<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Mailer;

use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class TwoFactorMailer implements AuthCodeMailerInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly MailService $mailService,
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $vars = [
            'mailsubject' => $this->translator->trans(
                '2fa.email.subject',
                ['projectName' => $this->parameterBag->get('project_name')]
            ),
            'mailbody'    => $this->twig->render(
                '@DemosPlanCore/DemosPlanCore/email/2fa_email_auth.html.twig',
                ['authCode' => $user->getEmailAuthCode()]
            ),
        ];

        $this->mailService->sendMail(
            'dm_subscription',
            'de_DE',
            $user->getEmailAuthRecipient(),
            '',
            '',
            '',
            MailSend::MAIL_SCOPE_EXTERN,
            $vars
        );
    }
}
