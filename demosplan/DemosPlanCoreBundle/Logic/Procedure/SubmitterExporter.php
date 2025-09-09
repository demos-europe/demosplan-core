<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Export\XlsxExporter;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Collection;

class SubmitterExporter extends XlsxExporter
{
    /**
     * Extract and merge the submitter data from given segments.
     * The result will be an Excel-document, which contains a submitter per row.
     * The statementsIds of a submitter are comma seperated in a column.
     *
     * @param array<int, Statement> $statements
     *
     * @return array<string, Xlsx>
     */
    public function generateExport(array $statements, string $userName): array
    {
        $this->setMetaData($userName);
        $this->setHeaderRow();
        $this->setData($statements);
        $this->setDataFormat();

        return $this->getFileArray();
    }

    private function setMetaData(string $userName): void
    {
        $this->spreadsheet->getProperties()
            ->setCreator($userName)
            ->setLastModifiedBy($userName)
            ->setTitle($this->translator->trans('submitter.export.meta.title'))
            ->setSubject($this->translator->trans('submitter.export.meta.title'))
            ->setDescription($this->translator->trans('consultation.export.bulk.letter.meta.description'));
    }

    private function setHeaderRow(): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', $this->translator->trans('name'));
        $sheet->setCellValue('B1', $this->translator->trans('organisation'));
        $sheet->setCellValue('C1', $this->translator->trans('department'));
        $sheet->setCellValue('D1', $this->translator->trans('street'));
        $sheet->setCellValue('E1', $this->translator->trans('street.number'));
        $sheet->setCellValue('F1', $this->translator->trans('postalcode'));
        $sheet->setCellValue('G1', $this->translator->trans('city'));
        $sheet->setCellValue('H1', $this->translator->trans('email'));
        $sheet->setCellValue('I1', $this->translator->trans('internId.plural'));
        $sheet->setCellValue('J1', $this->translator->trans('id.plural'));
    }

    /**
     * @param array<int, Statement> $statements
     */
    private function setData(array $statements): void
    {
        $statementGroups = $this->groupStatements($statements);
        $offset = 2;
        foreach ($statementGroups as $statementGroup) {
            $this->createCellValue('A', $offset, 'submitterName', $statementGroup);
            $this->createCellValue('B', $offset, 'organisationName', $statementGroup);
            $this->createCellValue('C', $offset, 'departmentName', $statementGroup);
            $this->createCellValue('D', $offset, 'street', $statementGroup);
            $this->createCellValue('E', $offset, 'houseNumber', $statementGroup);
            $this->createCellValue('F', $offset, 'organisationPostalCode', $statementGroup);
            $this->createCellValue('G', $offset, 'organisationCity', $statementGroup);
            $this->createCellValue('H', $offset, 'emailAddress', $statementGroup);
            $this->createCellValue('I', $offset, 'internId', $statementGroup);
            $this->createCellValue('J', $offset, 'externId', $statementGroup);
            ++$offset;
        }
    }

    private function setDataFormat(): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $sheet->getDefaultColumnDimension()->setWidth(20);
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('E:F')
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getColumnDimension('E')
            ->setWidth(20);
        $sheet->getColumnDimension('F')
            ->setWidth(20);
    }

    /**
     * @return array<string, Xlsx>
     */
    private function getFileArray(): array
    {
        $fileName = sprintf(
            $this->translator->trans('submitter.data').'-%s.xlsx',
            Carbon::now()->format('Y-m-d')
        );

        return [
            'filename' => $fileName,
            'writer'   => $this->getWriter(),
        ];
    }

    /**
     * T23843
     * Group Statements of same name, street, houseNumber, postalCode, and location(orgaCity)
     * and extract data to export.
     * Create uniqueness by using concatenated postcode, location, street, house-number and submitter-name as key to
     * group specific statement-data for export.
     * Each group will contain one collection with one statementGroup (Collection).
     * The statementGroup have strings as keys and a string or null as values.
     *
     * @param array<int, Statement> $statements
     */
    private function groupStatements(array $statements): Collection
    {
        return collect($statements)->mapToGroups(function (Statement $statement): array {
            $statementData = [];
            $key = $statement->getOrgaPostalCode()
                .$statement->getOrgaCity()
                .$statement->getOrgaStreet()
                .$statement->getMeta()->getHouseNumber()
                .$statement->getMeta()->getSubmitName();

            // do not group in case of different location data are given
            if ('' === $statement->getOrgaPostalCode() || '' === $statement->getOrgaCity() || '' === $statement->getOrgaStreet()) {
                $key .= $statement->getId(); // just add the ID to avoid grouping but keep key for sorting alphabetically
            }

            $organisationName = '';
            if (User::ANONYMOUS_USER_ORGA_NAME !== $statement->getMeta()->getOrgaName()) {
                $organisationName = $statement->getMeta()->getOrgaName();
            }

            $departmentName = '';
            if (User::ANONYMOUS_USER_DEPARTMENT_NAME !== $statement->getMeta()->getOrgaDepartmentName()) {
                $departmentName = $statement->getMeta()->getOrgaDepartmentName();
            }

            $statementData['submitterName'] = $statement->getMeta()->getSubmitName();
            $statementData['organisationName'] = $organisationName;
            $statementData['departmentName'] = $departmentName;
            $statementData['organisationPostalCode'] = $statement->getOrgaPostalCode();
            $statementData['organisationCity'] = $statement->getOrgaCity();
            $statementData['street'] = $statement->getOrgaStreet();
            $statementData['houseNumber'] = $statement->getMeta()->getHouseNumber();
            $statementData['emailAddress'] = $statement->getSubmitterEmailAddress();
            $statementData['internId'] = $statement->getInternId();
            $statementData['externId'] = $statement->getExternId();

            return [$key => $statementData];
        })->sortKeys();
    }

    /**
     * Fill statement groups data into the active spreadsheet.
     * Filtering null values and empty strings
     * Erase duplicate entries.
     * Convert array into string to allow to print it in one excel-field.
     */
    private function createCellValue(string $pCoordinate, int $offset, string $key, Collection $statementGroup): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $sheet->setCellValue($pCoordinate.$offset,
            $statementGroup->pluck($key)->filter()->unique()->implode(', ')
        );
    }
}
