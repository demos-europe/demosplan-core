<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Repository\ReportRepository;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Writer\PDF;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\String\u;

class ExportReportService extends CoreService
{
    /** @var array */
    private $styles;

    public function __construct(
        private readonly ReportMessageConverter $messageConverter,
        private readonly ReportRepository $reportRepository,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        $this->initializeStyles();
    }

    /**
     * Returns an array with the necessary info to generate the procedure report.
     */
    public function getReportInfo(string $procedureId, PermissionsInterface $permissions): array
    {
        $report = [];

        if ($permissions->hasPermission('feature_procedure_report_general')) {
            $report['general'] = $this->getGeneralReportInfo($procedureId);
        }

        if ($permissions->hasPermission('feature_procedure_report_public_phase')) {
            $report['public'] = $this->getPublicReportInfo($procedureId);
        }

        if ($permissions->hasPermission('feature_procedure_report_invitations')) {
            $report['emailInvitations'] = $this->getEmailInvitationsReportInfo($procedureId);
        }

        if ($permissions->hasPermission('feature_procedure_report_register_invitations')) {
            $report['registeredInvitations'] = $this->getRegisteredInvitationsReportInfo($procedureId);
        }

        if ($permissions->hasPermission('feature_procedure_report_final_mails')) {
            $report['finalMails'] = $this->getFinalMailsReportInfo($procedureId);
        }

        if ($permissions->hasPermission('feature_procedure_report_statements')) {
            $report['statements'] = $this->getStatementsReportInfo($procedureId);
        }

        return $report;
    }

