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
use demosplan\DemosPlanCoreBundle\Repository\ReportRepository;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\String\u;

class ExportReportService extends CoreService
{
    /** @var array */
    private $styles;

    public function __construct(private readonly ReportMessageConverter $messageConverter, private readonly ReportRepository $reportRepository, private readonly TranslatorInterface $translator)
    {
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
     * Generates the report with the received info in pdf format.
     *
     * @return WriterInterface
     *
     * @throws Exception
     */
    public function generateProcedureReport(array $reportInfo): array
    {
        $reportMessages = [];
        foreach ($reportInfo as $reportCategory) {
            $reportHeader = $reportCategory['headerMessage'] ?? $reportCategory['titleMessage'];
            $reportCategoryTitle = $reportCategory['titleMessage'];
            $reportMessages[$reportCategoryTitle] = [$reportHeader => []];
            /** @var ReportEntry $reportEntry */
            foreach ($reportCategory['reportEntries'] as $reportEntry) {
                $reportMessages[$reportCategoryTitle][$reportHeader][] = [
                    'creationDate' => $reportEntry->getCreated()->format('d.m.Y H:i:s'),
                    'userName'     => u($reportEntry->getUserName()),
                    'message'      => $this->messageConverter->convertMessage($reportEntry),
                ];
            }
            if ([] === $reportMessages[$reportCategoryTitle][$reportHeader]) {
                $reportMessages[$reportCategoryTitle][$reportHeader] = $this->translator->trans('text.protocol.no.entries');
            }
        }

        return $reportMessages;
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
