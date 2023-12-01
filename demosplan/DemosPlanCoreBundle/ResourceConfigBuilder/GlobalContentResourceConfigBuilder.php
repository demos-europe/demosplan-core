<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\ResourceConfigBuilder;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseGlobalContentResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,GlobalContent> $pictureTitle
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,GlobalContent> $pdfTitle
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,GlobalContent,File> $picture
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,GlobalContent,File> $pdf
 */
class GlobalContentResourceConfigBuilder extends BaseGlobalContentResourceConfigBuilder
{

}
