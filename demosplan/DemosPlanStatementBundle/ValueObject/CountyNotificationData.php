<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string|null getOrgaName()
 * @method array       getProcedure()
 * @method array       getFiles()
 * @method PdfFile     getPdfResult()
 */
class CountyNotificationData extends ValueObject
{
    /**
     * @var string|null
     */
    private $orgaName;

    /**
     * @var array<string, mixed>
     */
    private $procedure;

    /**
     * @var array<int,string>
     */
    private $files;

    /**
     * @var PdfFile
     */
    private $pdfResult;

    /**
     * @param array<string, mixed> $procedure
     * @param array<int,string>    $files
     */
    public function __construct(?string $orgaName, array $procedure, array $files, PdfFile $pdfResult)
    {
        $this->orgaName = $orgaName;
        $this->procedure = $procedure;
        $this->files = $files;
        $this->pdfResult = $pdfResult;
        $this->lock();
    }
}
