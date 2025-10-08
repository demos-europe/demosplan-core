<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceConfigBuilder;

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseNewsResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<News> $pictureTitle
 * @property-read AttributeConfigBuilderInterface<News> $pdfTitle
 * @property-read ToOneRelationshipConfigBuilderInterface<News,File> $picture
 * @property-read ToOneRelationshipConfigBuilderInterface<News,Procedure> $procedure
 * @property-read ToOneRelationshipConfigBuilderInterface<News,File> $pdf
 */
class NewsResourceConfigBuilder extends BaseNewsResourceConfigBuilder
{
}
