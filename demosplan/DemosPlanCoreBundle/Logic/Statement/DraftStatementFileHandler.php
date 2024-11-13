<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementFileRepository;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementRepository;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;

class DraftStatementFileHandler
{

    public function __construct(
        private readonly FileService $fileService,
       private readonly DraftStatementFileRepository $draftStatementFileRepository)
    {
    }


    public function getDraftStatementRelatedToThisFile(string $fileString): array

    {
        $file = $this->fileService->getFileFromFileString($fileString);
       return $this->draftStatementFileRepository->getDraftStatementFilesByFile($file);
    }

}
