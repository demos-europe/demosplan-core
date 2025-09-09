<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;
use demosplan\DemosPlanCoreBundle\Logic\Export\XlsxExporter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Contracts\Translation\TranslatorInterface;

class MasterToebListExport extends XlsxExporter
{
    public function __construct(private readonly PermissionsInterface $permissions, TranslatorInterface $translator)
    {
        parent::__construct($translator);
    }

    /**
     * @param MasterToeb[] $masterToebArray
     *
     * @return array<string, Xlsx>
     */
    public function generateExport(array $masterToebArray): array
    {
        $this->setMetaData();
        $headingFields = $this->getHeadingFields();
        $this->setHeaderRow($headingFields);
        $masterToebList = $this->getMasterToebList($masterToebArray, $headingFields);
        $this->setData($masterToebList);

        return $this->getFileArray();
    }

    private function setMetaData(): void
    {
        $this->spreadsheet->getProperties()
            ->setCreator('DEMOS')
            ->setLastModifiedBy('DEMOS')
            ->setTitle($this->translator->trans('master.toeb.list'))
            ->setSubject($this->translator->trans('master.toeb.list'));
        $this->spreadsheet->getActiveSheet()->setTitle('MasterTöB-Liste');
        $this->spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * @param string[] $headingFields
     */
    private function setHeaderRow(array $headingFields): void
    {
        // Besorge die gültigen Klarnamen der Felder
        $headings = array_map(fn (string $field) => $this->translator->trans($field, [], 'master-toeb-list'), $headingFields);

        $this->spreadsheet->getActiveSheet()->fromArray($headings);
    }

    /**
     * @param string[] $masterToebList
     */
    private function setData(array $masterToebList): void
    {
        $this->spreadsheet->getActiveSheet()->fromArray($masterToebList, null, 'A2');
    }

    /**
     * @return array<string, Xlsx>
     */
    private function getFileArray(): array
    {
        $fileName = sprintf(
            $this->translator->trans('master.toeb.list.export.file').'-%s.xlsx',
            Carbon::now()->format('Y-m-d')
        );

        return [
            'filename' => $fileName,
            'writer'   => $this->getWriter(),
        ];
    }

    /**
     * @return string[]
     */
    private function getHeadingFields(): array
    {
        return $this->permissions->hasPermission('area_manage_mastertoeblist')
            ? [
                'gatewayGroup',
                'orgaName',
                'departmentName',
                'sign',
                'email',
                'ccEmail',
                'contactPerson',
                'registered',
                'memo',
                'districtHHMitte',
                'districtAltona',
                'districtEimsbuettel',
                'districtHHNord',
                'districtWandsbek',
                'districtBergedorf',
                'districtHarburg',
                'districtBsu',
                'comment',
                'documentRoughAgreement',
                'documentAgreement',
                'documentNotice',
                'documentAssessment',
            ]
            : [
                'orgaName',
                'departmentName',
                'sign',
                'email',
                'ccEmail',
                'contactPerson',
                'registered',
                'memo',
                'districtHHMitte',
                'districtAltona',
                'districtEimsbuettel',
                'districtHHNord',
                'districtWandsbek',
                'districtBergedorf',
                'districtHarburg',
                'districtBsu',
                'documentRoughAgreement',
                'documentAgreement',
                'documentNotice',
                'documentAssessment',
            ];
    }

    /**
     * @param array<int, MasterToeb> $masterToebArray
     * @param array<int, string>     $headingFields
     *
     * @return array<int, string>
     */
    private function getMasterToebList(array $masterToebArray, array $headingFields): array
    {
        // Fülle die Felder der Toeb mit null, wenn sie nicht ausgefüllt sind
        $masterToebList = [];
        foreach ($masterToebArray as $key => $toeb) {
            foreach ($headingFields as $field) {
                $method = 'get'.ucfirst($field);
                if (method_exists($toeb, $method)) {
                    $masterToebList[$key][$field] = $toeb->$method();
                }
            }
        }

        return $masterToebList;
    }
}
