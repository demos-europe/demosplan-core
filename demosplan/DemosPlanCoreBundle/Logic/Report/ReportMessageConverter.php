<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
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
    /**
     * @var PermissionsInterface
     */
    private $permissions;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        DateExtension $dateExtension,
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        PermissionsInterface $permissions,
        RouterInterface $router,
        TranslatorInterface $translator
    ) {
        $this->dateExtension = $dateExtension;
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->permissions = $permissions;
        $this->router = $router;
        $this->translator = $translator;
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

        if ('' === $procedureId ||
            !$this->permissions->hasPermission('area_admin_assessmenttable')
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
        $procedureId = (isset($message['procedure']) && array_key_exists('id', $message['procedure'])) ? $message['procedure']['id'] :
            $message['procedure']['ident'] ?? '';
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
        if (array_key_exists('oldStartDate', $message) && array_key_exists('newStartDate', $message) &&
            array_key_exists('oldEndDate', $message) && array_key_exists('newEndDate', $message)) {
            $visibilityMessage = $this->translator->trans('invitable_institution.participation');
            $mainMessage = $this->translator->trans('text.protocol.procedure.time.changed', [
                'oldStartDate' => $dateExtension->dateFilter($message['oldStartDate'] ?? ''),
                'oldEndDate'   => $dateExtension->dateFilter($message['oldEndDate'] ?? ''),
                'newStartDate' => $dateExtension->dateFilter($message['newStartDate'] ?? ''),
                'newEndDate'   => $dateExtension->dateFilter($message['newEndDate'] ?? ''),
            ]);

            $returnMessage[] = "$visibilityMessage: $mainMessage";
        }
        if (!array_key_exists('oldStartDate', $message) && array_key_exists('newStartDate', $message) &&
            !array_key_exists('oldEndDate', $message) && array_key_exists('newEndDate', $message)) {
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
        if (array_key_exists('oldPublicStartDate', $message) && array_key_exists('newPublicStartDate', $message) &&
            array_key_exists('oldPublicEndDate', $message) && array_key_exists('newPublicEndDate', $message)) {
            $visibilityMessage = $this->translator->trans('public.participation');
            $mainMessage = $this->translator->trans('text.protocol.procedure.time.changed', [
                'oldStartDate' => $dateExtension->dateFilter($message['oldPublicStartDate'] ?? ''),
                'oldEndDate'   => $dateExtension->dateFilter($message['oldPublicEndDate'] ?? ''),
                'newStartDate' => $dateExtension->dateFilter($message['newPublicStartDate'] ?? ''),
                'newEndDate'   => $dateExtension->dateFilter($message['newPublicEndDate'] ?? ''),
            ]);

            $returnMessage[] = "$visibilityMessage: $mainMessage";
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
                'mapExtent' => str_replace(',', ', ', $message['mapExtent']), // T15125
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
            if (isset($recipient['email2']) && 0 < strlen($recipient['email2'])) {
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
                if (array_key_exists('ccEmails', $orga) && 0 < count($orga['ccEmails'])) {
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
        $returnMessage[] = $this->translator->trans('message').': '.nl2br($entry->getMailBody());

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
            if ($createdBySystem) {
                $returnMessage[] = $translator->trans('text.protocol.phase.system', [
                    '%oldPhase%' => $message['oldPhase'],
                    '%newPhase%' => $message['newPhase'],
                ]);

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
                $returnMessage[] = $translator->trans('text.protocol.phase', [
                    'oldPhase' => $message['oldPhase'],
                    'newPhase' => $message['newPhase'],
                ]);
            }
        }
        if (array_key_exists('oldPublicPhase', $message) && array_key_exists('newPublicPhase', $message)) {
            if ($createdBySystem) {
                $returnMessage[] = $translator->trans('text.protocol.publicphase.system', [
                    '%oldPublicPhase%' => $message['oldPublicPhase'],
                    '%newPublicPhase%' => $message['newPublicPhase'],
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
                    'oldPublicPhase' => $message['oldPublicPhase'],
                    'newPublicPhase' => $message['newPublicPhase'],
                ]);
            }
        }

        $returnMessage[] = $translator->trans('text.protocol.published.documents');

        $documents = [];
        // has publishedDocuments
        // should html be added here? use template? really?
        if (array_key_exists('publishedDocuments', $message) && 0 < count($message['publishedDocuments'])) {
            foreach ($message['publishedDocuments'] as $document) {
                $documents[] = '<strong>'.$document.'</strong>';
            }
        }
        // has categories
        if (array_key_exists('categories', $message) && 0 < count($message['categories'])) {
            foreach ($message['categories'] as $category) {
                // category has limited orga access
                $documents[] = '<strong>'.$category['name'].'</strong>';
                if (array_key_exists('access', $category) && null !== $category['access']) {
                    $documents[] = $translator->trans('access.for').' '.
                        implode(', ', $category['access']);
                }
                if (array_key_exists('documents', $category) && 0 < count($category['documents'])) {
                    $categoryDocumentsString = '';
                    foreach ($category['documents'] as $categoryDocument) {
                        $categoryDocumentsString .= '<li>'.
                            $categoryDocument['name'].' ('.$categoryDocument['fileName'].')</li>';
                    }
                    $documents[] = '<ul>'.$categoryDocumentsString.'</ul>';
                }

                if (array_key_exists('existingParagraphs', $category) && 0 < count($category['existingParagraphs'])) {
                    $categoryParagraphsString = '';

                    foreach ($category['existingParagraphs'] as $categoryParagraph) {
                        $categoryParagraphsString .= '<li>'.$categoryParagraph.'</li>';
                    }

                    $documents[] = '<ul>'.$categoryParagraphsString.'</ul>';
                }
            }
        } elseif (array_key_exists('elements', $message) && 0 < count($message['elements'])) {
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
     *
     * @return mixed
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
                        $documentParts = explode(':', $document);
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
                        $documentParts = explode(':', $document);
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

        if (array_key_exists('paragraphs', $message) && 0 < count($message['paragraphs'])) {
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
}
