<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Consultation;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Export\XlsxExporter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BulkLetterExporter extends XlsxExporter
{
    /**
     * @return array<string, Xlsx>
     */
    public function generateExport(array $tokenList, string $userName): array
    {
        $this->setMetaData($userName);
        $this->setHeaderRow();
        $this->setData($tokenList);
        $this->setDataFormat();

        return $this->getFileArray();
    }

    private function setMetaData(string $userName): void
    {
        $this->spreadsheet->getProperties()
            ->setCreator($userName)
            ->setLastModifiedBy($userName)
            ->setTitle($this->translator->trans('consultation.export.bulk.letter.meta.title'))
            ->setSubject($this->translator->trans('consultation.export.bulk.letter.meta.title'))
            ->setDescription(
                $this->translator->trans('consultation.export.bulk.letter.meta.description')
            );
    }

    private function setHeaderRow(): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', $this->translator->trans('consultation.export.bulk.letter.header.name'));
        $sheet->setCellValue('B1', $this->translator->trans('consultation.export.bulk.letter.header.email'));
        $sheet->setCellValue('C1', $this->translator->trans('consultation.export.bulk.letter.header.street'));
        $sheet->setCellValue('D1', $this->translator->trans('consultation.export.bulk.letter.header.house.number'));
        $sheet->setCellValue('E1', $this->translator->trans('consultation.export.bulk.letter.header.postal.code'));
        $sheet->setCellValue('F1', $this->translator->trans('consultation.export.bulk.letter.header.city'));
        $sheet->setCellValue('G1', $this->translator->trans('consultation.export.bulk.letter.header.token'));
        $sheet->setCellValue('H1', $this->translator->trans('consultation.export.bulk.letter.header.note'));
        $sheet->setCellValue('I1', $this->translator->trans('consultation.export.bulk.letter.header.procedure.name'));
        $sheet->setCellValue('J1', $this->translator->trans('consultation.export.bulk.letter.header.export.date'));
    }

    private function setData(array $tokenList): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        // current time needs to be converted to excel readable format
        $dateTimeNow = time();
        $excelDateValue = Date::PHPToExcel($dateTimeNow);

        $i = 2;
        /** @var ConsultationToken $token */
        foreach ($tokenList as $token) {
            $statement = $token->getStatement();
            $statementMeta = $statement->getMeta();
            $authorName = '' !== $statementMeta->getSubmitName() ?
                $statementMeta->getSubmitName() : $statementMeta->getAuthorName();
            $author = trim($statement->getAuthorName());
            $email = '' !== $author && !$statement->isAnonymous()
                ? $statement->getSubmitterEmailAddress()
                : User::ANONYMOUS_USER_DEPARTMENT_NAME;
            $sheet->setCellValue('A'.$i, $authorName);
            $sheet->setCellValue('B'.$i, $email);
            $sheet->setCellValue('C'.$i, $statementMeta->getOrgaStreet());
            $sheet->setCellValueExplicit('D'.$i, $statementMeta->getHouseNumber(), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$i, $statementMeta->getOrgaPostalCode(), DataType::TYPE_STRING);
            $sheet->setCellValue('F'.$i, $statementMeta->getOrgaCity());
            $sheet->setCellValue('G'.$i, $token->getToken());
            $sheet->setCellValue('H'.$i, $token->getNote());
            $sheet->setCellValue('I'.$i, $statement->getProcedure()->getName());
            $sheet->setCellValue('J'.$i, $excelDateValue);
            ++$i;
        }
    }

    private function setDataFormat(): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        // Set column to display as date
        $sheet->getStyle('J:J')
            ->getNumberFormat()
            ->setFormatCode('dd.mm.yyyy');
        $sheet->getStyle('J:J')
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->getDefaultColumnDimension()->setWidth(24);
        // T25539 The Date does not seem to care about a default width - it needs to be told extra
        $sheet->getColumnDimension('J')->setWidth(24);
    }

    /**
     * @return array<string, Xlsx>
     */
    private function getFileArray(): array
    {
        $fileName = sprintf(
            $this->translator->trans('consultation.export.bulk.letter.file.title').'-%s.xlsx',
            Carbon::now()->format('Y-m-d')
        );

        return [
            'filename' => $fileName,
            'writer'   => $this->getWriter(),
        ];
    }
}
