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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PrepareReportFromProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Doctrine\DBAL\Exception;

class StatementEmailSender extends CoreService
{
    public function __construct(
        protected AssignService $assignService,
        protected PermissionsInterface $permissions,
        protected StatementFragmentService $statementFragmentService,
        protected ConsultationTokenService $consultationTokenService,
        protected StatementAttachmentService $statementAttachmentService,
        protected CurrentProcedureService $currentProcedureService,
        protected StatementService $statementService,
        private readonly PrepareReportFromProcedureService $prepareReportFromProcedureService,
        private readonly UserService $userService,
        private readonly MessageBagInterface $messageBag, private readonly MailService $mailService, private readonly FileService $fileService,
    ) {
    }

    public function sendStatementMail($rParams)
    {
        try {
            $error = false;
            $vars = [];
            $ident = '';
            $emailcc = [];
            $successMessageTranslationParams = [];

            if (array_key_exists('send_body', $rParams['request'])) {
                $vars['mailbody'] = $rParams['request']['send_body'];
            }

            if (array_key_exists('send_title', $rParams['request'])) {
                $vars['mailsubject'] = $rParams['request']['send_title'];
            }

            if (array_key_exists('ident', $rParams['request'])) {
                $ident = $rParams['request']['ident'];
            }

            if (array_key_exists('emailCC', $rParams)) {
                $emailcc[] = $rParams['emailCC'];
            }

            // Überprüfe, ob E-Mails im CC-Feld eingetragen wurden
            $syntaxEmailErrors = [];
            if (array_key_exists('send_emailCC', $rParams['request']) && 0 !== strlen((string) $rParams['request']['send_emailCC'])) {
                // zerlege den string in die einzelnen E-Mail-Adressen
                $mailsCC = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', (string) $rParams['request']['send_emailCC']);
                // überprüfe jede dieser mails
                foreach ($mailsCC as $mail) {
                    // lösche alle Freizeichen am Anfang und Ende
                    $mailForCc = trim((string) $mail);
                    // Überprüfe, ob die E-Mail-Adresse korrekt ist
                    if (filter_var($mailForCc, FILTER_VALIDATE_EMAIL)) {
                        // wenn ja, gebe sie weiter
                        $emailcc[] = $mailForCc;
                    } else {
                        // wennn nicht, gebe eine Fehlermeldung aus
                        $syntaxEmailErrors[] = $mailForCc;
                    }
                }
            }
            // wenn E-Mail-Adressen falsch sind, generiere eine Fehlermeldung
            if (0 < count($syntaxEmailErrors)) {
                throw new InvalidDataException('Invalid Emails provided in CC field.');
            }

            $statement = $this->statementService->getStatement($ident);

            $procedure = $this->currentProcedureService->getProcedureWithCertainty();

            $from = $procedure->getAgencyMainEmailAddress();

            if (null !== $statement) {
                $attachments = array_map($this->createSendableAttachment(...), $rParams['emailAttachments'] ?? []);
                $attachmentNames = array_column($attachments, 'name');
                // Bürger Stellungnahmen
                if (Statement::EXTERNAL === $statement->getPublicStatement()) {
                    if ('email' === $statement->getFeedback()) {
                        $successMessageTranslationParams['sent_to'] = 'citizen_only';
                        $this->sendDmSchlussmitteilung(
                            $statement->getMeta()->getOrgaEmail(),
                            $from,
                            $emailcc,
                            $vars,
                            $attachments
                        );
                        // wenn die Mail einmal im CC verschickt wird, muss sie es später nicht mehr
                        $emailcc = [''];
                        // speicher ab, wann die Schlussmitteilung verschickt wurde
                        $this->statementService->setSentAssessment($statement->getId());
                        $this->prepareReportFromProcedureService->addReportFinalMail(
                            $statement,
                            $rParams['request']['send_title'] ?? '',
                            $attachmentNames
                        );
                    }
                // manuell eingegebene Stellungnahme
                } elseif ('' != $statement->getMeta()->getOrgaEmail()) {
                    $successMessageTranslationParams['sent_to'] = 'institution_only';
                    $this->sendDmSchlussmitteilung(
                        $statement->getMeta()->getOrgaEmail(),
                        $from,
                        $emailcc,
                        $vars,
                        $attachments
                    );
                    // wenn die Mail einmal im CC verschickt wird, muss sie es später nicht mehr
                    $emailcc = [''];
                    // speicher ab, wann die Schlussmitteilung verschickt
                    $this->statementService->setSentAssessment($statement->getId());
                    $this->prepareReportFromProcedureService->addReportFinalMail(
                        $statement,
                        $rParams['request']['send_title'] ?? '',
                        $attachmentNames
                    );
                } else {
                    // regulär eingereichte Stellungnahme (ToeB)
                    if ('' === $statement->getUId()) {
                        throw new InvalidArgumentException('UserId must be set');
                    }

                    /** @var User $user */
                    $user = $this->userService->getSingleUser($statement->getUId());

                    // Mail an Beteiligungs-E-Mail-Adresse
                    // Die Rollen brauchen keine Mail an ihre Organisation
                    if (!$user->hasAnyOfRoles([Role::GUEST, Role::CITIZEN])) {
                        $successMessageTranslationParams['sent_to'] = 'institution_only';
                        $recipients = [];
                        if (0 < strlen($user->getOrga()->getEmail2())) {
                            $recipients[] = $user->getOrga()->getEmail2();
                        }
                        // Gibt es auch noch eingetragenede BeteiligungsEmail in CC
                        if (null !== $user->getOrga()->getCcEmail2()) {
                            $ccUsersEmail = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', $user->getOrga()->getCcEmail2());
                            $recipients = array_merge($recipients, $ccUsersEmail);
                        }
                        $this->sendDmSchlussmitteilung(
                            $recipients,
                            $from,
                            $emailcc,
                            $vars,
                            $attachments
                        );
                        // speicher ab, wann die Schlussmitteilung verschickt wurde
                        $this->statementService->setSentAssessment($statement->getId());
                        foreach ($recipients as $email) {
                            $this->prepareReportFromProcedureService->addReportFinalMail(
                                $statement,
                                $rParams['request']['send_title'] ?? '',
                                $attachmentNames
                            );
                        }
                    }
                    // Mail an die einreichende Institutions-K, falls nicht identisch mit Einreicher*in
                    if (null !== $statement->getMeta()->getSubmitUId()) {
                        $submitUser = $this->userService->getSingleUser($statement->getMeta()->getSubmitUId());
                        $submitUserEmail = $submitUser->getEmail();
                        if (false === stripos($user->getEmail(), $submitUserEmail)) {
                            $successMessageTranslationParams['sent_to'] = 'institution_and_coordination';
                            $this->sendDmSchlussmitteilung(
                                $submitUserEmail,
                                $from,
                                '',
                                $vars,
                                $attachments
                            );
                            // speicher ab, wann die Schlussmitteilung verschickt wurde
                            $this->statementService->setSentAssessment($statement->getId());
                            $this->prepareReportFromProcedureService->addReportFinalMail(
                                $statement,
                                $rParams['request']['send_title'] ?? '',
                                $attachmentNames
                            );
                        }
                    }
                }
                if (!$statement->getVotes()->isEmpty()) {
                    /** @var StatementVote $vote */
                    foreach ($statement->getVotes() as $vote) {
                        $voteEmailAddress = $vote->getUserMail();
                        if (null !== $voteEmailAddress) {
                            $this->sendDmSchlussmitteilung(
                                $voteEmailAddress,
                                $from,
                                $emailcc,
                                $vars,
                                $attachments
                            );
                            // wenn die Mail einmal im CC verschickt wird, muss sie es später nicht mehr
                            $emailcc = [];
                            // speicher ab, wann die Schlussmitteilung verschickt wurde
                            $this->statementService->setSentAssessment($statement->getId());
                            $this->prepareReportFromProcedureService->addReportFinalMail(
                                $statement,
                                $rParams['request']['send_title'] ?? '',
                                $attachmentNames
                            );
                        }
                    }

                    $successMessageTranslationParams['voters_count'] = count($statement->getVotes());
                    if (Statement::EXTERNAL === $statement->getPublicStatement() && 'email' === $statement->getFeedback()) {
                        $successMessageTranslationParams['sent_to'] = 'citizen_and_voters';
                    } else {
                        $successMessageTranslationParams['sent_to'] = 'voters_only';
                    }
                }
            } else {
                $error = true;
            }
        } catch (InvalidArgumentException) {
            $this->messageBag->add('error', 'error.statement.final.send.noemail');

            return;
        }

        if (true === $error) {
            $this->messageBag->add('error', 'error.statement.final.send');

            return;
        }

        $this->messageBag->add('confirm', 'confirm.statement.final.sent', $successMessageTranslationParams);
        $this->messageBag->add('confirm', 'confirm.statement.final.sent.emailCC');
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
    public function sendDmSchlussmitteilung($to, $from, $emailcc, $vars, array $attachments): void
    {
        $this->mailService->sendMail(
            'dm_schlussmitteilung',
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

    /**
     * @return array<string,string> An array consisting of two keys: `name` and `content`. The
     *                              former contains the name of the file. The latter contains the
     *                              file content loaded from the file system. This format is needed
     *                              by {@link MailService::sendMail}.
     */
    public function createSendableAttachment(string $fileString): array
    {
        $file = $this->fileService->getFileFromFileString($fileString);
        if (null === $file) {
            throw new InvalidArgumentException("File not found for ID: $fileString");
        }

        return [
            'name'    => $file->getFilename(),
            'content' => $this->fileService->getContent($file),
        ];
    }
}