    /**
     * Generates the report with the received info in the given document format.
     *
     * @param string $format - 'ODText', 'RTF', 'Word2007', 'HTML', 'PDF'
     *
     * @throws Exception
     */
    public function generateProcedureReport(
        string $procedureId,
        array $reportInfo,
        array $reportMeta,
        string $format = 'PDF',
    ): PDF {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $section = $phpWord->addSection();
        $procedureUrl = $this->urlGenerator->generate(
            'DemosPlan_procedure_public_detail',
            ['procedure' => $procedureId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $docTitle = $reportMeta['name'].' - '.$this->translator->trans('protocol');
        $section->addText($docTitle, $this->styles['docTitleFont']);
        $section->addText($procedureUrl, $this->styles['url']);

        $dateText = $this->translator->trans('exported.on', ['date' => $reportMeta['exportDate'], 'time' => $reportMeta['exportTime']]);
        $section->addText($dateText, $this->styles['baseFont']);
        $section->addTextBreak(2);

        foreach ($reportInfo as $reportBlock) {
            $this->writeReportBlockTitle($section, $reportBlock['titleMessage']);
            if (empty($reportBlock['reportEntries'])) {
                $noEntriesMessage = $this->translator->trans('text.protocol.no.entries');
                $noInfoText = $section->addText($noEntriesMessage, $this->styles['noInfoMessage']);
                $noInfoText->setParagraphStyle($this->styles['paragraph']);
            } else {
                $table = $section->addTable();
                $blockTitleMsg = $reportBlock['headerMessage'] ?? $reportBlock['titleMessage'];
                $this->writeReportBlockHeader($blockTitleMsg, $table);
                $this->writeReportBlockBody($reportBlock['reportEntries'], $table);
            }
        }

        $this->writeSignatureField($section, $dateText);

        return IOFactory::createWriter($phpWord, $format);
    }

    /** Adds a field for signatures at the end of $section.
     */
    private function writeSignatureField(Section $section, string $exportedOn = '')
    {
        $font = $this->styles['baseFont'];
        $section->addTextBreak(2);

        $placeAndDateText = $this->translator->trans('city').', '.$this->translator->trans('date');
        $signatureText = $this->translator->trans('signature');
        $placeholder = '______________________________';

        $section->addText($placeholder, $font);
        $section->addText($placeAndDateText, $font);
        $section->addTextBreak(1);
        $section->addText($placeholder, $font);
        $section->addText($signatureText, $font);
        $section->addTextBreak(1);
        $section->addText($exportedOn, $font);
    }

    private function writeReportBlockTitle(Section $section, string $blockTitleMsg)
    {
        $titleMessage = htmlspecialchars($this->translator->trans($blockTitleMsg));
        $tableTitle = $section->addText($titleMessage, $this->styles['titleFont']);
        $tableTitle->setParagraphStyle($this->styles['paragraph']);
    }

    private function writeReportBlockHeader(string $blockTitleMsg, Table $table)
    {
        $table->addRow($this->styles['rowHeight'], $this->styles['tableHeader']);

        $dateHeaderLabel = htmlspecialchars($this->translator->trans('date'));
        $dateHeaderCell = $table->addCell($this->styles['dateCellWidth'], $this->styles['tableHeader']);
        $dateHeaderText = $dateHeaderCell->addText($dateHeaderLabel, $this->styles['tableHeaderFont']);
        $dateHeaderText->setParagraphStyle($this->styles['paragraph']);

        $messageHeaderLabel = htmlspecialchars($this->translator->trans($blockTitleMsg));
        $headerCell = $table->addCell($this->styles['descriptionCellWidth'], $this->styles['tableHeader']);
        $headerCell->addText($messageHeaderLabel, $this->styles['tableHeaderFont']);

        $userHeaderLabel = htmlspecialchars($this->translator->trans('user'));
        $userHeaderCell = $table->addCell($this->styles['userCellWidth'], $this->styles['tableHeader']);
        $userHeaderCell->addText($userHeaderLabel, $this->styles['tableHeaderFont']);
    }

    private function writeReportBlockBody(array $reportEntries, Table $table)
    {
        /** @var ReportEntry $reportEntry */
        foreach ($reportEntries as $reportEntry) {
            $this->writeReportEntry($reportEntry, $table);
        }
    }

    private function writeReportEntry(ReportEntry $reportEntry, Table $table)
    {
        $table->addRow($this->styles['rowHeight'], $this->styles['tableRow']);

        $creationDate = $reportEntry->getCreated()->format('d.m.Y H:i:s');
        $dateEntryCell = $table->addCell($this->styles['dateCellWidth']);
        $dateEntryCell->addText($creationDate, $this->styles['baseFont']);

        $messageParts = $this->getMessageParts($reportEntry);
        $cell = $table->addCell($this->styles['descriptionCellWidth']);
        foreach ($messageParts as $messagePart) {
            $message = strip_tags((string) $messagePart);
            $cellText = $cell->addText($message, $this->styles['baseFont']);
            $cellText->setParagraphStyle($this->styles['paragraph']);
        }

        $userName = $reportEntry->getUserName();
        $userCell = $table->addCell($this->styles['userCellWidth']);
        $userCell->addText(u($userName)->normalize()->toString(), $this->styles['baseFont']);
    }

    /**
     * Initializes the styles to be used to generate the report.
     */
    private function initializeStyles()
    {
        $this->styles['page'] = ['orientation' => 'landscape'];
        $this->styles['titleFont'] = ['name' => 'helvetica', 'size' => 15];
        $this->styles['tableHeader'] = ['bgColor' => 'f5f5f5', 'valign' => 'center'];
        $this->styles['tableHeaderFont'] = ['name' => 'helvetica', 'size' => 14, 'align' => 'center'];
        $this->styles['baseFont'] = ['name' => 'helvetica', 'color' => '696969', 'align' => 'left', 'size' => 12];
        $this->styles['url'] = $this->styles['baseFont'];
        $this->styles['url']['size'] = 10;
        $this->styles['tableRow'] = ['bgColor' => 'ffffff', 'valign' => 'center'];
        $this->styles['paragraph'] = ['spaceBefore' => '50', 'spaceAfter' => '50'];
        $this->styles['docTitleFont'] = ['name' => 'helvetica', 'size' => 18];

        $this->styles['rowHeight'] = 100;
        $this->styles['dateCellWidth'] = 500;
        $this->styles['descriptionCellWidth'] = 600;
        $this->styles['userCellWidth'] = 500;
        $this->styles['noInfoMessage'] = ['name' => 'helvetica', 'size' => 12];
    }

    protected function getGeneralReportInfo(string $procedureId): array
    {
        $generalActions = $this->reportRepository->getProcedureReportEntries(
            $procedureId, [ReportEntry::GROUP_PROCEDURE], [ReportEntry::CATEGORY_ADD, ReportEntry::CATEGORY_UPDATE]
        );

        return [
            'titleMessage'  => 'general',
            'headerMessage' => 'act',
            'reportEntries' => $generalActions,
        ];
    }

    protected function getPublicReportInfo(string $procedureId): array
    {
        $publicActions = $this->reportRepository->getProcedureReportEntries(
            $procedureId, [ReportEntry::GROUP_PROCEDURE], [ReportEntry::CATEGORY_CHANGE_PHASES]
        );

        return [
            'titleMessage'  => 'procedure.public.phase',
            'reportEntries' => $publicActions,
        ];
    }

    protected function getEmailInvitationsReportInfo(string $procedureId): array
    {
        $emailInvitations = $this->reportRepository->getProcedureReportEntries(
            $procedureId, [ReportEntry::GROUP_PROCEDURE], [ReportEntry::CATEGORY_INVITATION]
        );

        return [
            'titleMessage'  => 'email.invitations',
            'reportEntries' => $emailInvitations,
        ];
    }

    protected function getRegisteredInvitationsReportInfo(string $procedureId): array
    {
        $registeredInvitations = $this->reportRepository->getProcedureReportEntries(
            $procedureId, [ReportEntry::GROUP_PROCEDURE], [ReportEntry::CATEGORY_REGISTER_INVITATION]
        );

        return [
            'titleMessage'  => 'email.register.invitations',
            'reportEntries' => $registeredInvitations,
        ];
    }

    protected function getFinalMailsReportInfo(string $procedureId): array
    {
        $finalMails = $this->reportRepository->getProcedureReportEntries(
            $procedureId,
            [ReportEntry::GROUP_STATEMENT, ReportEntry::GROUP_PROCEDURE],
            [ReportEntry::CATEGORY_FINAL_MAIL]
        );

        return [
            'titleMessage'  => 'text.protocol.finalMails',
            'reportEntries' => $finalMails,
        ];
    }

    protected function getStatementsReportInfo(string $procedureId): array
    {
        $statements = $this->reportRepository->getProcedureReportEntries(
            $procedureId, [ReportEntry::GROUP_STATEMENT],
            [
                ReportEntry::CATEGORY_ADD,
                ReportEntry::CATEGORY_ANONYMIZE_META,
                ReportEntry::CATEGORY_ANONYMIZE_TEXT,
                ReportEntry::CATEGORY_COPY,
                ReportEntry::CATEGORY_DELETE_ATTACHMENTS,
                ReportEntry::CATEGORY_DELETE_TEXT_FIELD_HISTORY,
                ReportEntry::CATEGORY_DELETE,
                ReportEntry::CATEGORY_MOVE,
                ReportEntry::CATEGORY_STATEMENT_SYNC_INSOURCE,
                ReportEntry::CATEGORY_STATEMENT_SYNC_INTARGET,
            ]
        );

        return [
            'titleMessage'  => 'statements',
            'reportEntries' => $statements,
        ];
    }

    private function getMessageParts(ReportEntry $reportEntry)
    {
        return explode('<br />', $this->messageConverter->convertMessage($reportEntry));
    }
}
