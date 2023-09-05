<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\ValueObject\OrgaSignatureValueObject;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class TokenCreationNotifier
{
    public function __construct(private readonly ConsultationTokenService $consultationTokenService, private readonly Environment $twig, private readonly LoggerInterface $logger, private readonly MailService $mailService, private readonly MessageBagInterface $messageBag, private readonly OrgaService $orgaService, private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @throws Exception
     */
    public function notifyIfNecessary(ConsultationToken $consultationToken): void
    {
        $statement = $consultationToken->getStatement();
        if (null !== $statement) {
            $procedure = $statement->getProcedure();

            if ($this->shouldSendNotification($statement)) {
                $this->sendNotification(
                    $consultationToken,
                    $procedure,
                    $statement,
                    $statement->getSubmitterEmailAddress()
                );
            }
        }
    }

    private function shouldSendNotification(Statement $statement): bool
    {
        // check the submission type
        // token should not be sent on statements via statement "Reden Sie mit" form, as
        // token is included in statement submitted email
        if (!$statement->isManual()) {
            return false;
        }

        // check the email address
        $emailAddress = $statement->getSubmitterEmailAddress();
        if (null === $emailAddress) {
            return false;
        }
        if ('' === trim((string) $emailAddress)) {
            return false;
        }

        // everything seems fine
        return true;
    }

    /**
     * @throws Exception
     */
    private function sendNotification(
        ConsultationToken $consultationToken,
        Procedure $procedure,
        Statement $statement,
        string $statementSubmitterAddress
    ): void {
        try {
            $mailSend = $this->mailService->sendMail(
                'dm_stellungnahme',
                'de_DE',
                $statementSubmitterAddress,
                $procedure->getOrga()->getParticipationEmail(),
                [],
                '',
                MailSend::MAIL_SCOPE_EXTERN,
                $this->getMailTemplateVars($statement, $consultationToken)
            );

            $this->consultationTokenService->updateEmailOfToken($consultationToken, $mailSend);

            $this->messageBag->add('confirm', 'consultation.notification.mail.on.token.creation.confirmation');
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->messageBag->add('error', 'consultation.notification.mail.on.token.creation.error');

            throw $e;
        }
    }

    /**
     * @return array<string, string>
     *
     * @throws Exception
     */
    private function getMailTemplateVars(Statement $statement, ConsultationToken $consultationToken): array
    {
        return [
            'mailsubject' => $this->translator->trans(
                'consultation.notification.mail.subject.on.token.creation',
                [
                    'procedureName' => $statement->getProcedure()->getName(),
                ]
            ),
            'mailbody'    => $this->getMailContent($statement, $consultationToken),
        ];
    }

    /**
     * @throws Exception
     */
    private function getMailContent(Statement $statement, ConsultationToken $consultationToken): string
    {
        return $this
            ->twig
            ->render(
                '@DemosPlanCore/DemosPlanCore/notify_consultation_token_creation.html.twig',
                $this->getMailContentTemplateVars($statement, $consultationToken)
            );
    }

    /**
     * @return array<string, string|OrgaSignatureValueObject>
     *
     * @throws Exception
     */
    private function getMailContentTemplateVars(Statement $statement, ConsultationToken $consultationToken): array
    {
        $procedure = $statement->getProcedure();

        return [
            'procedureExternalName'     => $procedure->getExternalName(),
            'statementExternId'         => $statement->getExternId(),
            'consultationToken'         => $consultationToken->getToken(),
            'signature'                 => $this->orgaService->getOrgaSignatureByProcedure($procedure),
        ];
    }
}
