<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter;

use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use Psr\Log\LoggerInterface;

class AssessmentTableExporterStrategy
{
    /** @var iterable<AssessmentTableFileExporterInterface> */
    private $exporters;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param iterable<AssessmentTableFileExporterInterface> $exporters
     */
    public function __construct(iterable $exporters, LoggerInterface $loggerInterface)
    {
        $this->exporters = $exporters;
        $this->logger = $loggerInterface;
    }

    /**
     * @throws DemosException
     */
    public function export(string $format, array $parameters): array
    {
        /** @var AssessmentTableFileExporterAbstract $exporter */
        foreach ($this->exporters as $exporter) {
            if ($exporter->supports($format)) {
                return $exporter($parameters);
            }
        }

        $this->logger->error("Export format $format not supported");
        throw new DemosException('error.generic');
    }
}
