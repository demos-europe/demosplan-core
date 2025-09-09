<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

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
     * @param array<string, mixed> $procedure
     * @param array<int, string>   $files
     */
    public function __construct(
        protected ?string $orgaName,
        protected array $procedure,
        protected array $files,
        protected PdfFile $pdfResult
    ) {
        $this->lock();
    }
}
