<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Response;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\String\UnicodeString;

class BinaryFileDownload extends BinaryFileResponse
{
    public function __construct($filePath, $fileName, $deleteFileAfterSend = false)
    {
        parent::__construct($filePath, 200);
        self::trustXSendfileTypeHeader();
        $this->deleteFileAfterSend($deleteFileAfterSend);

        $fileNameFallback = (new UnicodeString($fileName))->ascii()->toString();

        $this->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileName,
            $fileNameFallback
        );
    }
}
