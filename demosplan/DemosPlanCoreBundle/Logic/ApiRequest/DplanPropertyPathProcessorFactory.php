<?php

declare(strict_types=1);

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
