<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter;

/**
 * Interface AssessmentTableFileExporterInterface.
 */
interface AssessmentTableFileExporterInterface
{
    /**
     * Generates an array implementing the file for the supported formats.
     */
    public function __invoke(array $parameters): array;

    /**
     * Check whether the implementation can generate the Response object for the given format.
     */
    public function supports(string $format): bool;
}
