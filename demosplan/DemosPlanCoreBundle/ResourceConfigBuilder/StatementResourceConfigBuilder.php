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
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GenericStatementAttachmentResourceType;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitterEmailAddress @deprecated Move into StatementSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $authoredDate
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $elementCategory @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $filteredFragmentsCount @deprecated Neither attribute nor relationship. Belongs into API meta response.
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $formerExternId @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $fragmentsCount @deprecated Create a {@link StatementFragment} relationship instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $isCitizen
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $isCluster @deprecated Cluster statements should get a separate resource type instead, which allows this attribute to be removed
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $likesNum @deprecated Use relationship to {@link StatementLike} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $movedFromProcedureId @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $movedFromProcedureName @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $movedStatementId @deprecated Use {@link StatementResourceType::movedStatement} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $movedToProcedureId @deprecated See {@link StatementResourceType::$movedStatementId}
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $movedToProcedureName @deprecated See {@link StatementResourceType::$movedStatementId}
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $initialOrganisationHouseNumber @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $initialOrganisationStreet @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $initialOrganisationCity @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $initialOrganisationDepartmentName @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $initialOrganisationName @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $initialOrganisationPostalCode @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $parentId @deprecated Statements in clusters should get a separate resource type where this relationship(!) can be moved into
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $procedureId @deprecated Use relationship instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $publicAllowed @deprecated Use {@link StatementResourceType::$publicVerified} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $publicVerifiedTranslation
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $recommendationIsTruncated
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitDate @deprecated Move into StatementSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $textIsTruncated
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $userGroup @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $userOrganisation @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $userPosition @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $userState @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $votesNum @deprecated Use relationship to {@link StatementVote} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $fullText
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $isSubmittedByCitizen
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $isManual
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement, Segment> $segments
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement, Elements> $elements
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement, Elements> $paragraphOriginal
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement, StatementFragment> $fragmentsElements @deprecated Create a {@link StatementFragment} relationship instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitterPostalCode
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitterCity
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitterStreet
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitterHouseNumber
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $initialOrganisationEmail @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitterName
 *
 * Cluster statement properties
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $documentParentId @deprecated Use {@link StatementResourceType::$document} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $documentTitle @deprecated Use a relationship to {@link SingleDocumentVersion} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $elementId @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $elementTitle @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $originalId @deprecated Use a relationship instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $paragraphParentId @deprecated Use {@link StatementResourceType::$paragraph} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $paragraphTitle @deprecated Use {@link StatementResourceType::$paragraph} instead
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $authorName
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitName
 *
 * Head statement properties
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement, Elements> $statements
 *
 * Ordinary statement properties
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $segmentDraftList
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,Statement> $availableInternalPhases
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,Statement> $availableExternalPhases
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,Statement> $location
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement, ParagraphVersion> $paragraphVersion
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,Statement> $procedurePhase
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,Statement> $availableProcedurePhases
 *
 * Statement Attachments properties
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,StatementInterface,GenericStatementAttachmentResourceType> $genericAttachments
 * An Statement has only one source attachment, that is why the property is named singular even though it is a to-many relationship
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,StatementInterface,StatementAttachment> $sourceAttachment
 */
class StatementResourceConfigBuilder extends BaseStatementResourceConfigBuilder
{
}
