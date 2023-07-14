<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use EDT\Wrapping\Utilities\PropertyPathProcessor;
use EDT\Wrapping\Utilities\PropertyPathProcessorFactory;
use EDT\Wrapping\Utilities\TypeAccessors\AbstractProcessorConfig;
use Psr\Log\LoggerInterface;

class DplanPropertyPathProcessorFactory extends PropertyPathProcessorFactory
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function createPropertyPathProcessor(AbstractProcessorConfig $processorConfig): PropertyPathProcessor
    {
        return new DplanPropertyPathProcessor($processorConfig, $this->logger);
    }
}
