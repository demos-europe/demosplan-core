<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use EDT\Wrapping\Utilities\PropertyPathProcessor;
use EDT\Wrapping\Utilities\PropertyPathProcessorFactory;
use EDT\Wrapping\Utilities\TypeAccessors\AbstractTypeAccessor;
use Psr\Log\LoggerInterface;

class DplanPropertyPathProcessorFactory extends PropertyPathProcessorFactory
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function createPropertyPathProcessor(AbstractTypeAccessor $typeAccessor): PropertyPathProcessor
    {
        return new DplanPropertyPathProcessor($typeAccessor, $this->logger);
    }
}
