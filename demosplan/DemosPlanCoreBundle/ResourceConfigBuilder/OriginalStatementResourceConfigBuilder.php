<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\ResourceConfigBuilder;


use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseStatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitDate
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitName
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $isSubmittedByCitizen
 */
class OriginalStatementResourceConfigBuilder extends BaseStatementResourceConfigBuilder
{

}
