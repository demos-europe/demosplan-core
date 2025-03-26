<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsentRevokeToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class StatementSubmissionNotifier
{
    public function __construct(private readonly CurrentProcedureService $currentProcedureService, private readonly CurrentUserInterface $currentUser, private readonly Environment $twig, private readonly LoggerInterface $logger, private readonly MailService $mailService, private readonly MessageBagInterface $messageBag, private readonly TranslatorInterface $translator, private readonly DraftStatementService $draftStatementService, private readonly OrgaService $orgaService, private readonly ContentService $contentService, private readonly ConsultationTokenService $consultationTokenService)
    {
    }

    /**
     * Send a confirmation mail to the institution or directly to the user address if the submitter is a "logged in user".
     *
     * @param array<int, Statement> $submittedStatements
     *
     * @throws Exception
     */
    public function sendConfirmationMails(array $submittedStatements)
    {
        $vars = [];
        $mailData = $this->prepareConfirmationMailData(
            $submittedStatements,
            $this->currentProcedureService->getProcedureWithCertainty()->getId()
        );

        $procedure = $this->currentProcedureService->getProcedureArray();
        $pdfResult = $mailData->getPdfResult();
        $consultationToken = $mailData->getConsultationToken();
        $destinationAddress = $mailData->getDestinationAddress();
        $consultationTokenString = $mailData->getConsultationTokenString();
        $from = $procedure['agencyMainEmailAddress'];
        $scope = 'extern';
        $attachments = null === $pdfResult
            ? []
            : [$pdfResult->toArray()];

        // Cover the case of once enabled but now disabled permission (token would be exists, but should not send.)
        if (false === $this->currentUser->hasPermission('feature_public_consultation')) {
            $consultationTokenString = null;
        }

        $vars['mailsubject'] = $this->translator->trans('email.subject.statement.submitted');
        $vars['mailbody'] = $this->twig
            ->load('@DemosPlanCore/DemosPlanStatement/email_statement_submitted.html.twig')
            ->render(
                [
                    'templateVars' => [
                        'externIds'                 => implode(', ', $mailData->getExternIds()),
                        'procedureOrga'             => $mailData->getProcedureOrga(),
                        'isCitizen'                 => $this->currentUser->getUser()->isCitizen(),
                        'consultationTokenString'   => $consultationTokenString,
                        'procedureEmail'            => $from,
                    ],
                ]
            );

        try {
            // Send email, use mailtemmplate dm_stellungnahme as vanilla template
            $mailSend = $this->mailService->sendMail(
                'dm_stellungnahme',
                'de_DE',
                $destinationAddress,
                $from,
                '',
                '',
                $scope,
                $vars,
                $attachments
            );
        } catch (Exception $e) {
            $this->logger->warning('Send Confirmation Email failed: ', [$e]);
            $this->messageBag->add('warning', 'error.email.confirmation.not.sent');

            return;
        }

        // On successfully sent email, set the destinationAddress to the Token, which was used in the sent mail.
        if ($consultationToken instanceof ConsultationToken) {
            $this->consultationTokenService->updateEmailOfToken($consultationToken, $mailSend);
        }
    }

    /**
     * Benachrichtige die Fachplaner, dass Stellungnahmen eingereicht wurden
     * <p>
     * Will send a separate email for each given statement informing about the submission of a the statement.
     * The email will be send with no sender email (empty string) to the 'agencyMainEmailAddress' of the
     * given procedure and the 'agencyExtraEmailAddresses' of the procedure as CC.
     * <p>
     * If the Orga of the Procedure has activated its setting to receive notifications about new statements
     * ('emailNotificationNewStatement') an additional email is send (again) with no sender address and the
     * same receivers and CCs listing all submitted Statements.
     *
     * @param array<int, Statement> $submittedStatements
     *
     * @throws Exception|Throwable
     */
    public function sendNotificationEmailForAdmins(array $submittedStatements): void
    {
        $procedure = $this->currentProcedureService->getProcedureWithCertainty();

        // New statements
        // Fetch  infos about the statements
        foreach ($submittedStatements as $statement) {
            if ($statement->getPublicAllowed()) {
                // notifications for statements to be published should always be sent
                try {
                    // Send Notification because Statement needs to be checked by Planner
                    $this->sendNewPublicAllowedStatementNotification(
                        $statement,
                        $procedure,
                        [$procedure->getAgencyMainEmailAddress()],
                        $procedure->getAgencyExtraEmailAddresses()->toArray()
                    );
                } catch (Exception $e) {
                    $this->logger->warning('Could not send Mail: ', [$e]);
                }
            }
        }

        // does the orga want to receive notification mails?
        if ($this->isOrgaWantingNotifications($procedure)) {
            $emailText = $this->twig->load(
                '@DemosPlanCore/DemosPlanStatement/send_notification_email_for_new_statement.html.twig'
            )->renderBlock(
                'body_plain',
                [
                    'templateVars' => [
                        'statements' => $submittedStatements,
                        'procedure'  => $procedure,
                    ],
                ]
            );
            $this->sendNewStatementNotification(
                $procedure->getName(),
                'email.subject.admin.notification',
                $emailText,
                [$procedure->getAgencyMainEmailAddress()],
                $procedure->getAgencyExtraEmailAddresses()->toArray()
            );
        }
    }

    /**
     * @param array<int, Statement> $submittedStatements array of Statements as arrays
     *
     * @throws Exception
     */
    private function prepareConfirmationMailData(array $submittedStatements, string $procedureId): PreparedConfirmationMailData
    {
        $toebOrgaId = '';
        $draftStatements = [];
        $externIds = [];
        $pdfResult = [];
        $procedureOrga = null;
        $tokenString = null;

        if (!is_array($submittedStatements) || count($submittedStatements) < 1) {
            throw new Exception('Cannot send confirmation email for empty statement array');
        }

        foreach ($submittedStatements as $submittedStatement) {
            $consultationToken = $this->consultationTokenService->getTokenForStatement($submittedStatement);
            if ($consultationToken instanceof ConsultationToken) {
                $tokenString = $consultationToken->getToken(); // this overwrite is intended: only one token is necessary for the user.
            }

            $draftStatement = $submittedStatement->getDraftStatement();
            // statement->externId could be also used, but this is the context of submit a draftStatement,
            // therefore use the information of the drafStatement as far as possible
            $externIds[] = $draftStatement->getNumber();

            $draftStatements[] = $draftStatement;
            $draftStatementArrays[] = $this->draftStatementService->getDraftStatement($draftStatement->getId());

            $toebOrgaId = $draftStatement->getOrganisation()->getId();
        }

        // did we get an Orga?
        if ('' === $toebOrgaId) {
            $this->logger->warning('Send Confirmation Email to self failed. Could not find any ToebOrga ');
            throw new Exception('Could not find any ToebOrga for submitted statements');
        }

        // If current user a logged in user, use their email instead of institution email:
        $orga = $this->orgaService->getOrga($toebOrgaId);
        $destinationAddress = $orga->getEmail2();

        if ($this->currentUser->getPermissions()->hasPermission('feature_notification_citizen_statement_submitted')) {
            $destinationAddress = $this->currentUser->getUser()->getEmail();
        }

        try {
            $type = 'list_final_group';
            if ($this->currentUser->getUser()->hasAnyOfRoles([Role::GUEST, Role::CITIZEN])) {
                $type = 'list_final_group_citizen';
            }

            $pdfFile = $this->draftStatementService->generatePdf($draftStatementArrays, $type, $procedureId);
        } catch (Exception $e) {
            $this->logger->warning('Could not create PDF for Email ', [$e]);
        }

        if (0 < count($draftStatements)) {
            try {
                $procedureOrga = $draftStatements[0]->getProcedure()->getOrga();
            } catch (Exception $e) {
                $this->logger->warning('Could not find procedure for statement ', [$e]);
            }
        }

        return new PreparedConfirmationMailData(
            $destinationAddress,
            $externIds,
            $pdfFile ?? null,
            $procedureOrga,
            $consultationToken ?? null,
            $tokenString,
        );
    }

    /**
     * Send Notification because Statement needs to be checked by Planner.
     *
     * @param array|Statement $statement
     * @param string[]        $ccs
     *
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    protected function sendNewPublicAllowedStatementNotification(
        Statement $statement,
        Procedure $procedure,
        array $recipients,
        $ccs = []
    ): void {
        $emailText = $this->twig->load(
            '@DemosPlanCore/DemosPlanStatement/send_notification_email_for_new_statement_public_allowed.html.twig'
        )->renderBlock(
            'body_plain',
            [
                'templateVars' => [
                    'statement' => $statement,
                    'procedure' => $procedure,
                ],
            ]
        );
        $this->sendNewStatementNotification(
            $procedure->getName(),
            'email.subject.admin.notification.public.allowed',
            $emailText,
            $recipients,
            $ccs
        );
    }

    /**
     * @throws Exception
     */
    protected function isOrgaWantingNotifications(Procedure $procedure): bool
    {
        /** @var Orga $orga */
        $orga = $this->orgaService->getOrga($procedure->getOrgaId());
        $wantsNotification = false;

        // Do they want to have a notification email? ->Info saved in Settings
        try {
            $settingForKeyStatement = $this->contentService->getSettings('emailNotificationNewStatement');
            foreach ($settingForKeyStatement as $settingStatement) {
                // @ToDo refactor, kann gleich bei der Datenbankabfrage gemacht werden
                if ($orga->getId() === $settingStatement['orgaId'] && 'true' === $settingStatement['content']) {
                    $wantsNotification = true;
                    break;
                }
            }
        } catch (Exception) {
            $this->logger->warning('Key emailNotificationNewStatement für Settings nicht vorhanden');
        }

        return $wantsNotification;
    }

    /**
     * @param string $procedureName
     * @param string $subjectTransKey
     * @param string $emailText
     * @param array  $recipients
     * @param array  $ccs
     *
     * @throws Exception
     */
    protected function sendNewStatementNotification($procedureName, $subjectTransKey, $emailText, $recipients, $ccs)
    {
        $vars = [];
        $from = '';
        $scope = 'extern';
        $vars['mailsubject'] = $this->translator->trans(
            $subjectTransKey,
            ['procedure_name' => $procedureName]
        );
        $vars['mailbody'] = html_entity_decode(
            $emailText,
            ENT_QUOTES,
            'UTF-8'
        );
        // Send email
        foreach (array_merge($recipients, $ccs) as $to) {
            $this->mailService->sendMail(
                'dm_stellungnahme',
                'de_DE',
                $to,
                $from,
                [],
                '',
                $scope,
                $vars
            );
        }
    }

    /**
     * Versende eine Bestätigungs-Email nach eingereichter Stellungnahme (Beteiligungsebene).
     *
     * @param string $statementText
     * @param string $recipient
     * @param mixed  $number
     *
     * @throws Throwable
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendEmailOnNewStatement(
        $statementText,
        $recipient,
        ?Statement $submittedStatement = null,
        $number = null,
        GdprConsentRevokeToken $gdprConsentRevokeToken = null
    ): void {
        $mailTemplateVars = [];
        $vars = [];
        $procedureDetails = $this->currentProcedureService->getProcedureArray();
        $mailTemplateVars['procedure'] = $procedureDetails;
        $orga = $this->orgaService->getOrga($procedureDetails['orgaId']);
        $mailTemplateVars['organisation'] = $orga;
        $mailTemplateVars['statement'] = $statementText;
        $consultationToken = null;

        if (null !== $gdprConsentRevokeToken) {
            $mailTemplateVars['token'] = $gdprConsentRevokeToken->getToken();
        }
        if (null !== $number) {
            $mailTemplateVars['number'] = $number;
        }
        if ($submittedStatement instanceof Statement) {
            $consultationToken = $this->consultationTokenService->getTokenForStatement($submittedStatement);
            // some phases does not generate Tokens
            if (null !== $consultationToken) {
                $mailTemplateVars['consultationTokenString'] = $consultationToken->getToken();
            }
        }
        $from = $procedureDetails['agencyMainEmailAddress'];

        $mailTemplateVars['signature'] = [
            'nameLegal'                 => $orga->getName(),
            'street'                    => $orga->getStreet(),
            'houseNumber'               => $orga->getHouseNumber(),
            'postalcode'                => $orga->getPostalcode(),
            'city'                      => $orga->getCity(),
            'email'                     => $from,
        ];

        $emailText = $this->twig->load(
            '@DemosPlanCore/DemosPlanStatement/new_statement_confirm_email.html.twig'
        )->renderBlock('body_plain', ['templateVars' => $mailTemplateVars]);
        $vars['mailsubject'] = $this->translator->trans(
            'email.subject.public.confirm',
            ['procedure_name' => $procedureDetails['externalName']]
        );
        $this->logger->debug(
            'Try to send new statement Email with following emailtext '.DemosPlanTools::varExport(
                $emailText,
                true
            )
        );
        $vars['mailbody'] = html_entity_decode(
            $emailText,
            ENT_QUOTES,
            'UTF-8'
        );
        $this->logger->debug(
            'Try to send new statement Email with following emailtext html_entity_decoded '.DemosPlanTools::varExport(
                $vars['mailbody'],
                true
            )
        );
        $scope = 'extern';

        // Create PDF if statement object was supplied
        $attachments = [];
        if ($submittedStatement instanceof Statement) {
            $statements = [$this->draftStatementService->getDraftStatement($submittedStatement->getDraftStatementId())];
            $pdfFile = $this->draftStatementService->generatePdf(
                $statements,
                'list_final_group_citizen',
                $procedureDetails['id']
            );
            $attachments[] = $pdfFile->toArray();
        }

        $mailSend = $this->mailService->sendMail(
            'dm_stellungnahme',
            'de_DE',
            $recipient,
            $from,
            '',
            '',
            $scope,
            $vars,
            $attachments
        );

        // On successfully sent email, set the destinationAddress to the Token, which was used in the sent mail.
        if ($consultationToken instanceof ConsultationToken) {
            $this->consultationTokenService->updateEmailOfToken($consultationToken, $mailSend);
        }
    }
}
