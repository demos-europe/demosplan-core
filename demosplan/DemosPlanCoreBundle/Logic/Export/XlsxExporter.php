<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class XlsxExporter
{
    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->spreadsheet = new Spreadsheet();
        $this->translator = $translator;
    }

    protected function getWriter(): Xlsx
    {
        return new Xlsx($this->spreadsheet);
    }
}
