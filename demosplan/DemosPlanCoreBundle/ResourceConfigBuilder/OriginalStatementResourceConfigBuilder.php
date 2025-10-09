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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseStatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<Statement> $fullText
 * @property-read AttributeConfigBuilderInterface<Statement> $shortText
 * @property-read AttributeConfigBuilderInterface<Statement> $submitDate
 * @property-read AttributeConfigBuilderInterface<Statement> $isSubmittedByCitizen
 * @property-read AttributeConfigBuilderInterface<Statement> $attachmentsDeleted
 * @property-read AttributeConfigBuilderInterface<Statement> $submitterAndAuthorMetaDataAnonymized
 * @property-read AttributeConfigBuilderInterface<Statement> $textPassagesAnonymized
 * @property-read AttributeConfigBuilderInterface<Statement> $textIsTruncated
 * @property-read AttributeConfigBuilderInterface<Statement> $procedurePhase
 *
 * An Statement has only one source attachment, that is why the property is named singular even though it is a to-many relationship
 * @property-read ToManyRelationshipConfigBuilderInterface<StatementInterface,StatementAttachment> $sourceAttachment
 * @property-read ToManyRelationshipConfigBuilderInterface<StatementInterface,FileContainer> $genericAttachments
 * @property-read ToOneRelationshipConfigBuilderInterface<Statement, Elements> $elements
 * @property-read ToOneRelationshipConfigBuilderInterface<Statement, Statement> $parentStatementOfSegment
 */
class OriginalStatementResourceConfigBuilder extends BaseStatementResourceConfigBuilder
{
}
