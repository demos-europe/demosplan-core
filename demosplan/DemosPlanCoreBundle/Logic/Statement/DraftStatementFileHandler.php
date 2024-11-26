<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementFileRepository;

class DraftStatementFileHandler
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly DraftStatementFileRepository $draftStatementFileRepository)
    {
    }

    public function getDraftStatementRelatedToThisFile(string $fileId): array
    {
        $file = $this->fileService->getFileById($fileId);
        if (null === $file) {
            return [];
        }

        return $this->draftStatementFileRepository->getDraftStatementFilesByFile($file);
    }
}
