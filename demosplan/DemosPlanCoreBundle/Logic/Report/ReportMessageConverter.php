<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Twig\Extension\DateExtension;
use demosplan\DemosPlanCoreBundle\ValueObject\Report\ProcedureFinalMailReportEntryData;
use demosplan\DemosPlanCoreBundle\ValueObject\Report\RegisteredInvitationReportEntryData;
use demosplan\DemosPlanCoreBundle\ValueObject\Report\StatementFinalMailReportEntryData;
use demosplan\DemosPlanCoreBundle\ValueObject\Report\UnregisteredInvitationReportEntryData;
use Exception;
use Psr\Log\LoggerInterface;
use Seld\JsonLint\JsonParser;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportMessageConverter
{
    /** @var JsonParser */
    protected $jsonParser;

    /** @var DateExtension */
    protected $dateExtension;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    public function __construct(
        DateExtension $dateExtension,
        GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
        $this->dateExtension = $dateExtension;
        $this->globalConfig = $globalConfig;
    }

    public function convertMessage(ReportEntry $reportEntry): string
    {
        $message = '';

        try {
            $group = $reportEntry->getGroup();
            $category = $reportEntry->getCategory();

            $reportEntryMessage = $reportEntry->getMessageDecoded(true);

            if (ReportEntry::GROUP_PROCEDURE === $group) {
                if (ReportEntry::CATEGORY_ADD === $category) {
                    $message = $this->translator->trans('procedure.created');
                } elseif (ReportEntry::CATEGORY_CHANGE_PHASES === $category) {
                    $message = $this->getProcedureChangePhasesMessage($reportEntryMessage);
                } elseif (ReportEntry::CATEGORY_INVITATION === $category) {
                    $unregisteredInvitationReportEntryData = UnregisteredInvitationReportEntryData::createFromArray($reportEntryMessage);
                    $message = $this->getProcedureInvitationMessage($unregisteredInvitationReportEntryData);
                } elseif (ReportEntry::CATEGORY_REGISTER_INVITATION === $category) {
                    $registeredInvationReportEntryData = RegisteredInvitationReportEntryData::createFromArray($reportEntryMessage);
                    $message = $this->getProcedureRegisterInvitationMessage($registeredInvationReportEntryData);
                } elseif (ReportEntry::CATEGORY_FINAL_MAIL === $category) {
                    $procedureFinalMailReportEntry = ProcedureFinalMailReportEntryData::createFromArray($reportEntryMessage);
                    $message = $this->getProcedureFinalMailMessage($procedureFinalMailReportEntry);
                } elseif (ReportEntry::CATEGORY_UPDATE === $category) {
                    $message = $this->getProcedureUpdateMessage($reportEntryMessage);
                }
            } elseif (ReportEntry::GROUP_STATEMENT === $group) {
                if (ReportEntry::CATEGORY_ADD === $category) {
                    $message = $this->getStatementAddMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_COPY === $category) {
                    $message = $this->getStatementCopyMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_DELETE === $category) {
                    $message = $this->getStatementDeleteMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_FINAL_MAIL === $category) {
                    $statementFinalMailReportEntry = StatementFinalMailReportEntryData::createFromArray($reportEntryMessage);
                    $message = $this->getStatementFinalMailMessage($statementFinalMailReportEntry);
                }
                if (ReportEntry::CATEGORY_STATEMENT_SYNC_INSOURCE === $category) {
                    $message = $this->getStatementSynchronizedSourceProcedureMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_STATEMENT_SYNC_INTARGET === $category) {
                    $message = $this->getStatementSynchronizedTargeProcedureMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_MOVE === $category) {
                    $message = $this->getStatementMoveMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_UPDATE === $category) {
                    $message = $this->getStatementUpdateMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_ANONYMIZE_META === $category) {
                    $message = $this->getOriginalStatementMessage($reportEntryMessage, 'confirm.original.statement.meta.anonymized');
                }
                if (ReportEntry::CATEGORY_ANONYMIZE_TEXT === $category) {
                    $message = $this->getOriginalStatementMessage($reportEntryMessage, 'confirm.original.statement.text.anonymized');
                }
                if (ReportEntry::CATEGORY_DELETE_TEXT_FIELD_HISTORY === $category) {
                    $message = $this->getOriginalStatementMessage($reportEntryMessage, 'confirm.original.statement.text.history.deleted');
                }
                if (ReportEntry::CATEGORY_DELETE_ATTACHMENTS === $category) {
                    $message = $this->getOriginalStatementMessage($reportEntryMessage, 'confirm.original.statement.attachment.deleted');
                }
            } elseif (ReportEntry::GROUP_ELEMENT === $group) { // Planungsdokumentenkategorien
                if (ReportEntry::CATEGORY_ADD === $category) {
                    $message = $this->createAddElementMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_UPDATE === $category) {
                    $message = $this->createUpdateElementMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_DELETE === $category) {
                    $message = $this->createDeleteElementMessage($reportEntryMessage['elementTitle'], $reportEntryMessage['elementCategory']);
                }
            } elseif (ReportEntry::GROUP_PARAGRAPH === $group) { // Kapitel
                if (ReportEntry::CATEGORY_ADD === $category) {
                    $message = $this->createAddParagraphMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_UPDATE === $category) {
                    $message = $this->createUpdateParagraphMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_DELETE === $category) {
                    $message = $this->createDeleteParagraphMessage($reportEntryMessage['paragraphTitle']);
                }
            } elseif (ReportEntry::GROUP_SINGLE_DOCUMENT === $group) { // Planungsdokumente
                if (ReportEntry::CATEGORY_ADD === $category) {
                    $message = $this->createAddSingleDocumentMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_UPDATE === $category) {
                    $message = $this->createUpdateSingleDocumentMessage($reportEntryMessage);
                }
                if (ReportEntry::CATEGORY_DELETE === $category) {
                    $message = $this->createDeleteSingleDocumentMessage($reportEntryMessage['documentTitle']);
                }
            } elseif (ReportEntry::GROUP_PLAN_DRAW === $group) { // Planzeichnung
                if (ReportEntry::CATEGORY_CHANGE === $category) {
                    $message = $this->createChangePlanDrawMessage($reportEntryMessage);
                }
            }
        } catch (Exception $e) {
            $this->logger->warning('Exception when converting protocol message', [$e]);
            $message = '';
        }

        return $message;
    }

    /**
     * This translation key can be modified by getStatementMessage()
     * possible variations are:
     * 'confirm.statement.id.synchronized.target'
     * 'confirm.statement.id.synchronized.target.nolink'.
     */
    protected function getStatementSynchronizedTargeProcedureMessage(array $message): string
    {
        return $this->getStatementMessage($message, 'confirm.statement.id.synchronized.target');
    }

    /**
     * This translation key can be modified by getStatementMessage()
     * possible variations are:
     * 'confirm.statement.id.synchronized.source'
     * 'confirm.statement.id.synchronized.source.nolink'.
     */
    protected function getStatementSynchronizedSourceProcedureMessage(array $message): string
    {
        return $this->getStatementMessage($message, 'confirm.statement.id.synchronized.source');
    }

    protected function getStatementMessage(array $message, string $transKey): string
    {
        [$procedureId, $messageId] = $this->handleAncientReportMessages($message);

        if ('' === $procedureId
            || !$this->permissions->hasPermission('area_admin_assessmenttable')
        ) {
            return $this->translator->trans($transKey.'.nolink', [
                'externId' => $message['externId'] ?? '',
            ]);
        }

        return $this->translator->trans($transKey, [
            'externId' => $message['externId'] ?? '',
            'link'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => $procedureId,
                '_fragment'   => $messageId,
            ]),
        ]);
    }

    /**
     * Extract procedureId and ident from given message.
     *
     * @param array $message
     *
     * @return string[]
     */
    protected function handleAncientReportMessages($message): array
    {
        $procedureId = (isset($message['procedure']) && array_key_exists('id', $message['procedure']))
                ? $message['procedure']['id']
                : $message['procedure']['ident'] ?? '';

        $messageId = array_key_exists('id', $message) ? $message['id'] :
            $message['ident'] ?? '';

        return [$procedureId, $messageId];
    }

    protected function getOriginalStatementMessage(array $message, string $transKey): string
    {
        [$procedureId, $messageId] = $this->handleAncientReportMessages($message);

        return $this->translator->trans($transKey, [
            'externId' => $message['externId'],
            'link'     => $this->router->generate('dplan_assessmenttable_view_original_table', [
                'procedureId' => $procedureId,
                '_fragment'   => $messageId,
            ]),
        ]);
    }

    /**
     * This translation key can be modified by getStatementMessage()
     * possible variations are:
     * 'confirm.statement.submitted'
     * 'confirm.statement.submitted.nolink'.
     */
    protected function getStatementAddMessage(array $message): string
    {
        return $this->getStatementMessage($message, 'confirm.statement.submitted');
    }

    /**
     * This translation key can be modified by getStatementMessage()
     * possible variations are:
     * 'confirm.statement.id.copied'
     * 'confirm.statement.id.copied.nolink'.
     */
    protected function getStatementCopyMessage(array $message): string
    {
        return $this->getStatementMessage($message, 'confirm.statement.id.copied');
    }

    /**
     * This translation key can be modified by getStatementMessage()
     * possible variations are:
     * 'confirm.statement.id.updated'
     * 'confirm.statement.id.updated.nolink'.
     */
    protected function getStatementUpdateMessage(array $message): string
    {
        return $this->getStatementMessage($message, 'confirm.statement.id.updated');
    }

    protected function getStatementDeleteMessage(array $message): string
    {
        return $this->translator->trans('confirm.statement.id.deleted', [
            'externId' => $message['externId'], ]);
    }

    protected function getStatementFinalMailMessage(StatementFinalMailReportEntryData $entryData): string
    {
        $outputLines = [];
        $outputLines[] = $this->translator->trans('text.protocol.procedure.finalMail', [
            'url'      => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => $entryData->getProcedureId(),
                '_fragment'   => $entryData->getStatementId(),
            ]),
            'externId' => $entryData->getExternId(),
        ]);

        $mailSubject = $entryData->getMailSubject();
        if (null !== $mailSubject) {
            $outputLines[] = $this->getSubjectLine($mailSubject);
        }

        $attachmentNames = $entryData->getEmailAttachmentNames();
        if ([] !== $attachmentNames) {
            $outputLines[] = $this->translator->trans('attachments').':';
            foreach ($attachmentNames as $attachmentName) {
                $outputLines[] = $attachmentName;
            }
        }

        return $this->toHtmlLines($outputLines);
    }

    protected function getStatementMoveMessage(array $message): string
    {
        return $this->translator->trans('protocol.statement.moved', [
            'externId'            => $message['placeholderStatementExternId'],
            'targetProcedureName' => $message['targetProcedureName'],
            'newExternId'         => $message['movedStatementExternId'],
        ]);
    }

    protected function getProcedureUpdateMessage(array $message): string
    {
        $returnMessage = [];
        $dateExtension = $this->getDateExtension();

        // name changed
        if (array_key_exists('oldName', $message) && array_key_exists('newName', $message)) {
            $returnMessage[] = $this->translator->trans('text.protocol.procedure.name.changed', [
                'oldName' => $message['oldName'], 'newName' => $message['newName'],
            ]);
        }
        if (array_key_exists('oldPublicName', $message) && array_key_exists('newPublicName', $message)) {
            $returnMessage[] = $this->translator->trans('text.protocol.procedure.public.name.changed', [
                'oldName' => $message['oldPublicName'], 'newName' => $message['newPublicName'],
            ]);
        }
        if ($this->isPeriodMessageDataAvailable($message, false)) {
            $returnMessage[] = $this->generatePeriodChangeMessage($message, false);
        }

        if (!array_key_exists('oldStartDate', $message) && array_key_exists('newStartDate', $message)
            && !array_key_exists('oldEndDate', $message) && array_key_exists('newEndDate', $message)) {
            // only required for initial report
            $agencyVisibilityMessage = $this->translator->trans('invitable_institution.participation');
            $citizenVisibilityMessage = $this->translator->trans('public.participation');
            $mainMessage = $this->translator->trans('text.protocol.procedure.time.set', [
                'startDate' => $dateExtension->dateFilter($message['newStartDate'] ?? ''),
                'endDate'   => $dateExtension->dateFilter($message['newEndDate'] ?? ''),
            ]);
            $returnMessage[] = "$agencyVisibilityMessage: $mainMessage";
            $returnMessage[] = "$citizenVisibilityMessage: $mainMessage";
        }

        if ($this->isPeriodMessageDataAvailable($message, true)) {
            $returnMessage[] = $this->generatePeriodChangeMessage($message, true);
        }

        // @improve T23803
        $designatedMessageBuilder = new ProcedureUpdateReportMessageBuilder(
            $message,
            $dateExtension,
            $this->translator,
            $this->globalConfig,
            'oldDesignated',
            'newDesignated'
        );

        $designatedMessageBuilder->maybeAddMessage(
            'Phase',
            'text.protocol.procedure.designated.phase.changed',
            'text.protocol.procedure.designated.phase.changed.fromNull',
            'text.protocol.procedure.designated.phase.changed.toNull',
            false
        );
        $designatedMessageBuilder->maybeAddMessage(
            'SwitchDate',
            'text.protocol.procedure.designated.start.changed',
            'text.protocol.procedure.designated.start.changed',
            'text.protocol.procedure.designated.start.changed.toNull',
            false,
            true
        );
        $designatedMessageBuilder->maybeAddMessage(
            'SwitchEndDate',
            'text.protocol.procedure.designated.end.changed',
            'text.protocol.procedure.designated.end.changed.fromNull',
            'text.protocol.procedure.designated.end.changed.toNull',
            false,
            true
        );
        $designatedMessageBuilder->maybeAddMessage(
            'Phase',
            'text.protocol.procedure.designated.phase.changed',
            'text.protocol.procedure.designated.phase.changed.fromNull',
            'text.protocol.procedure.designated.phase.changed.toNull',
            true
        );
        $designatedMessageBuilder->maybeAddMessage(
            'SwitchDate',
            'text.protocol.procedure.designated.start.changed',
            'text.protocol.procedure.designated.start.changed',
            'text.protocol.procedure.designated.start.changed.toNull',
            true,
            true
        );
        $designatedMessageBuilder->maybeAddMessage(
            'SwitchEndDate',
            'text.protocol.procedure.designated.end.changed',
            'text.protocol.procedure.designated.end.changed.fromNull',
            'text.protocol.procedure.designated.end.changed.toNull',
            true,
            true
        );

        $returnMessage = array_merge($returnMessage, $designatedMessageBuilder->getMessages());

        if (array_key_exists('externalDesc', $message)) {
            $returnMessage[] = $this->translator->trans('text.protocol.procedure.externaldesc.changed', [
                'externalDesc' => $message['externalDesc'],
            ]);
        }
        if (array_key_exists('oldAuthorizedUsers', $message) && array_key_exists('newAuthorizedUsers', $message)) {
            $returnMessage[] = $this->translator->trans('text.protocol.procedure.authorized.user.changed', [
                'oldAuthorizedUsers' => $message['oldAuthorizedUsers'],
                'newAuthorizedUsers' => $message['newAuthorizedUsers'],
            ]);
        }
        if (array_key_exists('mapExtent', $message)) {
            $returnMessage[] = $this->translator->trans('text.protocol.procedure.mapextent.changed', [
                'mapExtent' => str_replace(',', ', ', (string) $message['mapExtent']), // T15125
            ]);
        }
        if (array_key_exists('targetProcedure', $message) && array_key_exists('relatedInstitutionName', $message)) {
            $returnMessage[] = $this->translator->trans('text.protocol.procedure.coupled.targetProcedure', [
                'targetProcedure'        => $message['targetProcedure'],
                'relatedInstitutionName' => $message['relatedInstitutionName'],
            ]);
        }
        if (array_key_exists('sourceProcedure', $message) && array_key_exists('relatedInstitutionName', $message)) {
            $returnMessage[] = $this->translator->trans('text.protocol.procedure.coupled.sourceProcedure', [
                'sourceProcedure'        => $message['sourceProcedure'],
                'relatedInstitutionName' => $message['relatedInstitutionName'],
            ]);
        }

        // Fallback for really old report entries
        if (0 === count($returnMessage)) {
            $returnMessage[] = $this->translator->trans('text.protocol.procedure.changed.generic');
        }

        return $this->toHtmlLines($returnMessage);
    }

    protected function getProcedureInvitationMessage(UnregisteredInvitationReportEntryData $entryData): string
    {
        $returnMessage = [];

        $invitedOrgas = [];
        // only log recipients with email address
        $recipients = $entryData->getRecipients();
        foreach ($recipients as $recipient) {
            if (isset($recipient['email2']) && 0 < strlen((string) $recipient['email2'])) {
                $invitedOrga = [
                    'ident'     => $recipient['ident'],
                    'nameLegal' => $recipient['nameLegal'],
                    'email2'    => $recipient['email2'],
                ];
                if (isset($recipient['ccEmails'])) {
                    $invitedOrga['ccEmails'] = $recipient['ccEmails'];
                }
                $invitedOrgas[] = $invitedOrga;
            }
        }

        $mailSubject = $entryData->getMailSubject();
        if (null !== $mailSubject) {
            $returnMessage[] = $this->getSubjectLine($mailSubject);
        }

        // hole den Phasennamen
        $returnMessage[] = $this->globalConfig->getPhaseNameWithPriorityInternal($entryData->getPhase());
        if (0 !== count($invitedOrgas)) {
            $returnMessage[] = $this->translator->trans('email.invitation.sent');

            foreach ($invitedOrgas as $orga) {
                $returnMessage[] = $orga['nameLegal'] ?? ', '.$orga['email2'] ?? '';
                if (array_key_exists('ccEmails', $orga) && 0 < (is_countable($orga['ccEmails']) ? count($orga['ccEmails']) : 0)) {
                    $returnMessage[] = $this->translator->trans('email.cc').': '.
                        implode(', ', $orga['ccEmails']);
                }
            }
        }

        return $this->toHtmlLines($returnMessage);
    }

    protected function getProcedureRegisterInvitationMessage(RegisteredInvitationReportEntryData $entryData): string
    {
        $returnMessage = [];

        $mailSubject = $entryData->getMailSubject();
        if (null !== $mailSubject) {
            $returnMessage[] = $this->getSubjectLine($mailSubject);
        }

        $returnMessage[] = $this->translator->trans('email.invitation.sent');
        $recipients = [];
        $combinedRecipients = array_unique(array_merge($entryData->getRecipients(), $entryData->getCcAddresses()));
        foreach ($combinedRecipients as $recipient) {
            if ('' === $recipient) {
                continue;
            }
            $recipients[] = $recipient;
        }
        $returnMessage[] = implode(', ', $recipients);

        return $this->toHtmlLines($returnMessage);
    }

    protected function getProcedureFinalMailMessage(ProcedureFinalMailReportEntryData $entry): string
    {
        $returnMessage = [];

        $returnMessage[] = $this->translator->trans('message.submitters.sent', [
            'count' => $entry->getReceiverCount(),
        ]);
        $returnMessage[] = $this->getSubjectLine($entry->getMailSubject());
        $returnMessage[] = $this->translator->trans('message').': '.nl2br((string) $entry->getMailBody());

        return $this->toHtmlLines($returnMessage);
    }

    protected function getProcedureChangePhasesMessage(array $message): string
    {
        $returnMessage = [];
        $translator = $this->translator;
        $message = $this->alterPhaseEntry($message);
        $createdBySystem = $message['createdBySystem'] ?? false;

        // phase changed
        if (array_key_exists('oldPhase', $message) && array_key_exists('newPhase', $message)) {
            $phaseChangeMessageData = [
                'oldPhase'     => $message['oldPhase'],
                'newPhase'     => $message['newPhase'],
                'oldIteration' => $message['oldPhaseIteration'] ?? 0,
                'newIteration' => $message['newPhaseIteration'] ?? 0,
            ];

            if ($createdBySystem) {
                $returnMessage[] = $translator->trans('text.protocol.phase.system', $phaseChangeMessageData);

                // Only show in case of a phase change by the system, as some customers
                // think the start and end date does not refer to a phase but the
                // procedure as a whole instead. Customers using the designated phase
                // change know better, thus it is ok to add the following line.
                if (isset($message['oldPhaseStart'], $message['newPhaseStart'],
                    $message['oldPhaseEnd'], $message['newPhaseEnd'])) {
                    $visibilityMessage = $translator->trans('invitable_institution.participation');
                    $dateChangeMessage = $translator->trans('text.protocol.phase.date', [
                        '%oldPhaseStart%' => $this->dateExtension->dateFilter($message['oldPhaseStart']),
                        '%newPhaseStart%' => $this->dateExtension->dateFilter($message['newPhaseStart']),
                        '%oldPhaseEnd%'   => $this->dateExtension->dateFilter($message['oldPhaseEnd']),
                        '%newPhaseEnd%'   => $this->dateExtension->dateFilter($message['newPhaseEnd']),
                    ]);

                    $returnMessage[] = "$visibilityMessage: $dateChangeMessage";
                }
            } else {
                $returnMessage[] = $translator->trans('text.protocol.phase', $phaseChangeMessageData);
            }
        }
        if (array_key_exists('oldPublicPhase', $message) && array_key_exists('newPublicPhase', $message)) {
            if ($createdBySystem) {
                $returnMessage[] = $translator->trans('text.protocol.publicphase.system', [
                    'oldPublicPhase'          => $message['oldPublicPhase'],
                    'newPublicPhase'          => $message['newPublicPhase'],
                    'oldPublicPhaseIteration' => $message['oldPublicPhaseIteration'] ?? 0,
                    'newPublicPhaseIteration' => $message['newPublicPhaseIteration'] ?? 0,
                ]);

                // Only show in case of a phase change by the system, as some customers
                // think the start and end date does not refer to a phase but the
                // procedure as a whole instead. Customers using the designated phase
                // change know better, thus it is ok to add the following line.
                if (isset($message['oldPublicPhaseStart'], $message['newPublicPhaseStart'],
                    $message['oldPublicPhaseEnd'], $message['newPublicPhaseEnd'])) {
                    $visibilityMessage = $translator->trans('public.participation');
                    $dateChangeMessage = $translator->trans('text.protocol.phase.date', [
                        '%oldPhaseStart%' => $this->dateExtension->dateFilter($message['oldPublicPhaseStart']),
                        '%newPhaseStart%' => $this->dateExtension->dateFilter($message['newPublicPhaseStart']),
                        '%oldPhaseEnd%'   => $this->dateExtension->dateFilter($message['oldPublicPhaseEnd']),
                        '%newPhaseEnd%'   => $this->dateExtension->dateFilter($message['newPublicPhaseEnd']),
                    ]);

                    $returnMessage[] = "$visibilityMessage: $dateChangeMessage";
                }
            } else {
                $returnMessage[] = $translator->trans('text.protocol.publicphase', [
                    'oldPublicPhase'          => $message['oldPublicPhase'],
                    'newPublicPhase'          => $message['newPublicPhase'],
                    'oldPublicPhaseIteration' => $message['oldPublicPhaseIteration'] ?? 0,
                    'newPublicPhaseIteration' => $message['newPublicPhaseIteration'] ?? 0,
                ]);
            }
        }

        $returnMessage[] = $translator->trans('text.protocol.published.documents');

        $documents = [];
        // has publishedDocuments
        // should html be added here? use template? really?
        if (array_key_exists('publishedDocuments', $message) && 0 < (is_countable($message['publishedDocuments']) ? count($message['publishedDocuments']) : 0)) {
            foreach ($message['publishedDocuments'] as $document) {
                $documents[] = '<strong>'.$document.'</strong>';
            }
        }
        // has categories
        if (array_key_exists('categories', $message) && 0 < (is_countable($message['categories']) ? count($message['categories']) : 0)) {
            foreach ($message['categories'] as $category) {
                // category has limited orga access
                $documents[] = '<strong>'.$category['name'].'</strong>';
                if (array_key_exists('access', $category) && null !== $category['access']) {
                    $documents[] = $translator->trans('access.for').' '.
                        implode(', ', $category['access']);
                }
                if (array_key_exists('documents', $category) && 0 < (is_countable($category['documents']) ? count($category['documents']) : 0)) {
                    $categoryDocumentsString = '';
                    foreach ($category['documents'] as $categoryDocument) {
                        $categoryDocumentsString .= '<li>'.
                            $categoryDocument['name'].' ('.$categoryDocument['fileName'].')</li>';
                    }
                    $documents[] = '<ul>'.$categoryDocumentsString.'</ul>';
                }

                if (array_key_exists('existingParagraphs', $category) && 0 < (is_countable($category['existingParagraphs']) ? count($category['existingParagraphs']) : 0)) {
                    $categoryParagraphsString = '';

                    foreach ($category['existingParagraphs'] as $categoryParagraph) {
                        $categoryParagraphsString .= '<li>'.$categoryParagraph.'</li>';
                    }

                    $documents[] = '<ul>'.$categoryParagraphsString.'</ul>';
                }
            }
        } elseif (array_key_exists('elements', $message) && 0 < (is_countable($message['elements']) ? count($message['elements']) : 0)) {
            foreach ($message['elements'] as $elementTitle => $element) {
                $documents[] = '<strong>'.$elementTitle.'</strong>';
                $fileTitles = '';
                foreach ($element['files'] as $fileTitle => $file) {
                    $fileTitles .= '<li>'.$fileTitle.'</li>';
                }
                $documents[] = '<ul>'.$fileTitles.'</ul>';
            }
        }

        if (0 === count($documents)) {
            $documents[] = $translator->trans('none');
        }

        $returnMessage = array_merge($returnMessage, $documents);

        return $this->toHtmlLines($returnMessage);
    }

    /**
     * Füge Metainformationen zu dem Eintrag bei einer Phasenänderung hinzu.
     *
     * @param array $message
     */
    protected function alterPhaseEntry($message)
    {
        $translator = $this->translator;
        // Es gibt derzeit leider keine geschicktere Stelle als hier, die Phasennamen zu ersetzen...
        if (isset($message['newPhase'])) {
            $message['oldPhase'] = $this->globalConfig->getPhaseNameWithPriorityInternal($message['oldPhase']);
            $message['newPhase'] = $this->globalConfig->getPhaseNameWithPriorityInternal($message['newPhase']);
        }
        if (isset($message['newPublicPhase'])) {
            $message['oldPublicPhase'] = $this->globalConfig->getPhaseNameWithPriorityExternal(
                $message['oldPublicPhase']
            );
            $message['newPublicPhase'] = $this->globalConfig->getPhaseNameWithPriorityExternal(
                $message['newPublicPhase']
            );
        }

        // Welche Dokumente waren zum Zeitpunkt der Phasenumstellung eingestellt?
        $publishedDocuments = [];
        if (isset($message['begruendung']) && true === $message['begruendung']) {
            $publishedDocuments[] = $translator->trans('reason');
        }
        if (isset($message['begruendungPDF'])) {
            $publishedDocuments[] = $translator->trans('reason.pdf');
        }
        if (isset($message['verordnungPDF'])) {
            $publishedDocuments[] = $translator->trans('agreement.pdf');
        }
        if (isset($message['planGisVisible']) && true === $message['planGisVisible']) {
            $publishedDocuments[] = $translator->trans(
                'text.protocol.drawing',
                ['layerName' => $message['planGisName']]
            );
        }
        if (isset($message['planPDF'])) {
            $publishedDocuments[] = $translator->trans('drawing.explanation');
        }
        if (isset($message['planDrawPDF'])) {
            $publishedDocuments[] = $translator->trans('pdf.public.drawing');
        }

        if (0 < count($publishedDocuments)) {
            $message['publishedDocuments'] = $publishedDocuments;
        }

        $categories = [];
        if (isset($message['elements'])) {
            foreach ($message['elements'] as $key => $element) {
                $documentsOfElement = [];
                $category = [];
                $fileNumber = 0;

                // Nur wenn Dokumente zu der Kategorie eingestellt wurden, soll diese im Protokoll erscheinen.
                if (array_key_exists('files', $element) && is_array($element['files'])) {
                    // Name der Kategorie speichern
                    $category['name'] = $key;

                    // Dokumente durchgehen, den (Anzeige-)Namen extrahieren und im Array $documentsOfElement ablegen.
                    foreach ($element['files'] as $fileKey => $document) {
                        $documentParts = explode(':', (string) $document);
                        // report messages format changed over time
                        if (is_int($fileKey)) {
                            $documentsOfElement[$fileNumber]['name'] = $documentParts[0];
                            $documentsOfElement[$fileNumber]['fileName'] = $documentParts[1];
                        } else {
                            $documentsOfElement[$fileNumber]['name'] = $fileKey;
                            $documentsOfElement[$fileNumber]['fileName'] = $documentParts[0];
                        }
                        ++$fileNumber;
                    }
                    // alle Dokumente(-Namen) der Kategorie speichern.
                    $category['documents'] = $documentsOfElement;

                    // ist Zugriffsbeschränkung gesetzt?
                    if (isset($element['access'][0])) {
                        // übernehme das Array mit dem Inhalt der zugriffsberechtigten Institutionen
                        $category['access'] = $element['access'];
                    }
                } elseif (isset($element[0])) {
                    // is this part ever needed?
                    $category['name'] = $key;

                    // Dokumente durchgehen, den (Anzeige-)Namen extrahieren und im Array $documentsOfElement ablegen.
                    foreach ($element as $document) {
                        $documentParts = explode(':', (string) $document);
                        $documentsOfElement[$fileNumber]['name'] = $documentParts[0];
                        $documentsOfElement[$fileNumber]['fileName'] = $documentParts[1];
                        ++$fileNumber;
                    }
                    // alle Dokumente(-Namen) der Kategorie speichern.
                    $category['documents'] = $documentsOfElement;
                }

                if (isset($category['name'])) {
                    $categories[] = $category;
                }
            }
        }

        if (array_key_exists('paragraphs', $message) && 0 < (is_countable($message['paragraphs']) ? count($message['paragraphs']) : 0)) {
            foreach ($message['paragraphs'] as $elementTitle => $element) {
                $category = [];
                $category['name'] = $elementTitle;

                if (array_key_exists('hasParagraphPdf', $element)) {
                    $category['existingParagraphs'][] = $this->translator->trans('file.as.pdf');
                }
                if (array_key_exists('hasParagraphs', $element)) {
                    $category['existingParagraphs'][] = $this->translator->trans('file.as.paragraphs');
                }
                if (0 < count($category['existingParagraphs'])) {
                    $categories[] = $category;
                }
            }
        }

        if (0 < count($categories)) {
            $message['categories'] = $categories;
        }

        return $message;
    }

    public function getJsonParser(): JsonParser
    {
        if (!$this->jsonParser instanceof JsonParser) {
            $this->jsonParser = new JsonParser();
        }

        return $this->jsonParser;
    }

    protected function getDateExtension(): DateExtension
    {
        return $this->dateExtension;
    }

    /**
     * @param string[] $lines
     */
    protected function toHtmlLines(array $lines): string
    {
        return implode('<br />', $lines);
    }

    protected function getSubjectLine(string $subject): string
    {
        return $this->translator->trans('subject').': '.$subject;
    }

    private function isPeriodMessageDataAvailable(array $message, bool $isPublic): bool
    {
        $message = collect($message);
        $publicKeyPart = $isPublic ? 'Public' : '';

        return $message->has('old'.$publicKeyPart.'StartDate')
            && $message->has('new'.$publicKeyPart.'StartDate')
            && $message->has('old'.$publicKeyPart.'EndDate')
            && $message->has('new'.$publicKeyPart.'EndDate');
    }

    private function generatePeriodChangeMessage(array $message, bool $isPublic): string
    {
        $dateExtension = $this->getDateExtension();
        $mainMessageKey = 'text.protocol.procedure.time.changed';

        $publicKeyPart = $isPublic ? 'Public' : '';
        $visibilityMessage = $isPublic
            ? $this->translator->trans('public.participation')
            : $this->translator->trans('invitable_institution.participation');

        // message variables are the same for public and internal, therefore no need to add the $publicKeyPart
        $mainMessage = $this->translator->trans($mainMessageKey, [
            'oldStartDate' => $dateExtension->dateFilter($message['old'.$publicKeyPart.'StartDate'] ?? ''),
            'oldEndDate'   => $dateExtension->dateFilter($message['old'.$publicKeyPart.'EndDate'] ?? ''),
            'newStartDate' => $dateExtension->dateFilter($message['new'.$publicKeyPart.'StartDate'] ?? ''),
            'newEndDate'   => $dateExtension->dateFilter($message['new'.$publicKeyPart.'EndDate'] ?? ''),
        ]);

        return "$visibilityMessage: $mainMessage";
    }

    /**
     * @param array $reportEntryMessage
     *
     * @return string
     */
    private function createAddElementMessage(array $reportEntryMessage): string
    {
        $restrictedToOrganisations = '' === $reportEntryMessage['organisations'] ? $this->translator->trans('unrestricted') : $reportEntryMessage['organisations'];

        $elementCategory = $reportEntryMessage['elementCategory'];
        $translationKey = 'file' === $elementCategory ? 'file.related' : 'paragraph.related';
        $elementCategory = $this->translator->trans($translationKey);

        return $this->translator->trans('report.add.element', [
            'title' => $reportEntryMessage['elementTitle'],
            'text' => substr($reportEntryMessage['elementText'], 0, 25).'...',
            'category' => $this->translator->trans($elementCategory),
            'fileName' => $reportEntryMessage['fileName'],
            'organisations' => $restrictedToOrganisations,
            'enabled' => $reportEntryMessage['enabled'] ? $this->translator->trans(
                'yes'
            ) : $this->translator->trans('no'),
        ]);
    }

    private function createUpdateElementMessage(array $reportEntryMessage): string
    {
        $restrictedToOrganisations = '' === $reportEntryMessage['organisations'] ? $this->translator->trans('unrestricted') : $reportEntryMessage['organisations'];

        $elementCategory = $reportEntryMessage['elementCategory'];
        $translationKey = 'file' === $elementCategory ? 'file.related' : 'paragraph.related';
        $elementCategory = $this->translator->trans($translationKey);

        return $this->translator->trans('report.update.element', [
            'title' => $reportEntryMessage['elementTitle'],
            'text' => $this->shortenText($reportEntryMessage['elementText']),
            'category' => $this->translator->trans($elementCategory),
            'fileName' => $reportEntryMessage['fileName'],
            'organisations' => $restrictedToOrganisations,
            'enabled' =>
                $reportEntryMessage['enabled'] ? $this->translator->trans('yes') : $this->translator->trans('no'),
        ]);
    }

    private function createAddParagraphMessage(array $reportEntryMessage): string
    {
        return $this->translator->trans('report.add.paragraph', [
            'title' => $reportEntryMessage['paragraphTitle'],
            'text' => $this->shortenText($reportEntryMessage['paragraphText']),
            'category' => $this->translator->trans($reportEntryMessage['paragraphCategory']),
            'visible' =>
                $reportEntryMessage['visible'] ? $this->translator->trans('yes') : $this->translator->trans('no'),
        ]);
    }

    private function createUpdateParagraphMessage(array $reportEntryMessage): string
    {
        return $this->translator->trans('report.update.paragraph', [
            'title' => $reportEntryMessage['paragraphTitle'],
            'text' => $this->shortenText($reportEntryMessage['paragraphText']),
            'category' => $this->translator->trans($reportEntryMessage['paragraphCategory']),
            'visible' =>
                $reportEntryMessage['visible'] ? $this->translator->trans('yes') : $this->translator->trans('no'),
        ]);
    }

    private function createAddSingleDocumentMessage(array $reportEntryMessage): string
    {
        return $this->translator->trans('report.add.singleDocument', [
            'title' => $reportEntryMessage['documentTitle'],
            'category' => $this->translator->trans($reportEntryMessage['documentCategory']),
            'fileName' => $reportEntryMessage['relatedFile'],
            'visible' =>
                $reportEntryMessage['visible'] ? $this->translator->trans('yes') : $this->translator->trans('no'),
            'statement_enabled' =>
                $reportEntryMessage['statement_enabled'] ? $this->translator->trans('yes') : $this->translator->trans('no'),
        ]);
    }

    private function createUpdateSingleDocumentMessage(array $reportEntryMessage): string
    {
        //todo
        // add related element?
        // add ID  for update entries?!

        return $this->translator->trans('report.update.singleDocument', [
            'title' => $reportEntryMessage['documentTitle'],
            'category' => $this->translator->trans($reportEntryMessage['documentCategory']),
            'fileName' => $reportEntryMessage['relatedFile'],
            'visible' =>
                $reportEntryMessage['visible'] ? $this->translator->trans('yes') : $this->translator->trans('no'),
            'statement_enabled' =>
                $reportEntryMessage['statement_enabled'] ? $this->translator->trans('yes') : $this->translator->trans('no'),
        ]);
    }

    private function createChangePlanDrawMessage(array $reportEntryMessage): string
    {
        $planDrawMessage = '';

        if (array_key_exists('planDrawFile', $reportEntryMessage)) {

            if ('' === $reportEntryMessage['planDrawFile']['old'] && '' !== $reportEntryMessage['planDrawFile']['new']) {
                $planDrawMessage .= $this->translator->trans('report.create.planDrawingFile', [
                        'fileName' => $this->getFileName($reportEntryMessage['planDrawFile']['new']),
                    ]
                );
            } elseif ('' !== $reportEntryMessage['planDrawFile']['old'] && '' === $reportEntryMessage['planDrawFile']['new']) {
                $planDrawMessage .= $this->translator->trans('report.delete.planDrawingFile', [
                        'fileName' => $this->getFileName($reportEntryMessage['planDrawFile']['old']),
                    ]
                );
            } else {
                $planDrawMessage .= $this->translator->trans('report.update.planDrawingFile', [
                        'oldFileName' => $this->getFileName($reportEntryMessage['planDrawFile']['old']),
                        'newFileName' => $this->getFileName($reportEntryMessage['planDrawFile']['new']),
                    ]
                );
            }
        }

        if (array_key_exists('planDrawingExplanation', $reportEntryMessage)) {
            if ('' === $reportEntryMessage['planDrawingExplanation']['old'] && '' !== $reportEntryMessage['planDrawingExplanation']['new']) {
                $planDrawMessage .= $this->translator->trans('report.delete.planDrawingExplanation', [
                        'fileName' => $this->getFileName($reportEntryMessage['planDrawingExplanation']['new']),
                    ]
                );
            } elseif ('' !== $reportEntryMessage['planDrawingExplanation']['old'] && '' === $reportEntryMessage['planDrawingExplanation']['new']) {
                $planDrawMessage .= $this->translator->trans('report.delete.planDrawingExplanation', [
                        'fileName' => $this->getFileName($reportEntryMessage['planDrawingExplanation']['old']),
                    ]
                );
            } else {
                $planDrawMessage .= $this->translator->trans('report.update.planDrawingExplanation', [
                        'oldFileName' => $this->getFileName($reportEntryMessage['planDrawingExplanation']['old']),
                        'newFileName' => $this->getFileName($reportEntryMessage['planDrawingExplanation']['new']),
                    ]
                );
            }
        }

        return $planDrawMessage;
    }

    private function getFileName($fileString): string
    {
        return explode(':', $fileString)[0];
    }

    private function createDeleteElementMessage(string $title, string $elementCategory): string
    {
        $translationKey = 'file' === $elementCategory ? 'file.related' : 'paragraph.related';
        $elementCategory = $this->translator->trans($translationKey);

        return $this->translator->trans('report.delete.element', ['elementCategory' => $elementCategory, 'title' => $title]);
    }

    private function createDeleteParagraphMessage($title): string
    {
        return $this->translator->trans('report.delete.paragraph', ['title' => $title]);
    }

    private function createDeleteSingleDocumentMessage($documentTitle): string
    {
        return $this->translator->trans('report.delete.singleDocument', ['title' => $documentTitle]);
    }

    private function shortenText(string $text, int $length = 50): string
    {
        if ('' === $text) {
            return '';
        }

        return substr($text, 0, $length).'...';
    }
}
