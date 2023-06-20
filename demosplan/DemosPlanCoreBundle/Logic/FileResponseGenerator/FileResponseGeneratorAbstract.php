<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator;

use demosplan\DemosPlanCoreBundle\Logic\Procedure\CsvNameService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface FileResponseGeneratorAbstract.
 */
abstract class FileResponseGeneratorAbstract
{
    public CsvNameService $csvNameService;

    public function __construct(CsvNameService $csvNameService)
    {
        $this->csvNameService = $csvNameService;
    }

    protected $supportedTypes = [];

    /**
     * Given an array implementing a file, generates the Response based on the specific
     * file format.
     */
    abstract public function __invoke(array $file): Response;

    /**
     * Check whether the implementation can generate the Response object for the given format.
     */
    public function supports(string $format): bool
    {
        return in_array($format, $this->supportedTypes, true);
    }
}
