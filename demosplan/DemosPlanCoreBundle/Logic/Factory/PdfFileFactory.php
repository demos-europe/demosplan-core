<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use demosplan\DemosPlanCoreBundle\ValueObject\Statement\PdfFile;
use DemosEurope\DemosplanAddon\Contracts\Factory\PdfFileFactoryInterface;
use DemosEurope\DemosplanAddon\Contracts\ValueObject\PdfFileInterface;

class PdfFileFactory implements PdfFileFactoryInterface
{
    public function createPdfFile(): PdfFileInterface
    {
        return new PdfFile();
    }
}
