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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @template-extends MagicResourceConfigBuilder<ClauseFunctionInterface<bool>, TextSection>
 *
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, TextSection> $orderInStatement
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, TextSection> $textRaw
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, TextSection> $text
 * @property-read ToOneRelationshipConfigBuilderInterface<TextSection, Statement> $statement
 */
class TextSectionResourceConfigBuilder extends MagicResourceConfigBuilder
{
    protected function getEntityClass(): string
    {
        return TextSection::class;
    }
}
