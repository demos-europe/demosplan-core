<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

class SimpleSpreadsheetService
{
    /**
     * @param string $title
     *
     * @return Spreadsheet
     *
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function createExcelDocument($title = 'untitled'): Spreadsheet
    {
        $phpExcel = new Spreadsheet();

        $phpExcel
            ->getProperties()
            ->setCreator('demosplan')
            ->setLastModifiedBy('demosplan')
            ->setTitle($title);

        return $phpExcel;
    }

    /**
     * Add worksheet to given phpExcel including the given data.
     *
     * If the sheet count ofte passed document equals 1, no new sheet will be created.
     * This can be overridden with the `$forceNewSheet` flag.
     *
     * This method will also set the active sheet index to the newly created worksheet
     * which means that exported excel documents will open with that sheet by default.
     *
     * @param string $sheetTitle    - title of the only worksheet wich will be created
     * @param bool   $wrapText      - determines if text will wrap at the end of column width
     * @param bool   $forceNewSheet - if this is true, a new sheet will also be created if there's only one sheet in the document
     *
     * @return spreadsheet - created Table with given content and formatted header
     *
     * @internal param $ [] $columnTitles - The content to set to the first row. Will be the header of the table.
     * @internal param $ [] $data - The actually content. Will be the "body" of the table.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function addWorksheet(Spreadsheet $phpExcel,
                                 array $formattedData = [],
                                 array $columnTitles = [],
                                 $sheetTitle = 'untitled',
                                 $wrapText = true,
                                 $forceNewSheet = false
    ) {
        // if there's only one worksheet in the document, we assume that it's the first one and just return it
        if (1 === $phpExcel->getSheetCount() && !$forceNewSheet) {
            $worksheetIndex = 0;
            $worksheet = $phpExcel->getActiveSheet();
        } else {
            $worksheetIndex = $phpExcel->getSheetCount() + 1;
            $worksheet = $phpExcel->createSheet();
        }

        $worksheet->setTitle($sheetTitle);
        // set given columnTitles to fist column
        $worksheet->fromArray($columnTitles, null, 'A1');

        // get last filled column (end of table):
        $lastColumn = $worksheet->getHighestColumn();
        // get alignment from first to last column
        $globalAlignment = $worksheet->getStyle('A:'.$lastColumn)->getAlignment();
        $globalAlignment->setHorizontal('left');
        $globalAlignment->setVertical('top');
        $globalAlignment->setWrapText($wrapText);

        // /set font of first column to bold
        $worksheet->getStyle('A1:'.$lastColumn.'1')
            ->getFont()
            ->setBold(true);

        // set font color of first column to white
        $worksheet->getStyle('A1:'.$lastColumn.'1')
            ->getFont()
            ->getColor()
            ->setRGB('FFFFFF');

        // set background color of first column to grey
        $worksheet->getStyle('A1:'.$lastColumn.'1')
            ->getFill()
            ->setFillType('solid')
            ->getStartColor()
            ->setRGB('A0A0A0');

        if ($wrapText) {
            // set fixed columnDimensions to trigger wrapping of text
            $dimensions = $worksheet->getColumnDimensions();
            foreach ($dimensions as $dimension) {
                $dimension->setWidth(50);
            }
        }

        // T9490: decode because text was stored encoded in DB:
        foreach ($formattedData as $key => $dataSet) {
            if (array_key_exists('recommendation', $formattedData[$key])) {
                $formattedData[$key]['recommendation'] = htmlspecialchars_decode((string) $dataSet['recommendation']);
            }
        }

        $worksheet->fromArray($formattedData, null, 'A2');
        $phpExcel->setActiveSheetIndex($worksheetIndex);

        return $phpExcel;
    }

    /**
     * @throws Exception
     */
    public function getExcel2007Writer(Spreadsheet $document): IWriter
    {
        return IOFactory::createWriter($document, 'Xlsx');
    }
}
