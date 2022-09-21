<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use PhpOffice\PhpWord\Writer\WriterInterface;

/**
 * @method string          getFilename()
 * @method WriterInterface getWriter()
 */
class DocxExportResult extends ValueObject
{
    /** @var string */
    protected $filename;
    /** @var WriterInterface */
    protected $writer;

    public function __construct(string $filename, WriterInterface $writer)
    {
        $this->filename = $filename;
        $this->writer = $writer;
        $this->lock();
    }
}
