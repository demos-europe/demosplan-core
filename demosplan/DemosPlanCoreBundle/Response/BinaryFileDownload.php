<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Response;

use Patchwork\Utf8;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class BinaryFileDownload extends BinaryFileResponse
{
    protected $deleteFileAfterSend = false;

    public function __construct($filePath, $fileName)
    {
        parent::__construct($filePath, 200);
        self::trustXSendfileTypeHeader();
        $this->deleteFileAfterSend(true);

        if (class_exists(Utf8::class)) {
            $fileNameFallback = Utf8::toAscii($fileName);
        } else {
            $fileNameFallback = iconv('UTF-8', 'ASCII//TRANSLIT', $fileName);
        }

        $this->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileName,
            $fileNameFallback
        );
    }
}
