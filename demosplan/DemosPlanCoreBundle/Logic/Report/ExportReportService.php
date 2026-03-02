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
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Repository\ReportRepository;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\String\u;

class ExportReportService extends CoreService
{
    /** @var array */
    private $styles;

    public function __construct(
        private readonly GlobalConfigInterface $globalConfig,
        private readonly ReportMessageConverter $messageConverter,
        private readonly ReportRepository $reportRepository,
        private readonly TranslatorInterface $translator,
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
     * @return WriterInterface
     *
     * @throws Exception
     */
    public function generateProcedureReport(array $reportInfo, array $reportMeta, string $format = 'PDF')
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        $phpWord->addTitleStyle(1, $this->styles['docTitleFont']);
        $phpWord->addTitleStyle(2, $this->styles['titleFont']);

        $section = $phpWord->addSection(['breakType' => 'continuous']);

        $docTitle = sprintf(
            '%s: %s - %s',
            $this->globalConfig->getProjectName(),
            $reportMeta['name'],
            $this->translator->trans('protocol'),
        );

        $section->addTitle($docTitle, 1);

        $dateText = $this->translator->trans('exported.on', ['date' => $reportMeta['exportDate'], 'time' => $reportMeta['exportTime']]);

        $meta = $section->addTextRun($this->styles['meta']);
        $meta->addText($dateText);

        foreach ($reportInfo as $reportBlock) {
            $this->writeReportBlockTitle($section, $reportBlock['titleMessage']);
            if (empty($reportBlock['reportEntries'])) {
                $noEntriesMessage = $this->translator->trans('text.protocol.no.entries');
                $noInfoText = $section->addText($noEntriesMessage, $this->styles['noInfoMessage']);
                $noInfoText->setParagraphStyle($this->styles['paragraph']);
            } else {
                $this->writeReportBlockBody($reportBlock['reportEntries'], $section);
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
        $section->addTitle($titleMessage, 2);
    }

    private function writeReportBlockBody(array $reportEntries, Section $section)
    {
        /** @var ReportEntry $reportEntry */
        foreach ($reportEntries as $reportEntry) {
            $this->writeReportEntry($reportEntry, $section);
        }
    }

    private function writeReportEntry(ReportEntry $reportEntry, Section $section)
    {
        $creationDate = $reportEntry->getCreated()->format('d.m.Y H:i:s');
        $userName = u($reportEntry->getUserName())->normalize()->toString();

        $section->addText(sprintf('%s – %s', $creationDate, $userName), $this->styles['entryHeaderFont']);
        $messageParts = $this->getMessageParts($reportEntry);

        foreach ($messageParts as $messagePart) {
            $messageParagraphs = explode('</p>', strip_tags((string) $messagePart, ['p']));
            foreach ($messageParagraphs as $messageParagraph) {
                $messagePartParaText = $section->addText(strip_tags($messageParagraph), $this->styles['baseFont']);
                $messagePartParaText->setParagraphStyle($this->styles['paragraph']);
            }
        }
    }

    /**
     * Initializes the styles to be used to generate the report.
     */
    private function initializeStyles()
    {
        $this->styles['docTitleFont'] = ['name' => 'Helvetica', 'size' => 18];
        $this->styles['titleFont'] = ['name' => 'Helvetica', 'size' => 15];

        $this->styles['baseFont'] = ['name' => 'Helvetica', 'color' => '333333', 'align' => 'left', 'size' => 12];

        $this->styles['entryHeaderFont'] = $this->styles['baseFont'];
        $this->styles['entryHeaderFont']['bold'] = true;

        $this->styles['meta'] = $this->styles['baseFont'];
        $this->styles['meta']['size'] = 10;
        $this->styles['paragraph'] = ['spaceBefore' => '50', 'spaceAfter' => '50'];
        $this->styles['noInfoMessage'] = $this->styles['baseFont'];
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
