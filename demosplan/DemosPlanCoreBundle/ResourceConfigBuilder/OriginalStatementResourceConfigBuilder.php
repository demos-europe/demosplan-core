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

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseStatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $fullText
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $shortText
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitDate
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $isSubmittedByCitizen
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $attachmentsDeleted
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitterAndAuthorMetaDataAnonymized
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $textPassagesAnonymized
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $textIsTruncated
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement, File> $files @deprecated Use {@link StatementResourceType::$attachments} instead (needs implementation changes)
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement, Elements> $elements
 */
class OriginalStatementResourceConfigBuilder extends BaseStatementResourceConfigBuilder
{
}
