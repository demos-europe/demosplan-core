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

use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class TwoFactorMailer implements AuthCodeMailerInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $authCode = $user->getEmailAuthCode();
        $emailContent = $this->twig->render(
            '@DemosPlanCore/DemosPlanCore/email/2fa_email_auth.html.twig',
            ['authCode' => $authCode]
        );
        $message = new Email();
        $message
            ->to($user->getEmailAuthRecipient())
            ->from(new Address($this->parameterBag->get('email_system'), ''))
            ->subject($this->translator->trans('2fa.email.subject', ['projectName' => $this->parameterBag->get('project_name')]))
            ->text($emailContent)
        ;
        $this->mailer->send($message);
    }
}
