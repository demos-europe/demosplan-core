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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseStatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GenericStatementAttachmentResourceType;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<Statement> $submitterEmailAddress @deprecated Move into StatementSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $authoredDate
 * @property-read AttributeConfigBuilderInterface<Statement> $elementCategory @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $filteredFragmentsCount @deprecated Neither attribute nor relationship. Belongs into API meta response.
 * @property-read AttributeConfigBuilderInterface<Statement> $formerExternId @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read AttributeConfigBuilderInterface<Statement> $fragmentsCount @deprecated Create a {@link StatementFragment} relationship instead
 * @property-read AttributeConfigBuilderInterface<Statement> $segmentsCount
 * @property-read AttributeConfigBuilderInterface<Statement> $isCitizen
 * @property-read AttributeConfigBuilderInterface<Statement> $isCluster @deprecated Cluster statements should get a separate resource type instead, which allows this attribute to be removed
 * @property-read AttributeConfigBuilderInterface<Statement> $likesNum @deprecated Use relationship to {@link StatementLike} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $movedFromProcedureId @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read AttributeConfigBuilderInterface<Statement> $movedFromProcedureName @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read AttributeConfigBuilderInterface<Statement> $movedStatementId @deprecated Use {@link StatementResourceType::movedStatement} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $movedToProcedureId @deprecated See {@link StatementResourceType::$movedStatementId}
 * @property-read AttributeConfigBuilderInterface<Statement> $movedToProcedureName @deprecated See {@link StatementResourceType::$movedStatementId}
 * @property-read AttributeConfigBuilderInterface<Statement> $initialOrganisationHouseNumber @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $initialOrganisationStreet @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $initialOrganisationCity @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $initialOrganisationDepartmentName @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $initialOrganisationName @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $authorFeedback @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $initialOrganisationPostalCode @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $parentId @deprecated Statements in clusters should get a separate resource type where this relationship(!) can be moved into
 * @property-read AttributeConfigBuilderInterface<Statement> $procedureId @deprecated Use relationship instead
 * @property-read AttributeConfigBuilderInterface<Statement> $publicAllowed @deprecated Use {@link StatementResourceType::$publicVerified} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $publicVerifiedTranslation
 * @property-read AttributeConfigBuilderInterface<Statement> $recommendationIsTruncated
 * @property-read AttributeConfigBuilderInterface<Statement> $submitDate @deprecated Move into StatementSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $textIsTruncated
 * @property-read AttributeConfigBuilderInterface<Statement> $userGroup @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read AttributeConfigBuilderInterface<Statement> $userOrganisation @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read AttributeConfigBuilderInterface<Statement> $userPosition @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read AttributeConfigBuilderInterface<Statement> $userState @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read AttributeConfigBuilderInterface<Statement> $votesNum @deprecated Use relationship to {@link StatementVote} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $fullText
 * @property-read AttributeConfigBuilderInterface<Statement> $isSubmittedByCitizen
 * @property-read AttributeConfigBuilderInterface<Statement> $isManual
 * @property-read ToManyRelationshipConfigBuilderInterface<Statement, Segment> $segments
 * @property-read ToOneRelationshipConfigBuilderInterface<Statement, Elements> $elements
 * @property-read ToManyRelationshipConfigBuilderInterface<Statement, Elements> $paragraphOriginal
 * @property-read ToManyRelationshipConfigBuilderInterface<Statement, StatementFragment> $fragmentsElements @deprecated Create a {@link StatementFragment} relationship instead
 * @property-read AttributeConfigBuilderInterface<Statement> $submitterPostalCode
 * @property-read AttributeConfigBuilderInterface<Statement> $submitterCity
 * @property-read AttributeConfigBuilderInterface<Statement> $submitterStreet
 * @property-read AttributeConfigBuilderInterface<Statement> $submitterHouseNumber
 * @property-read AttributeConfigBuilderInterface<Statement> $initialOrganisationEmail @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<Statement> $submitterName
 *
 * Cluster statement properties
 * @property-read AttributeConfigBuilderInterface<Statement> $documentParentId @deprecated Use {@link StatementResourceType::$document} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $documentTitle @deprecated Use a relationship to {@link SingleDocumentVersion} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $elementId @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $elementTitle @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $originalId @deprecated Use a relationship instead
 * @property-read AttributeConfigBuilderInterface<Statement> $paragraphParentId @deprecated Use {@link StatementResourceType::$paragraph} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $paragraphTitle @deprecated Use {@link StatementResourceType::$paragraph} instead
 * @property-read AttributeConfigBuilderInterface<Statement> $authorName
 * @property-read AttributeConfigBuilderInterface<Statement> $submitName
 *
 * Head statement properties
 * @property-read ToManyRelationshipConfigBuilderInterface<Statement, Elements> $statements
 *
 * Ordinary statement properties
 * @property-read AttributeConfigBuilderInterface<Statement> $segmentDraftList
 * @property-read AttributeConfigBuilderInterface<Statement> $availableInternalPhases
 * @property-read AttributeConfigBuilderInterface<Statement> $availableExternalPhases
 * @property-read AttributeConfigBuilderInterface<Statement> $location
 * @property-read ToOneRelationshipConfigBuilderInterface<Statement, ParagraphVersion> $paragraphVersion
 * @property-read AttributeConfigBuilderInterface<Statement> $procedurePhase
 * @property-read AttributeConfigBuilderInterface<Statement> $availableProcedurePhases
 *
 * Statement Attachments properties
 * @property-read ToManyRelationshipConfigBuilderInterface<StatementInterface,GenericStatementAttachmentResourceType> $genericAttachments
 * An Statement has only one source attachment, that is why the property is named singular even though it is a to-many relationship
 * @property-read ToManyRelationshipConfigBuilderInterface<StatementInterface,StatementAttachmentInterface> $sourceAttachment
 * @property-read ToOneRelationshipConfigBuilderInterface<Statement, Statement> $parentStatementOfSegment
 */
class StatementResourceConfigBuilder extends BaseStatementResourceConfigBuilder
{
}
