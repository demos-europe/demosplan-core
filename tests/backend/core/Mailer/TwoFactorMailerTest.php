<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Mailer;

use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Mailer\TwoFactorMailer;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class TwoFactorMailerTest extends TestCase
{
    private ?Environment $twig = null;
    private ?MailService $mailService = null;
    private ?TranslatorInterface $translator = null;
    private ?ParameterBagInterface $parameterBag = null;
    private ?TwoFactorMailer $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->twig = $this->createMock(Environment::class);
        $this->mailService = $this->createMock(MailService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);

        $this->sut = new TwoFactorMailer(
            $this->twig,
            $this->mailService,
            $this->translator,
            $this->parameterBag
        );
    }

    public function testSendAuthCodeQueuesMailWithRenderedAuthCode(): void
    {
        $user = $this->createMock(TwoFactorInterface::class);
        $user->method('getEmailAuthCode')->willReturn('123456');
        $user->method('getEmailAuthRecipient')->willReturn('user@example.com');

        $this->parameterBag->method('get')
            ->with('project_name')
            ->willReturn('Test Project');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('2fa.email.subject', ['projectName' => 'Test Project'])
            ->willReturn('Your login code for Test Project');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                '@DemosPlanCore/DemosPlanCore/email/2fa_email_auth.html.twig',
                ['authCode' => '123456']
            )
            ->willReturn('Your code is 123456');

        $this->mailService->expects($this->once())
            ->method('sendMail')
            ->with(
                'dm_subscription',
                'de_DE',
                'user@example.com',
                '',
                '',
                '',
                MailSend::MAIL_SCOPE_EXTERN,
                [
                    'mailsubject' => 'Your login code for Test Project',
                    'mailbody'    => 'Your code is 123456',
                ]
            )
            ->willReturn(new MailSend());

        $this->sut->sendAuthCode($user);
    }
}
