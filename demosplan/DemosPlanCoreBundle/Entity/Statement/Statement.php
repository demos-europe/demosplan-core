<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\CountyInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\DraftStatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\GdprConsentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\MunicipalityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OriginalStatementAnonymizationInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ParagraphVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\PriorityAreaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePersonInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFragmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementMetaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementVersionFieldInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementVoteInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\ClaimConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\ConsistentAnonymousOrgaConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\CorrectDateOrderConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\FormDefinitionConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\MatchingSubmitTypesConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\OriginalReferenceConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\PrePersistUniqueInternIdConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\SimilarStatementSubmittersSameProcedureConstraint;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\OriginalStatementAnonymization;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\DoctrineStatementListener;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Services\HTMLFragmentSlicer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use UnexpectedValueException;

/**
 * @ORM\Table(name="_statement", uniqueConstraints={@ORM\UniqueConstraint(name="internId_procedure", columns={"_st_intern_id", "_p_id"})})
 *
 * @ORM\InheritanceType("SINGLE_TABLE")
 *
 * @ORM\DiscriminatorColumn(name="entity_type", type="string")
 *
 * @ORM\DiscriminatorMap({"Statement"="Statement", "Segment" = "demosplan\DemosPlanCoreBundle\Entity\Statement\Segment"})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementRepository")
 *
 * @ClaimConstraint()
 *
 * @CorrectDateOrderConstraint(groups={StatementInterface::IMPORT_VALIDATION})
 *
 * @ConsistentAnonymousOrgaConstraint(groups={StatementInterface::IMPORT_VALIDATION})
 *
 * @FormDefinitionConstraint()
 *
 * @MatchingSubmitTypesConstraint(groups={StatementInterface::IMPORT_VALIDATION})
 *
 * @OriginalReferenceConstraint()
 *
 * @PrePersistUniqueInternIdConstraint(groups={StatementInterface::IMPORT_VALIDATION})
 *
 * @SimilarStatementSubmittersSameProcedureConstraint(groups={"Default", "manual_create"})
 */
class Statement extends CoreEntity implements UuidEntityInterface, StatementInterface
{
    /**
     * @var string|null
     *                  Generates a UUID in code that confirms to https://www.w3.org/TR/1999/REC-xml-names-19990114/#NT-NCName
     *                  to be able to be used as xs:ID type in XML messages
     *
     * @ORM\Column(name="_st_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\NCNameGenerator")
     */
    protected $id;

    /**
     * This property is used inside this base Statement class only to be able to
     * build conditions for resource types. e.g. to filter out segments.
     *
     * @var StatementInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="segmentsOfStatement", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="segment_statement_fk", referencedColumnName="_st_id", nullable=true)
     */
    #[Assert\IsNull()]
    protected $parentStatementOfSegment;

    /**
     * Elternstellungnahme, von der diese kopiert wurde.
     *
     * @var Statement
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="children")
     *
     * @ORM\JoinColumn(name="_st_p_id", referencedColumnName="_st_id", onDelete="SET NULL")
     */
    protected $parent;

    /**
     * Id der Elternstellungnahme, von der diese kopiert wurde.
     *
     * @var string|null
     */
    protected $parentId;

    /**
     * Children.
     *
     * do not delete cascade children in case of delete this one (parent), because children can be existing without parent (copies)
     *
     * @var Collection<int, Statement>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", mappedBy="parent")
     */
    protected $children;

    /**
     * @var Statement
     *
     * Cascade persist, original STN in case of opposite will be deleted, the original stn will not longer expect a related STN.
     * Needed (WIP) for delete statements on delete procedure.
     *
     * On update this one, the associated originalSTN will be also persisted. Needed in StatementCopier::copyStatementToProcedure()
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", cascade={"persist"}, inversedBy="statementsCreatedFromOriginal")
     *
     * @ORM\JoinColumn(name="_st_o_id", referencedColumnName="_st_id")
     */
    protected $original;

    /**
     * If this instance is an original statement, then this list will contain the statement
     * instances created from this original statement. If this instance is not an original
     * statement, then this list should be empty.
     *
     * @var Collection<int, Statement>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", mappedBy="original")
     */
    protected $statementsCreatedFromOriginal;

    /**
     * Id der Originalstellungnahme.
     *
     * @var string|null
     */
    protected $originalId;

    /**
     * @var string
     *
     * @ORM\Column(name="_st_priority", type="string", length=10, nullable=false, options={"fixed":true})
     */
    protected $priority = '';

    /**
     * Automatically generated ID shown to the planners and provided to the submitter (eg. when
     * submitting in the UI and as Email).
     *
     * @var string
     *
     * @ORM\Column(name="_st_extern_id", type="string", length=25, nullable=false, options={"fixed":true})
     */
    protected $externId = '';

    /**
     * Beside the {@link StatementInterface::$externId} in manual statements a separate (intern) ID can be set manually.
     *
     * If it was not set the value remains `null`. It is necessary to use `null` instead of
     * an empty string in this case, because a set intern ID must be unique and
     * while multiple `null`s are considered different, multiple empty strings are not.
     *
     * @var string|null
     *
     * @ORM\Column(name="_st_intern_id", type="string", length=255, nullable=true, options={"fixed":true, "comment":"manuelle Eingangsnummer"})
     */
    #[Assert\Length(max: 255)]
    protected $internId;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", nullable=true, onDelete="RESTRICT")
     */
    protected $user;

    /**
     * Virtuelle Eigenschaft der UserId.
     *
     * @var string
     */
    protected $uId;

    /**
     * Virtuelle Eigenschaft des UserName.
     */
    protected ?string $uName = null;

    /**
     * @var Orga|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", nullable=true, onDelete="RESTRICT")
     */
    #[Assert\Valid(groups: [Statement::IMPORT_VALIDATION])]
    protected $organisation;

    /**
     * Virtuelle Eigenschaft der OrgansiationId.
     *
     * @var string|null
     */
    protected $oId;

    /**
     * Virtuelle Eigenschaft des OrgansiationNames. Hilft bei der Filterung und Sortierung.
     *
     * @var string
     */
    protected $oName;

    /**
     * Virtuelle Eigenschaft des DepartmentNames. Hilft bei der Filterung und Sortierung.
     *
     * @var string
     */
    protected $dName;

    /**
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", cascade={"persist"}, inversedBy="statements")
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var string
     */
    protected $pId;

    /**
     * "Eingereicht im Namen von".
     *
     * @var string
     *
     * @ORM\Column(name="_st_represents", type="string", length=256, nullable=true, options={"default":""})
     */
    protected $represents = '';

    /**
     * Rechtmäßigkeit der Vertretung überprüft.
     *
     * @var bool
     *
     * @ORM\Column(name="_st_representation_check", type="boolean", nullable=true, options={"default":false})
     */
    protected $representationCheck = false;

    /**
     * Must have one of a set of predefined values which differs in projects, see respective configuration file.
     *
     * @var string
     *
     * @ORM\Column(name="_st_phase", type="string", length=50, nullable=false)
     */
    protected $phase;

    /**
     * @var string
     *
     * @ORM\Column(name="_st_status", type="string", length=50, nullable=false, options={"fixed":true})
     */
    protected $status = 'new';

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_st_created_date", type="datetime", nullable=false)
     */
    protected $created;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_st_modified_date", type="datetime", nullable=false)
     */
    protected $modified;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_st_send_date", type="datetime", nullable=false)
     */
    protected $send;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_st_sent_assessment_date", type="datetime", nullable=false)
     */
    protected $sentAssessmentDate;

    /**
     * @var DateTime *
     *
     * @ORM\Column(name="_st_submit_date", type="datetime", nullable=false)
     */
    #[Assert\NotBlank(groups: [Statement::IMPORT_VALIDATION], message: 'statement.import.invalidSubmitDateBlank')]
    #[Assert\Type('DateTime', groups: [Statement::IMPORT_VALIDATION], message: 'statement.import.invalidSubmitDateType')]
    protected $submit;

    /**
     * @var DateTime *
     *
     * @ORM\Column(name="_st_deleted_date", type="datetime", nullable=false)
     */
    protected $deletedDate;

    /**
     * @var bool
     *
     * @ORM\Column(name="_st_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_st_negativ_statement", type="boolean", nullable=false, options={"default":false})
     */
    protected $negativeStatement = 0;

    /**
     * @var bool
     *
     * @ORM\Column(name="_st_sent_assessment", type="boolean", nullable=false, options={"default":false})
     */
    protected $sentAssessment = 0;

    /**
     * @var bool
     *
     * did the Author wants to be anonymised?
     * Not used - use $this->anonymous instead
     *
     * @ORM\Column(name="_st_public_use_name", type="boolean", nullable=false, options={"default":false})
     */
    protected $publicUseName = false;

    /**
     * @see: https://yaits.demos-deutschland.de/T15936
     *
     * See $this->publicVerifiedMapping for source of truth.
     *
     * @var string
     *
     * @ORM\Column(name="_st_public_verified", type="string", length=30, nullable=false)
     */
    protected $publicVerified;

    /**
     * @var Collection<int, OriginalStatementAnonymization>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\OriginalStatementAnonymization", mappedBy="statement")
     */
    protected $anonymizations;

    /**
     * Defines the allowed values of $this->publicVerified and maps them with the respective translations.
     *
     * The process varies in different projects, but the basis is always the same:
     * - First, the author is asked for permission/will to publish.
     * - Then, the planner decides on publication status.
     * However, this can result in several different statuses.
     *
     * @var array
     */
    public static $publicVerifiedMapping = [
        // We use this value if the permissions field_statement_public_allowed is not enabled. From a logic
        // perspective, this means that the author was not asked for permission.
        StatementInterface::PUBLICATION_NO_CHECK_SINCE_PERMISSION_DISABLED => 'public.permission.disabled',
        // If the author decides not to allow publication to the public - meaning (other) citizens -, then the value is
        // always set to 'no_check_since_not_allowed'.
        // By convention, this value is also the default in invalid cases, e.g. when creating head statements, which
        // should never but which need to have a value.
        StatementInterface::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED         => 'no',
        // If the author wants to allow publication, then one of the following values is set:
        // 'publication_pending' is the default, meaning that the FPA needs to check if publication is ok
        StatementInterface::PUBLICATION_PENDING                            => 'publication.pending',
        // Once the check has occurred, the value may either be 'publication_rejected' or 'publication_approved'.
        // The user may not change this once it is set. Hence, the rejection implies that an actual check by the planner
        // has taken place, it should never be used as a default.
        StatementInterface::PUBLICATION_REJECTED                           => 'publication.rejected',
        StatementInterface::PUBLICATION_APPROVED                           => 'publication.approved',
    ];

    /**
     * @var string
     *
     * @ORM\Column(name="_st_public_statement", type="string", length=20, nullable=false)
     */
    protected $publicStatement = StatementInterface::INTERNAL;

    /**
     * @var bool
     *
     * @ORM\Column(name="_st_to_send_per_mail", type="boolean", nullable=false, options={"default":false})
     */
    protected $toSendPerMail = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_st_title", type="string", length=4096, nullable=false)
     */
    protected $title = '';

    /**
     * Type: TipTap-Editor String
     * Allowed values: May not be empty (https://demosdeutschland.slack.com/archives/C03AD7Z2Y/p1576674603017800).
     *
     * @var string
     *
     * @ORM\Column(name="_st_text", type="text", nullable=false, length=15000000)
     */
    protected $text = '';

    /**
     * Sliced Version of StatementText for better Performance.
     *
     * @var string
     */
    protected $textShort = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_st_recommendation", type="text", nullable=false, length=15000000)
     */
    protected $recommendation = '';

    /**
     * Sliced Version of StatementRecommendation for better Performance.
     *
     * @var string
     */
    protected $recommendationShort = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_st_memo", type="text", length=65535, nullable=false)
     */
    protected $memo = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_st_feedback", type="string", length=10, nullable=false)
     */
    protected $feedback = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_st_reason_paragraph", type="text", length=65535, nullable=false)
     */
    protected $reasonParagraph = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_st_planning_document", type="string", length=4096, nullable=false)
     */
    protected $planningDocument = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_st_file", type="string", length=255, nullable=false, options={"fixed":true}))
     */
    protected $file = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_st_map_file", type="string", length=255, nullable=true, options={"fixed":true})
     */
    protected $mapFile = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_st_county_notified", type="boolean", nullable=false, options={"default":false})
     */
    protected $countyNotified = false;

    /**
     * @var ParagraphVersion
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="_st_paragraph_id", referencedColumnName="_pdv_id", onDelete="SET NULL")
     */
    protected $paragraph;

    /**
     * Virtuelle Eigenschaft der ParagraphId.
     *
     * @var string
     */
    protected $paragraphId;

    /**
     * Virtuelle Eigenschaft der ParagraphTitle.
     *
     * @var string
     */
    protected $paragraphTitle;
    /**
     * Virtuelle Eigenschaft der Order des Paragraphs zur Sortierung der Absätze.
     *
     * @var int
     */
    protected $paragraphOrder;

    /**
     * Virtuelle Eigenschaft der Id des ElternParagraphs zur Sortierung der Absätze.
     *
     * @var string
     */
    protected $paragraphParentId;

    /**
     * Title of the parent paragraph (paragraph of the paragraph version) as virtual property.
     *
     * @var string
     */
    protected $paragraphParentTitle;

    /**
     * Title of the parent document (document of the document version) as virtual property.
     *
     * @var string
     */
    protected $documentParentTitle;

    /**
     * @var SingleDocumentVersion
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="_st_document_id", referencedColumnName="_sdv_id", onDelete="SET NULL")
     */
    protected $document;

    /**
     * Virtuelle Eigenschagft der DocumentId.
     *
     * @var string
     */
    protected $documentId;

    /**
     * Virtuelle Eigenschagft des DocumentTitle.
     *
     * @var string
     */
    protected $documentTitle;

    /**
     * @var string
     */
    protected $documentHash;

    /**
     * Virtuelle Eigenschaft für die ElementId.
     *
     * @var string
     */
    protected $elementId;

    /**
     * Virtuelle Eigenschaft für die ElementTitle.
     *
     * @var string
     */
    protected $elementTitle;

    /**
     * @var Elements
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Document\Elements", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="_st_element_id", referencedColumnName="_e_id", onDelete="SET NULL")
     *
     **/
    protected $element;

    /**
     * @var string
     *
     * @ORM\Column(name="_st_polygon", type="text", length=65535, nullable=false)
     */
    protected $polygon = '';

    /**
     * @var DraftStatement
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement")
     *
     * @ORM\JoinColumn(name="_ds_id", referencedColumnName="_ds_id", onDelete="SET NULL")
     */
    protected $draftStatement;

    /**
     * Virtuelle Eigenschaft der DraftStatementId.
     *
     * @var string
     */
    protected $draftStatementId;

    /**
     * @var StatementMeta
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta", mappedBy="statement", cascade={"persist", "remove"})
     */
    #[Assert\Valid(groups: [Statement::IMPORT_VALIDATION])]
    protected $meta;

    /**
     * @var StatementVersionField
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVersionField", mappedBy="statement")
     *
     * @ORM\OrderBy({"created" = "DESC"})
     */
    protected $version;

    /**
     * @var StatementAttribute[]
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute", mappedBy="statement", cascade={"remove"})
     */
    protected $statementAttributes;

    /**
     * @var Collection<int,StatementVote>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote", mappedBy="statement", cascade={"persist", "refresh"})
     */
    protected $votes;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true, "default":0})
     */
    protected $numberOfAnonymVotes = 0;

    /**
     * @var StatementLike[]
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementLike", mappedBy="statement")
     */
    protected $likes;

    /**
     * @var Collection
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Tag", inversedBy="statements", cascade={"persist", "refresh"})
     *
     * @ORM\JoinTable(
     *     name="_statement_tag",
     *     joinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_t_id", referencedColumnName="_t_id", onDelete="CASCADE")}
     * )
     */
    protected $tags;

    /**
     * @var Collection<int, County>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\County", inversedBy="statements")
     *
     * @ORM\JoinTable(
     *     name="_statement_county",
     *     joinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id", onDelete="cascade")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_c_id", referencedColumnName="_c_id", onDelete="cascade")}
     * )
     */
    protected $counties;

    /**
     * @var Collection<int, PriorityArea>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea", inversedBy="statements")
     *
     * @ORM\JoinTable(
     *     name="_statement_priority_area",
     *     joinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id", onDelete="cascade")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_pa_id", referencedColumnName="_pa_id", onDelete="cascade")}
     * )
     */
    protected $priorityAreas;

    /**
     * @var Collection<int, Municipality>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality", inversedBy="statements")
     *
     * @ORM\JoinTable(
     *     name="_statement_municipality",
     *     joinColumns={@ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id", onDelete="cascade")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_m_id", referencedColumnName="_m_id", onDelete="cascade")}
     * )
     */
    protected $municipalities;

    /**
     * @var Collection<int, StatementFragment>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment", mappedBy="statement", cascade={"remove"})
     *
     * @ORM\OrderBy({"sortIndex" = "ASC"})
     */
    protected $fragments;

    /**
     * @var int|null Number of fragments that match a given filter
     */
    protected $fragmentsFilteredCount;

    /**
     * Votum der Statskanzlei.
     * Concrete vote of this statement.
     *
     * @var string
     *
     * @ORM\Column(name="_st_vote_stk", type="string", length=16, nullable=true, options={"fixed":true})
     */
    protected $voteStk;

    /**
     * Votum (Empfehlung) des Planungsbüros
     * Kind of vote advice.
     *
     * @var string
     *
     * @ORM\Column(name="_st_vote_pla", type="string", length=16, nullable=true, options={"fixed":true})
     */
    protected $votePla;

    /**
     * Every original Statement has informations regarding the GDPR consent even if it was deleted or created
     * manually. The GdprConsent holds the information about given or revoked consents and is removed if the
     * statement is removed. For original Statements the GdprConsent is created when the Statement is created
     * (or in one migration for all old original Statements). This is needed to be able to revoke consent without
     * additional create-if-null logic. However for non-original Statements this is null, as they will use the
     * GdprConsent of their original Statement.
     *
     * We use persist cascade to automatically persist new GdprConsent instances attached to this statement that
     * are not yet in the database.
     *
     * Remove related GDPRConstent in case of this Statement will be deleted.
     * These is the inversed site
     *
     * @var GdprConsent|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent", mappedBy="statement", cascade={"persist", "remove"})
     */
    protected $gdprConsent;

    /**
     * @var string
     *
     * @ORM\Column(name="_st_submit_type", type="string", nullable=false)
     */
    #[Assert\NotBlank(groups: [Statement::IMPORT_VALIDATION], message: 'statement.import.invalidSubmitTypeBlank')]
    #[Assert\Choice(
        choices: StatementInterface::SUBMIT_TYPES,
        message: 'statement.invalid.submit.type',
        groups: ['Default', StatementInterface::IMPORT_VALIDATION]
    )]
    protected $submitType = StatementInterface::SUBMIT_TYPE_SYSTEM;

    /**
     * This field is transformed during elasticsearch populate
     * Getter gets translated value in Doctrine Listener.
     *
     * @see ElasticsearchStatementFieldTranslateListener
     * @see DoctrineStatementListener
     *
     * @var string
     */
    protected $submitTypeTranslated = '';

    /**
     * @var array<int,string>
     *                        No doctrine connection because of multiple inheritance. Real inheritance mapping as described in
     *                        http://doctrine-orm.readthedocs.io/en/latest/reference/inheritance-mapping.html
     *                        is not possible atm, because primary keys are named differently across entities
     *                        Files have to be get via Repository
     */
    protected $files;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="assignee", referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
     *
     * This is the user that is currently assigned to this statement. Assigned users are
     * exclusively permitted to change statements
     */
    protected $assignee;

    /**
     * The representative Statement defines the cluster.
     * This is the Statement which will be used instead of each statement in the cluster.
     *
     * This should not be persists automatic, because of checking the assignment in updateStatement()!
     * Doctrinesited persists, would bypass this check!
     *
     * @var Statement
     *
     * This is the owning side
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="cluster")
     *
     * @ORM\JoinColumn(name="head_statement_id", referencedColumnName="_st_id", nullable = true, onDelete="SET NULL")
     */
    protected $headStatement;

    /**
     * @var Collection<int, Statement>
     *
     * This should not be persists automatic, because of checking the assignment in updateStatement()!
     * Doctrine-sited persists, would bypass this check!
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", mappedBy="headStatement", cascade={"merge"})
     *
     * @ORM\OrderBy({"externId" = "ASC"})
     */
    protected $cluster;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable = false, options={"default":false})
     */
    protected $manual = false;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable = false, options={"default":false})
     */
    protected $clusterStatement = false;

    /**
     * Statement to remains in source procedure after moved to target procedure.
     * $placeholderStatement should be unable to delete, to ensure determine this statement as moved statement.
     *
     * cascade={"remove"} means, that the associated placeholder will be deleted, in case of this moved statement will be deleted.
     *
     * @var Statement
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", cascade={"remove"})
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id", nullable=true, onDelete="RESTRICT")
     */
    protected $placeholderStatement;

    /**
     * Statement which was moved into another procedure.
     * $movedStatement will only filled, if this statement is a placeholder statement.
     *
     * onDelete=Cascade means, that this placeholder will be deleted in case of the associated moved Statement will be deleted.
     *
     * @var Statement
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Statement\Statement")
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id", nullable=true)
     */
    protected $movedStatement;

    /**
     * Enable name (cluster-)statements.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $replied = false;

    /**
     * @var string|null
     *
     * The default needs to be null instead of empty string, as in MySQL 5.7
     * {@link https://dev.mysql.com/doc/refman/5.7/en/blob.html "TEXT columns cannot have DEFAULT values."}
     * and the empty string (now null) value is handled in a special way outside of this class.
     *
     * @ORM\Column(name="drafts_info_json", type="string", length=15000000, nullable=true)
     */
    protected $draftsListJson;

    /**
     * @var Collection<int, Segment>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Segment", mappedBy="parentStatementOfSegment", cascade={"persist", "remove"})
     */
    protected $segmentsOfStatement;

    /**
     * Virtual property to include the methods result in the legacy array format.
     *
     * @var bool
     */
    protected $submitterAndAuthorMetaDataAnonymized;
    /**
     * Virtual property to include the methods result in the legacy array format.
     *
     * @var bool
     */
    protected $textPassagesAnonymized;
    /**
     * Virtual property to include the methods result in the legacy array format.
     *
     * @var bool
     */
    protected $attachmentsDeleted;

    /**
     * @var Collection<int,StatementAttachment>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\StatementAttachment", mappedBy="statement", cascade={"persist"})
     */
    protected $attachments;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint", options={"default": "0"})
     */
    private $segmentationPiRetries;

    /**
     * @var string|null
     *
     * @ORM\Column(name="pi_segments_proposal_resource_url", type="string", length=255, nullable=true)
     */
    private $piSegmentsProposalResourceUrl;

    /**
     * This is modelled as ManyToMany, because intentionally it should be possible, that one ProcedurePersons is
     * related to many Statements, as well as many Statements are related to one ProcedurePerson.
     * Actually this is used as OneToMany, because one Statement can be related to many ProcedurePersons, but a
     * ProcedurePerson will only have one related Statement. That means, we can use cascade remove on this site,
     * to ensure related ProcedurePersons will be deleted in case of this Statement will be deleted.
     *
     * @var Collection<int, ProcedurePerson>
     *
     * @ORM\ManyToMany(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson",
     *     inversedBy="similarForeignStatements",
     *     cascade={"persist", "remove"},
     *     orphanRemoval = true
     * )
     *
     * @ORM\JoinTable(name="similar_statement_submitter",
     *      joinColumns={@ORM\JoinColumn(name="statement_id", referencedColumnName="_st_id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="submitter_id", referencedColumnName="id")}
     * )
     */
    private Collection $similarStatementSubmitters;

    /**
     * True in case of the statement was given anonymously.
     * (This is currently only possible as unregistered guest user in public detail).
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable = false, options={"default":false})
     */
    private $anonymous = false;

    public function __construct()
    {
        $this->deletedDate = DateTime::createFromFormat('d.m.Y', '2.1.1970');
        $this->submit = new DateTime();
        $this->send = DateTime::createFromFormat('d.m.Y', '2.1.1970');
        $this->sentAssessmentDate = DateTime::createFromFormat('d.m.Y', '2.1.1970');
        $this->version = new ArrayCollection();
        $this->votes = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->statementAttributes = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->counties = new ArrayCollection();
        $this->priorityAreas = new ArrayCollection();
        $this->municipalities = new ArrayCollection();
        $this->fragments = new ArrayCollection();
        $this->files = [];
        $this->cluster = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->segmentsOfStatement = new ArrayCollection();
        $this->anonymizations = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->similarStatementSubmitters = new ArrayCollection();
        $this->segmentationPiRetries = 0;
        $this->statementsCreatedFromOriginal = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @deprecated use {@link Statement::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * @return Statement
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Parent-Statement, of which this Statement was copied.
     *
     * @param StatementInterface $parent
     *
     * @return $this
     */
    public function setParent($parent)
    {
        if ($parent instanceof self) {
            $parent->addChild($this);
        }

        $this->parentId = null === $parent ? null : $parent->getId();
        $this->parent = $parent;

        return $this;
    }

    /**
     * @param StatementInterface $child
     *
     * @return $this
     */
    public function addChild($child)
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    /**
     * @param StatementInterface $child
     *
     * @return $this
     */
    public function removeChild($child)
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            $child->setParent(null);
        }

        return $this;
    }

    /**
     * @param StatementInterface[] $children
     *
     * @return $this
     */
    public function setChildren($children)
    {
        if (null !== $children) {
            foreach ($children as $child) {
                $this->addChild($child);
            }
        } else {
            $this->children = new ArrayCollection();
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getParentId()
    {
        if (null === $this->parentId && $this->parent instanceof Statement) {
            $this->parentId = $this->parent->getId();
        }

        return $this->parentId;
    }

    /**
     * @return ArrayCollection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return Statement|null
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * @param StatementInterface $original
     */
    public function setOriginal($original)
    {
        $this->originalId = null === $original ? null : $original->getId();
        $this->original = $original;
    }

    public function getOriginalId(): ?string
    {
        return null === $this->original ? null : $this->original->getId();
    }

    /**
     * Set priority.
     *
     * @param string $priority
     */
    public function setPriority($priority): Statement
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Get priority.
     */
    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * Get prioritySort.
     *
     * Rewrites emptystrings with "zzz" in order to move them last
     * in sorted elasticsearch lists.
     *
     * @return string
     */
    public function getPrioritySort()
    {
        return '' == $this->priority ? 'zzz' : $this->priority;
    }

    /**
     * Add Priority Area.
     *
     * @param PriorityAreaInterface $priorityArea
     *
     * @return bool - true, if the given priorityArea was successful added to this statement
     *              and this statement was successful added to the given priorityArea, otherwise false
     */
    public function addPriorityArea($priorityArea): bool
    {
        if (!$this->priorityAreas->contains($priorityArea)) {
            $addedStatementSuccessful = $this->priorityAreas->add($priorityArea);
            $addedPriorityAreaSuccessful = $priorityArea->addStatement($this);

            return $addedStatementSuccessful && $addedPriorityAreaSuccessful;
        }

        return false;
    }

    /**
     * Remove PriorityArea.
     *
     * @param PriorityAreaInterface $priorityArea
     */
    public function removePriorityArea($priorityArea)
    {
        if (!$priorityArea instanceof PriorityArea) {
            return;
        }
        if ($this->priorityAreas->contains($priorityArea)) {
            $this->priorityAreas->removeElement($priorityArea);
            $priorityArea->removeStatement($this);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getPriorityAreas()
    {
        return $this->priorityAreas;
    }

    public function getPriorityAreaKeys(): array
    {
        $ret = [];
        foreach ($this->getPriorityAreas() as $pa) {
            $ret[] = $pa->getKey();
        }

        return $ret;
    }

    public function getMunicipalityNames(): array
    {
        $ret = [];
        foreach ($this->getMunicipalities() as $m) {
            $ret[] = $m->getName();
        }

        return $ret;
    }

    public function getCountyNames(): array
    {
        $ret = [];
        foreach ($this->getCounties() as $c) {
            $ret[] = $c->getName();
        }

        return $ret;
    }

    /**
     * @param PriorityAreaInterface[]|ArrayCollection<int, PriorityAreaInterface> $priorityAreas
     */
    public function setPriorityAreas($priorityAreas)
    {
        // remove corresponding entries
        /** @var PriorityArea $priorityArea */
        foreach ($this->getPriorityAreas() as $priorityArea) {
            $this->removePriorityArea($priorityArea);
        }

        foreach ($priorityAreas as $priorityArea) {
            if (!$this->priorityAreas->contains($priorityArea)) {
                $this->priorityAreas->add($priorityArea);
            }
        }
    }

    /**
     * Set externId.
     *
     * @param string $externId
     */
    public function setExternId($externId): Statement
    {
        $this->externId = $externId;

        return $this;
    }

    public function getExternId(): string
    {
        return $this->externId;
    }

    /**
     * The usual statement pair (original + non original), makes it tricky to ensure
     * a unique internId per procedure, because these pair is a kind of a copy.
     * To ensure the interId is actually unique per procedure, the internId will only be stored
     * at the original statement.
     * To reduce the mental load, on getting the internId of a statement, the internId of the related
     * original statement wil be returned by default.
     * But in some (technically) cases it can be necessary to get the internId of the current statement,
     * instead of its parent. This can be done by setting the $gettingFromOriginal to false.
     */
    public function getInternId(bool $gettingFromOriginal = true): ?string
    {
        if ($gettingFromOriginal && null !== $this->getOriginal()) {
            return $this->getOriginal()->getInternId();
        }

        return $this->internId;
    }

    /**
     * @param string $internId
     */
    public function setInternId($internId): Statement
    {
        $this->internId = $internId;

        return $this;
    }

    /**
     * Set user.
     *
     * @param UserInterface $user
     */
    public function setUser($user): Statement
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string|null
     */
    public function getUserId()
    {
        if (null === $this->uId && $this->user instanceof User) {
            $this->uId = $this->user->getId();
        }

        return $this->uId;
    }

    /**
     * Method for ES indexing.
     *
     * @return string
     */
    public function getUId()
    {
        return $this->getUserId();
    }

    /**
     * Method for ES indexing.
     *
     * @return string
     */
    public function getUName()
    {
        return $this->getUserName();
    }

    /**
     * Returns the name of the author!
     */
    public function getUserName(): ?string
    {
        // hole dir den Nutzernamen so, wie er bei dem Statement gespeichert ist, nicht aus
        // dem Userobjekt
        if (null === $this->uName && $this->meta instanceof StatementMeta) {
            $this->uName = $this->meta->getAuthorName();
        }

        return $this->uName;
    }

    /**
     * Set oId.
     *
     * @param OrgaInterface $organisation
     */
    public function setOrganisation($organisation): Statement
    {
        $this->organisation = $organisation;

        return $this;
    }

    /**
     * Get Organisation.
     *
     * Return value might somehow even be empty string
     *
     * @return Orga|string|null
     */
    public function getOrganisation()
    {
        return $this->organisation;
    }

    /**
     * Get Name of related Organisation.
     */
    public function getOrganisationName(): ?string
    {
        return $this->organisation instanceof Orga ? $this->organisation->getName() : null;
    }

    /**
     * All ways to create a statement as citizen, will lead to a related citizen organisation.
     * Therefore, if there is a related organisation and is the related organisation is the
     * default citizen organisation, this statement was submitted and authored by an citizen.
     */
    public function isSubmittedByCitizen(): bool
    {
        return $this->organisation instanceof Orga && $this->organisation->isDefaultCitizenOrganisation();
    }

    /**
     * All ways to create a statement as citizen, will lead to a related citizen organisation.
     * Therefore, if there is no related organisation or the related organisation is not the
     * default citizen organisation, this statement was submitted and authored by an organisation.
     */
    public function isSubmittedByOrganisation(): bool
    {
        return !$this->isSubmittedByCitizen();
    }

    /**
     * Follows the same logic as used in isSubmittedByOrganisation().
     */
    public function isAuthoredByOrganisation(): bool
    {
        return $this->isSubmittedByOrganisation();
    }

    /**
     * Follows the same logic as used in isSubmittedByCitizen().
     */
    public function isAuthoredByCitizen(): bool
    {
        return $this->isSubmittedByCitizen();
    }

    /**
     * Get organisation ID.
     */
    public function getOId(): ?string
    {
        if (null === $this->oId && $this->organisation instanceof Orga) {
            $this->oId = $this->organisation->getId();
        }

        return $this->oId;
    }

    /**
     * Get Organisation Name.
     *
     * @return string
     */
    public function getOName()
    {
        if (null === $this->oName && $this->meta instanceof StatementMeta) {
            $this->oName = $this->meta->getOrgaName();
        }

        return $this->oName;
    }

    /**
     * Get Department Name.
     *
     * @return string
     */
    public function getDName()
    {
        if (null === $this->dName && $this->meta instanceof StatementMeta) {
            $this->dName = $this->meta->getOrgaDepartmentName();
        }

        return $this->dName;
    }

    /**
     * Set procedure.
     *
     * @param ProcedureInterface $procedure
     */
    public function setProcedure($procedure): Statement
    {
        $this->procedure = $procedure;
        if ($procedure instanceof Procedure) {
            $this->pId = $procedure->getId();
        }

        return $this;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    /**
     * Returns the ID of the related procedure.
     *
     * @return string
     */
    public function getProcedureId()
    {
        return $this->getPId();
    }

    /**
     * Get pId.
     *
     * @return string
     */
    public function getPId()
    {
        if (null === $this->pId && $this->procedure instanceof Procedure) {
            $this->pId = $this->procedure->getId();
        }

        return $this->pId;
    }

    /**
     * Set phase.
     *
     * @param string $phase
     */
    public function setPhase($phase): Statement
    {
        if ('' === $phase) {
            $message = 'Tried to set empty string as statement phase, please choose a valid value.';
            throw new UnexpectedValueException($message);
        }

        $this->phase = $phase;

        return $this;
    }

    /**
     * Get phase.
     */
    public function getPhase(): string
    {
        return $this->phase;
    }

    /**
     * Set status.
     *
     * @param string $status
     */
    public function setStatus($status): Statement
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set Created.
     *
     * @param DateTime $created
     */
    public function setCreated($created): Statement
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get Created.
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified.
     *
     * @param DateTime $modified
     */
    public function setModified($modified): Statement
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified.
     *
     * @return DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set send.
     *
     * @param DateTime $send
     */
    public function setSend($send): Statement
    {
        $this->send = $send;

        return $this;
    }

    /**
     * Get send.
     *
     * @return DateTime
     */
    public function getSend()
    {
        return $this->send;
    }

    /**
     * Set sentAssessmentDate.
     *
     * @param DateTime $sentAssessmentDate
     */
    public function setSentAssessmentDate($sentAssessmentDate): Statement
    {
        $this->sentAssessmentDate = $sentAssessmentDate;

        return $this;
    }

    /**
     * Get sentAssessmentDate.
     *
     * @return DateTime
     */
    public function getSentAssessmentDate()
    {
        return $this->sentAssessmentDate;
    }

    /**
     * Set submit.
     *
     * @param DateTime $submit
     */
    public function setSubmit($submit): Statement
    {
        $this->submit = $submit;

        return $this;
    }

    /**
     * Get submit as Timestamp.
     */
    public function getSubmit(): int
    {
        if ($this->submit instanceof DateTime && \is_numeric($this->submit->getTimestamp())) {
            return $this->submit->getTimestamp();
        }

        return 0;
    }

    /**
     * Get submit as Object.
     *
     * @return DateTime
     */
    public function getSubmitObject()
    {
        return $this->submit;
    }

    /**
     * @return string
     */
    public function getSubmitDateString()
    {
        return null === $this->getSubmitObject() ? '' : $this->getSubmitObject()->format('d.m.Y');
    }

    /**
     * Set deletedDate.
     *
     * @param DateTime $deletedDate
     */
    public function setDeletedDate($deletedDate): Statement
    {
        $this->deletedDate = $deletedDate;

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getCounties()
    {
        return $this->counties;
    }

    /**
     * @param CountyInterface[]|ArrayCollection<int, CountyInterface> $counties
     */
    public function setCounties($counties)
    {
        // remove corresponding entries
        /** @var County $county */
        foreach ($this->getCounties() as $county) {
            $this->removeCounty($county);
        }
        foreach ($counties as $county) {
            if (!$this->counties->contains($county)) {
                $this->counties->add($county);
            }
        }
    }

    /**
     * Add County.
     *
     * @param CountyInterface $county
     *
     * @return bool - true, if the given county was successful added to this statement
     *              and this statement was successful added to the given county, otherwise false
     */
    public function addCounty($county): bool
    {
        if (!$this->counties->contains($county)) {
            $addedStatementSuccessful = $this->counties->add($county);
            $addedCountySuccessful = $county->addStatement($this);

            return $addedStatementSuccessful && $addedCountySuccessful;
        }

        return false;
    }

    /**
     * Remove County.
     *
     * @param CountyInterface $county
     */
    public function removeCounty($county)
    {
        if (!$county instanceof County) {
            return;
        }
        if ($this->counties->contains($county)) {
            $this->counties->removeElement($county);
            $county->removeStatement($this);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getMunicipalities()
    {
        return $this->municipalities;
    }

    /**
     * @param MunicipalityInterface[]|ArrayCollection<int, MunicipalityInterface> $municipalities
     */
    public function setMunicipalities($municipalities)
    {
        // remove corresponding entries
        /** @var Municipality $municipality */
        foreach ($this->getMunicipalities() as $municipality) {
            $this->removeMunicipality($municipality);
        }
        foreach ($municipalities as $municipality) {
            if (!$this->municipalities->contains($municipality)) {
                $this->municipalities->add($municipality);
            }
        }
    }

    /**
     * Add Municipality.
     *
     * @param MunicipalityInterface $municipality
     *
     * @return bool - true, if the given municipality was successful added to this statement
     *              and this statement was successful added to the given municipality, otherwise false
     */
    public function addMunicipality($municipality): bool
    {
        if (!$this->municipalities->contains($municipality)) {
            $addedStatementSuccessful = $this->municipalities->add($municipality);
            $addedMunicipalitySuccessful = $municipality->addStatement($this);

            return $addedStatementSuccessful && $addedMunicipalitySuccessful;
        }

        return false;
    }

    public function getFragments(): Collection
    {
        return $this->fragments;
    }

    /**
     * @param StatementFragmentInterface[] $fragments
     */
    public function setFragments($fragments)
    {
        $this->fragments = new ArrayCollection($fragments);
    }

    public function removeFragment(StatementFragmentInterface $fragment): void
    {
        $this->fragments->removeElement($fragment);
    }

    public function addFragment(StatementFragmentInterface $fragment): void
    {
        $this->fragments->add($fragment);
    }

    /**
     * Number of fragments that match a given filter when a search is applied
     * Returns the number of all fragments if nothing is filtered or searched.
     */
    public function getFragmentsFilteredCount(): ?int
    {
        if (null === $this->fragmentsFilteredCount) {
            return $this->getFragmentsCount();
        }

        return $this->fragmentsFilteredCount;
    }

    /**
     * Number of all fragments.
     */
    public function getFragmentsCount(): int
    {
        return $this->fragments->count();
    }

    /**
     * @param int|null $fragmentsFilteredCount
     */
    public function setFragmentsFilteredCount($fragmentsFilteredCount)
    {
        $this->fragmentsFilteredCount = $fragmentsFilteredCount;
    }

    /**
     * @return array<int,string>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return array<int,string>
     */
    public function getFileNames(): array
    {
        $names = [];
        foreach ($this->files as $file) {
            $names[] = explode(':', $file)[0];
        }

        return $names;
    }

    /**
     * Attention. FileStrings are needed, which you can get from the FileContainerRepository.
     * One could implement magic function __toString() in File that returns it.
     * May typehint string will do it's thing.
     *
     * @param array $files
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    /**
     * Remove Municipality.
     *
     * @param MunicipalityInterface $municipality
     */
    public function removeMunicipality($municipality)
    {
        if (!$municipality instanceof Municipality) {
            return;
        }
        if ($this->municipalities->contains($municipality)) {
            $this->municipalities->removeElement($municipality);
            $municipality->removeStatement($this);
        }
    }

    /**
     * Returns a text that describes in the name of whom this
     * statement is going to be submitted.
     *
     * @return string
     */
    public function getRepresents()
    {
        return $this->represents;
    }

    /**
     * Sets a text that describes in the name of whom this
     * statement is going to be submitted.
     *
     * @param string $represents
     */
    public function setRepresents($represents): Statement
    {
        $this->represents = $represents;

        return $this;
    }

    /**
     * Returns wheter the validity and/or authority
     * of this statements representative has been checked
     * by a planner.
     */
    public function getRepresentationCheck(): int
    {
        return (bool) $this->representationCheck;
    }

    /**
     * Sets wheter the validity and/or authority
     * of this statements representative has been checked
     * by a planner.
     *
     * @param bool $checked
     */
    public function setRepresentationCheck($checked): Statement
    {
        $this->representationCheck = (int) $checked;

        return $this;
    }

    /**
     * Get deletedDate.
     *
     * @return DateTime
     */
    public function getDeletedDate()
    {
        return $this->deletedDate;
    }

    /**
     * Set Deleted.
     *
     * @param bool $deleted
     */
    public function setDeleted($deleted): Statement
    {
        $this->deleted = \filter_var($deleted, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get deleted.
     */
    public function getDeleted(): bool
    {
        return \filter_var($this->deleted, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Is deleted.
     */
    public function isDeleted(): bool
    {
        return \filter_var($this->deleted, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Tells Elasticsearch whether Entity should be indexed.
     *
     * @return bool
     */
    public function shouldBeIndexed()
    {
        try {
            if ($this->isDeleted()) {
                return false;
            }
            if ($this->getProcedure()->isDeleted()) {
                return false;
            }

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Set negativeStatement.
     *
     * @param bool $negativeStatement
     */
    public function setNegativeStatement($negativeStatement): Statement
    {
        $this->negativeStatement = (int) $negativeStatement;

        return $this;
    }

    /**
     * Get negativeStatement.
     */
    public function getNegativeStatement(): bool
    {
        return (bool) $this->negativeStatement;
    }

    /**
     * Set sentAssessment.
     *
     * @param bool $sentAssessment
     */
    public function setSentAssessment($sentAssessment): Statement
    {
        $this->sentAssessment = (int) $sentAssessment;
        if (true === $sentAssessment) {
            $this->setSentAssessmentDate(new DateTime());
        }

        return $this;
    }

    /**
     * Get sentAssessment.
     */
    public function getSentAssessment(): bool
    {
        return (bool) $this->sentAssessment;
    }

    /**
     * Get publicAllowed.
     * Convenience getter method.
     *
     **/
    public function getPublicAllowed(): bool
    {
        return in_array(
            $this->getPublicVerified(),
            [StatementInterface::PUBLICATION_PENDING, StatementInterface::PUBLICATION_APPROVED, StatementInterface::PUBLICATION_REJECTED],
            true
        );
    }

    /**
     * Set publicUseName.
     *
     * @param bool $publicUseName
     */
    public function setPublicUseName($publicUseName): Statement
    {
        $this->publicUseName = \filter_var($publicUseName, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get publicUseName.
     */
    public function getPublicUseName(): bool
    {
        return \filter_var($this->publicUseName, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set publicVerified.
     *
     * @param string $publicVerified
     */
    public function setPublicVerified($publicVerified): Statement
    {
        if (!array_key_exists($publicVerified, self::$publicVerifiedMapping)) {
            throw new UnexpectedValueException(sprintf('Tried to set property "$publicVerified" of Statement with invalid value: "%s"', $publicVerified));
        }

        $this->publicVerified = $publicVerified;

        return $this;
    }

    /**
     * Get publicVerified.
     */
    public function getPublicVerified(): string
    {
        if (!array_key_exists($this->publicVerified, self::$publicVerifiedMapping)) {
            throw new UnexpectedValueException(sprintf('Property "publicVerified" of Statement has invalid value: "%s"', $this->publicVerified));
        }

        return $this->publicVerified;
    }

    /**
     * Get translation key of property publicVerified.
     */
    public function getPublicVerifiedTranslation(): string
    {
        return self::$publicVerifiedMapping[$this->getPublicVerified()];
    }

    /**
     * Get publicCheck. This code is solely for backward compatibility and waiting to be completely removed.
     *
     * @deprecated the information of $this->publicCheck is now completely transferred to this->publicVerified
     */
    public function getPublicCheck(): string
    {
        return $this->getPublicVerifiedTranslation();
    }

    /**
     * Set publicStatement.
     *
     * @param string $publicStatement
     */
    public function setPublicStatement($publicStatement): Statement
    {
        $this->publicStatement = $publicStatement;

        return $this;
    }

    /**
     * Get publicStatement.
     */
    public function getPublicStatement(): string
    {
        return $this->publicStatement;
    }

    /**
     * Set toSendPerMail.
     *
     * @param bool $toSendPerMail
     */
    public function setToSendPerMail($toSendPerMail): Statement
    {
        $this->toSendPerMail = \filter_var($toSendPerMail, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get toSendPerMail.
     */
    public function getToSendPerMail(): bool
    {
        return \filter_var($this->toSendPerMail, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set title.
     *
     * @param string $title
     */
    public function setTitle($title): Statement
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set text.
     *
     * @param string $text
     */
    public function setText($text): Statement
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text.
     */
    public function getText(): string
    {
        return $this->text;
    }

    public function getTextShort(): string
    {
        return HTMLFragmentSlicer::getShortened($this->text);
    }

    /**
     * Set recommendation.
     *
     * @param string $recommendation
     */
    public function setRecommendation($recommendation): Statement
    {
        $this->recommendation = $recommendation;

        return $this;
    }

    /**
     * Get recommendation.
     */
    public function getRecommendation(): string
    {
        return $this->recommendation;
    }

    /**
     * @param string $additionalRecommendationParagraphText
     */
    public function addRecommendationParagraph($additionalRecommendationParagraphText): Statement
    {
        $oldRecommendationText = $this->getRecommendation();
        $newRecommendationText = $oldRecommendationText.$additionalRecommendationParagraphText;
        $this->setRecommendation($newRecommendationText);

        return $this;
    }

    public function getRecommendationShort(): string
    {
        return HTMLFragmentSlicer::getShortened($this->recommendation);
    }

    /**
     * Set memo.
     *
     * @param string $memo
     */
    public function setMemo($memo): Statement
    {
        $this->memo = $memo;

        return $this;
    }

    /**
     * Get memo.
     */
    public function getMemo(): string
    {
        return $this->memo;
    }

    /**
     * Set feedback.
     *
     * @param string $feedback
     */
    public function setFeedback($feedback): Statement
    {
        $this->feedback = $feedback;

        return $this;
    }

    /**
     * Get feedback.
     */
    public function getFeedback(): string
    {
        return $this->feedback;
    }

    /**
     * Set reasonParagraph.
     *
     * @param string $reasonParagraph
     */
    public function setReasonParagraph($reasonParagraph): Statement
    {
        $this->reasonParagraph = $reasonParagraph;

        return $this;
    }

    /**
     * @return Collection<int, StatementAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    /**
     * @param Collection<int, StatementAttachmentInterface> $attachments
     */
    public function setAttachments(Collection $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function addAttachment(StatementAttachmentInterface $attachment): self
    {
        if ($this->attachments instanceof Collection && !$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }

        return $this;
    }

    /**
     * Get reasonParagraph.
     */
    public function getReasonParagraph(): string
    {
        return $this->reasonParagraph;
    }

    /**
     * Set planningDocument.
     *
     * @param string $planningDocument
     */
    public function setPlanningDocument($planningDocument): Statement
    {
        $this->planningDocument = $planningDocument;

        return $this;
    }

    /**
     * Get planningDocument.
     *
     * @return string
     */
    public function getPlanningDocument()
    {
        return $this->planningDocument;
    }

    /**
     * Set file.
     *
     * @param string $file
     */
    public function setFile($file): Statement
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get file.
     *
     * @return string
     *
     * @deprecated this was basically removed (replaced with {@link FileContainer}) but may be still used somewhere
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set mapFile.
     *
     * @param string $mapFile
     */
    public function setMapFile($mapFile): Statement
    {
        $this->mapFile = $mapFile;

        return $this;
    }

    /**
     * Get mapFile.
     *
     * @return string|null In the database beside an actual map file string in the
     *                     form of 'Map_DRAFT_STATEMENT_UUID.png:FILE_UUID' the
     *                     value may be null or an empty string as well.
     */
    public function getMapFile(): ?string
    {
        return $this->mapFile;
    }

    /**
     * Set countyNotified.
     *
     * @param bool $cn
     */
    public function setCountyNotified($cn): Statement
    {
        $this->countyNotified = \filter_var($cn, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Get countyNotified.
     *
     * @return bool
     */
    public function getCountyNotified()
    {
        return \filter_var($this->countyNotified, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return ParagraphVersion|null
     */
    public function getParagraph()
    {
        return $this->paragraph;
    }

    /**
     * @param ParagraphVersionInterface|null $paragraph
     */
    public function setParagraph($paragraph)
    {
        $this->paragraph = $paragraph;
        // setze die ggf. zwischengespeicherten Daten zurück
        $this->paragraphId = null;
        $this->paragraphTitle = null;
        $this->paragraphOrder = 0;
    }

    /**
     * Get paragraphId.
     *
     * @return string
     */
    public function getParagraphId()
    {
        if ($this->paragraph instanceof ParagraphVersion) {
            $this->paragraphId = $this->paragraph->getId();
        }

        return $this->paragraphId;
    }

    /**
     * Get paragraphTitle.
     *
     * @return string
     */
    public function getParagraphTitle()
    {
        if ($this->paragraph instanceof ParagraphVersion) {
            $this->paragraphTitle = $this->paragraph->getTitle();
        }

        return trim($this->paragraphTitle ?? '');
    }

    /**
     * Get paragraphOrder.
     *
     * @return string
     */
    public function getParagraphOrder()
    {
        if ($this->paragraph instanceof ParagraphVersion) {
            $this->paragraphOrder = $this->paragraph->getOrder();
        }

        return $this->paragraphOrder;
    }

    /**
     * Get paragraphParentId.
     *
     * @return string
     */
    public function getParagraphParentId()
    {
        if (null === $this->paragraphParentId && $this->paragraph instanceof ParagraphVersion) {
            $parentId = null;
            $parentParagraph = $this->paragraph->getParagraph();
            if ($parentParagraph instanceof Paragraph) {
                $parentId = $parentParagraph->getId();
            }
            $this->paragraphParentId = $parentId;
        }

        return $this->paragraphParentId;
    }

    /**
     * @return string returns the title of the parent paragraph (the paragraph of the paragraph version)
     */
    public function getParagraphParentTitle(): string
    {
        if (null === $this->paragraphParentTitle && $this->paragraph instanceof ParagraphVersion) {
            $parentTitle = null;
            if ($this->paragraph->getParagraph() instanceof Paragraph) {
                $parentTitle = $this->paragraph->getParagraph()->getTitle();
            }
            $this->paragraphParentTitle = $parentTitle;
        }

        return trim($this->paragraphParentTitle ?? '');
    }

    /**
     * @return string returns the title of the parent document (the document of the document version)
     */
    public function getDocumentParentTitle(): string
    {
        if (null === $this->documentParentTitle && $this->document instanceof SingleDocumentVersion) {
            $documentTitle = null;
            if ($this->document->getSingleDocument() instanceof SingleDocument) {
                $documentTitle = $this->document->getSingleDocument()->getTitle();
            }
            $this->documentParentTitle = $documentTitle;
        }

        return trim($this->documentParentTitle ?? '');
    }

    /**
     * @return SingleDocumentVersion|null
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @param SingleDocumentVersionInterface|null $documentVersion
     */
    public function setDocument($documentVersion)
    {
        $this->document = $documentVersion;

        // setze die ggf. zwischengespeicherten Daten zurück
        $this->documentId = null;
        $this->documentTitle = null;
    }

    /**
     * Get documentId.
     *
     * @return string
     */
    public function getDocumentId()
    {
        if ($this->document instanceof SingleDocumentVersion) {
            $this->documentId = $this->document->getId();
        }

        return $this->documentId;
    }

    /**
     * Get documentTitle.
     *
     * @return string
     */
    public function getDocumentTitle()
    {
        if ($this->document instanceof SingleDocumentVersion) {
            $this->documentTitle = $this->document->getTitle();
        }

        return $this->documentTitle;
    }

    /**
     * Get documentHash.
     *
     * @return string
     */
    public function getDocumentHash()
    {
        if ($this->document instanceof SingleDocumentVersion) {
            $this->documentHash = $this->document->getDocument();
        }

        return $this->documentHash;
    }

    /**
     * Get elementId.
     *
     * @return string
     */
    public function getElementId()
    {
        if ($this->element instanceof Elements) {
            $this->elementId = $this->element->getId();
        }

        return $this->elementId;
    }

    /**
     * Get elementOrder.
     *
     * @return int
     */
    public function getElementOrder()
    {
        $elementOrder = 0;
        if ($this->element instanceof Elements) {
            $elementOrder = $this->element->getOrder();
        }

        return $elementOrder;
    }

    /**
     * Get elementTitle.
     *
     * @return string
     */
    public function getElementTitle()
    {
        if ($this->element instanceof Elements) {
            $this->elementTitle = $this->element->getTitle();
        }

        return $this->elementTitle;
    }

    /**
     * Get categoryType.
     *
     * Returns the category of the element that this statement refers to
     *
     * @return string
     */
    public function getElementCategory()
    {
        if ($this->element instanceof Elements) {
            return $this->element->getCategory();
        }

        return null;
    }

    /**
     * @return Elements|null
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * @param ElementsInterface|null $element
     */
    public function setElement($element)
    {
        $this->element = $element;

        // setze die ggf. zwischengespeicherten Daten zurück
        $this->elementId = null;
        $this->elementTitle = null;
    }

    /**
     * Set polygon.
     *
     * @param string $polygon
     *
     * @return Statement
     */
    public function setPolygon($polygon)
    {
        $this->polygon = $polygon;

        return $this;
    }

    /**
     * Get polygon.
     *
     * @return string
     */
    public function getPolygon()
    {
        return $this->polygon;
    }

    /**
     * Set DraftStatement.
     *
     * @param DraftStatementInterface $draftStatement
     *
     * @return Statement
     */
    public function setDraftStatement($draftStatement)
    {
        $this->draftStatement = $draftStatement;

        return $this;
    }

    /**
     * Get DraftStatement.
     *
     * @return DraftStatement
     */
    public function getDraftStatement()
    {
        return $this->draftStatement;
    }

    /**
     * Get DraftStatement Id.
     *
     * @return string
     */
    public function getDraftStatementId()
    {
        if (null === $this->draftStatementId && $this->draftStatement instanceof DraftStatement) {
            $this->draftStatementId = $this->draftStatement->getId();
        }

        return $this->draftStatementId;
    }

    public function getMeta(): StatementMeta
    {
        // in some cases StatementMeta is not set
        // create new object to avoid null pointer exceptions
        if (null === $this->meta) {
            $meta = new StatementMeta();
            $meta->setStatement($this);
            $this->meta = $meta;
        }

        return $this->meta;
    }

    public function setMeta(StatementMetaInterface $meta): void
    {
        $this->meta = $meta;
        $meta->setStatement($this);
    }

    /**
     * @return ArrayCollection
     */
    public function getStatementAttributes()
    {
        return $this->statementAttributes;
    }

    /**
     * @return StatementVersionField
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param StatementVersionFieldInterface $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return Collection<int,StatementVote>
     */
    public function getVotes()
    {
        return $this->votes;
    }

    /**
     * To ensure correct handling of votes, use repository method instead using this method directly.
     * The repository method, can handle a list of given votes and decide which of the given votes
     * have to be created or updated and which of the current votes have to be deleted.
     *
     * @param StatementVoteInterface[] $votes
     */
    public function setVotes($votes)
    {
        $this->votes = $votes;
    }

    /**
     * Returns number of votes for easier use in ES.
     */
    public function getVotesNum(): int
    {
        $sum = $this->numberOfAnonymVotes;
        if ($this->votes instanceof Collection) {
            $sum = $this->votes->count() + $this->numberOfAnonymVotes;
        }

        return $sum;
    }

    /**
     * Returns number of likes for easier use in ES.
     *
     * @return int
     */
    public function getLikesNum()
    {
        if ($this->likes instanceof Collection) {
            return $this->likes->count();
        }

        return 0;
    }

    /**
     * @return StatementLike[]
     */
    public function getLikes()
    {
        return $this->likes;
    }

    /**
     * Set Tags.
     *
     * @param TagInterface[] $tags
     */
    public function setTags($tags): Statement
    {
        // remove corresponding entries
        /** @var Tag $tag */
        foreach ($this->getTags() as $tag) {
            $this->removeTag($tag);
        }
        foreach ($tags as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }

        return $this;
    }

    /**
     * Add Tag.
     *
     * @return bool - true, if the given tag was successful added to this statement
     *              and this statement was successful added to the given tag, otherwise false
     */
    public function addTag(TagInterface $tag): bool
    {
        if (!$this->tags->contains($tag)) {
            $addedStatementSuccessful = $this->tags->add($tag);
            $addedTagSuccessful = $tag->addStatement($this);

            return $addedStatementSuccessful && $addedTagSuccessful;
        }

        return false;
    }

    /**
     * @param array<int, TagInterface> $tags
     */
    public function addTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->addTag($tag);
        }
    }

    /**
     * @param array<int, TagInterface> $tags
     */
    public function removeTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->removeTag($tag);
        }
    }

    /**
     * Remove Tag.
     *
     * @return Statement
     */
    public function removeTag(TagInterface $tag)
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * Get Tags.
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * Returns the names of all Tags assigned to this Statement.
     *
     * @return array()
     */
    public function getTagNames(): array
    {
        $ret = [];
        foreach ($this->getTags() as $tag) {
            $ret[] = $tag->getTitle();
        }

        return $ret;
    }

    /**
     * Returns the Ids of all Tags assigned to this Statement.
     *
     * @return array()
     */
    public function getTagIds(): array
    {
        $ret = [];
        foreach ($this->getTags() as $tag) {
            $ret[] = $tag->getId();
        }

        return $ret;
    }

    /**
     * Returns the names of all Topics that are related with this Statement.
     *
     * @return array()
     */
    public function getTopicNames()
    {
        $ret = [];
        foreach ($this->getTags() as $tag) {
            if (null === $tag->getTopic()) {
                continue;
            }
            $ret[$tag->getTopic()->getTitle()] = null;
        }

        return \array_keys($ret);
    }

    /**
     * VoteStatskanzlei
     * Get the StK-vote of this statement.
     *
     * @return string
     */
    public function getVoteStk()
    {
        return $this->voteStk;
    }

    /**
     * Set the StK-vote of this statement
     * Kind of vote advice.
     *
     * @param string|null $voteStk
     *
     * @throws Exception
     */
    public function setVoteStk($voteStk): Statement
    {
        if (!\in_array($voteStk, $this->getAllowedVoteValues())) {
            throw new Exception("The value of \$voteStk ('$voteStk') is not an acceptable value");
        }
        $this->voteStk = $voteStk;

        return $this;
    }

    /**
     * VotePlanungs...behörde?!/büro
     * Get the planners vote of this statement.
     */
    public function getVotePla(): ?string
    {
        return $this->votePla;
    }

    /**
     * Set the planners vote of this statement.
     *
     * @param string|null $votePla
     *
     * @throws Exception
     */
    public function setVotePla($votePla): Statement
    {
        if (!\in_array($votePla, $this->getAllowedVoteValues())) {
            throw new Exception("The value of \$votePla ('$votePla') is not an acceptable value");
        }
        $this->votePla = '' === $votePla ? null : $votePla;

        return $this;
    }

    public function getAllowedVoteValues(): array
    {
        return [
            null,
            'acknowledge',
            'followed',
            'following',
            'full',
            'no',
            'noFollow',
            'partial',
            'workInProgress',
        ];
    }

    /**
     * @return string
     */
    public function getSubmitType()
    {
        return $this->submitType;
    }

    /**
     * @param string $submitType
     */
    public function setSubmitType($submitType)
    {
        $this->submitType = $submitType;
        // save submitType to be translated later on during ES populate and Doctrine fetch
        $this->submitTypeTranslated = $submitType;
    }

    /**
     * This field ist transformed during populate to Elasticsearch and Doctrine fetch.
     */
    public function getSubmitTypeTranslated(): string
    {
        return $this->submitTypeTranslated;
    }

    public function setSubmitTypeTranslated(string $submitTypeTranslated): Statement
    {
        $this->submitTypeTranslated = $submitTypeTranslated;

        return $this;
    }

    /**
     * @return User|null
     */
    public function getAssignee()
    {
        return $this->assignee;
    }

    public function getAssigneeId(): ?string
    {
        $assignee = $this->getAssignee();
        if (null === $assignee) {
            return null;
        }

        return $assignee->getId();
    }

    /**
     * @param UserInterface|null $assignee
     */
    public function setAssignee($assignee)
    {
        $this->assignee = $assignee;
    }

    /**
     * Returns the cluster which this Statement belongs to.
     *
     * @return ArrayCollection
     */
    public function getCluster()
    {
        if (null === $this->cluster) {
            $this->cluster = new ArrayCollection();
        }

        return $this->cluster;
    }

    /**
     * Add this Statement to a cluster of Statements
     * If one of the given statements already a member of a cluster, this membership will be replaced!
     *
     * @param StatementInterface[] $statements
     *
     * @return $this
     */
    public function setCluster($statements)
    {
        $this->cluster = \is_array($statements) ? new ArrayCollection($statements) : $statements;
        foreach ($statements as $statement) {
            $statement->setHeadStatement($this);
            $this->clusterStatement = true;
        }

        return $this;
    }

    /**
     * @return Statement
     */
    public function getHeadStatement()
    {
        return $this->headStatement;
    }

    /**
     * @return string|null
     */
    public function getHeadStatementId()
    {
        if ($this->headStatement instanceof Statement) {
            return $this->headStatement->getId();
        }

        return null;
    }

    /**
     * @param StatementInterface $headStatement
     *
     * @return $this
     */
    public function setHeadStatement($headStatement)
    {
        $this->headStatement = $headStatement;

        return $this;
    }

    /**
     * Determines if this Statement is the head of a cluster.
     *
     * @return bool true if this Statement is the head of a cluster, otherwise false
     */
    public function hasClusterMember()
    {
        // is a head statement itself
        $isCluster = 0 < $this->getCluster()->count();
        // is an original statement. Check, whether child is head statement
        if (!$isCluster && null === $this->originalId && $this->children instanceof Collection) {
            foreach ($this->children->toArray() as $child) {
                /** @var Statement $child */
                if (0 < $child->getCluster()->count()) {
                    $isCluster = true;
                    break;
                }
            }
        }

        $this->clusterStatement = $isCluster;

        return $isCluster;
    }

    /**
     * Determines if this Statement belongs to a cluster.
     *
     * @return bool true if this Statement belongs to a cluster, otherwise false.s
     */
    public function isInCluster(): bool
    {
        return null !== $this->getHeadStatement();
    }

    /**
     * Add a Statement to the cluster of this Statement.
     *
     * @param StatementInterface $statement
     *
     * @return Statement|bool - false if this statement not a head of a cluster, otherwise this statement
     */
    public function addStatement($statement)
    {
        if (!$this->isClusterStatement()) {
            return false;
        }

        if (!$this->getCluster()->contains($statement)) {
            $this->getCluster()->add($statement);
            $statement->setHeadStatement($this);
        }

        return $this;
    }

    /**
     * @return int number of Statements belongs to the cluster
     */
    public function getNumberOfStatementsInCluster()
    {
        return $this->getCluster()->count();
    }

    /**
     * Removes the given Statement from his cluster if it is actually a element of a cluster.
     * Also set the headStatement of the given Statement to null.
     *
     * @return bool - true if the given Statement is a element of a cluster
     *              and was successfully removed, otherwise false
     */
    public function removeClusterElement(StatementInterface $statementToRemove)
    {
        $successful = false;
        if ($this->isClusterStatement() && $this->getCluster()->contains($statementToRemove)) {
            $successful = $this->getCluster()->removeElement($statementToRemove);
            if ($successful) {
                $statementToRemove->setHeadStatement(null);
            }
        }

        return $successful;
    }

    /**
     * Removes this Statement from his cluster if it is actually a element of a cluster.
     * Also set the headStatement if this Statement to null.
     *
     * @return bool - true if the given Statement is a element of a cluster
     *              and was successfully removed, otherwise false
     */
    public function detachFromCluster()
    {
        if ($this->isInCluster()) {
            return $this->getHeadStatement()->removeClusterElement($this);
        }

        return false;
    }

    /**
     * Load Authored Date from MetaData as Timestamp.
     *
     * @return int - Timestamp
     */
    public function getAuthoredDate()
    {
        return $this->getMeta()->getAuthoredDate();
    }

    /**
     * @return string
     */
    public function getAuthoredDateString()
    {
        if (null === $this->getMeta()) {
            return '';
        }

        return null === $this->getMeta()->getAuthoredDateObject() ? '' : $this->getMeta()->getAuthoredDateObject()->format('d.m.Y');
    }

    /**
     * Yields the type of this statement.
     *
     * Returns wheter the statement is a regular statement ('N'),
     * A cluster ('G')
     * Or a manually entered statement ('M')
     */
    public function getType(): string
    {
        if ($this->isManual()) {
            return 'm';
        }

        if ($this->isClusterStatement()) {
            return 'g';
        }

        return 'n';
    }

    /**
     * @return string|null
     *
     * @throws Exception
     */
    public function getConsentSubmitterId()
    {
        return $this->getSubmitterId();
    }

    /**
     * @return User|null
     *
     * @throws Exception
     */
    public function getConsentAuthor()
    {
        return $this->getAuthor();
    }

    /**
     * Considers if a GDPR consent was given at some time as well as if the consent was revoked.
     *
     * The original statement will be used if this statement isn't one.
     *
     * @return bool true if the consent was given and not revoked; false otherwise
     */
    public function isConsented(): bool
    {
        $gdprConsent = $this->getGdprConsent();

        return null === $gdprConsent ? false : $gdprConsent->isConsented();
    }

    /**
     * Uses the GdprConsent attached to its $original Statement or its own GdprConsent if the $original
     * Statement is null. If both are null a fake GdprConsent (consentReceived and consentRevoked both false)
     * will be returned. This is done as it is hard to determine the actual consent of some original statements
     * (for example original cluster statements).
     *
     * @return GdprConsent|null
     */
    public function getGdprConsent()
    {
        $originalStatement = $this->getOriginal();
        if (null === $originalStatement) {
            // if there is no originalStatement (meaning this statement is an original)
            // use  consent is attached then use it
            return $this->gdprConsent;
        }

        // if GDPR consent is not attached then use the original statement
        return $originalStatement->getGdprConsent();
    }

    /**
     * @return bool true if the consent was revoked, regardless of if it was received at all; false otherwise
     */
    public function isConsentRevoked(): bool
    {
        $gdprConsent = $this->getGdprConsent();

        return null === $gdprConsent ? false : $gdprConsent->isConsentRevoked();
    }

    /**
     * @return bool true if the consent was received, regardless of if it was revoked later; false otherwise
     */
    public function isConsentReceived(): bool
    {
        $gdprConsent = $this->getGdprConsent();

        return null === $gdprConsent ? false : $gdprConsent->isConsentReceived();
    }

    public function isManual(): bool
    {
        return $this->manual;
    }

    /**
     * @param bool $isManual
     */
    public function setManual($isManual = true)
    {
        $this->manual = $isManual;
    }

    public function isOriginal(): bool
    {
        return null === $this->getOriginal();
    }

    /**
     * @return int
     */
    public function getNumberOfAnonymVotes()
    {
        return $this->numberOfAnonymVotes;
    }

    /**
     * @param int $numberOfAnonymVotes
     */
    public function setNumberOfAnonymVotes($numberOfAnonymVotes)
    {
        if (\is_numeric($numberOfAnonymVotes)) {
            $this->numberOfAnonymVotes = \abs(\intval($numberOfAnonymVotes));
        }
    }

    public function isClusterStatement(): bool
    {
        return $this->clusterStatement;
    }

    /**
     * Because of headStatements are only used as container and will be deleted on resolving a cluster,
     * there is no need to set this flag to false.
     *
     * @param bool $isCluster
     */
    public function setClusterStatement($isCluster): Statement
    {
        $this->clusterStatement = $isCluster;

        return $this;
    }

    public function getAuthorName(): string
    {
        return $this->getMeta()->getAuthorName();
    }

    /**
     * @return string|null
     */
    public function getAuthorId()
    {
        return UserInterface::ANONYMOUS_USER_ID === $this->getUserId() ? null : $this->getUserId();
    }

    /**
     * @return User|null
     */
    public function getAuthor()
    {
        return UserInterface::ANONYMOUS_USER_ID === $this->getUserId() ? null : $this->getUser();
    }

    public function getOrgaPostalCode(): string
    {
        return $this->getMeta()->getOrgaPostalCode();
    }

    public function getOrgaCity(): string
    {
        return $this->getMeta()->getOrgaCity();
    }

    public function getOrgaStreet(): string
    {
        return $this->getMeta()->getOrgaStreet();
    }

    /**
     * @return string|null
     */
    public function getOrgaEmail()
    {
        return $this->getMeta()->getOrgaEmail();
    }

    public function setOrgaEmail(string $emailAddress): self
    {
        if (null === $this->getMeta()) {
            throw new InvalidArgumentException('Can\'t set email address, statement has no meta.');
        }

        $this->getMeta()->setOrgaEmail($emailAddress);

        return $this;
    }

    public function getOrgaPhoneNumber(): string
    {
        return null === $this->getOrganisation() ? '' : $this->getOrganisation()->getPhone();
    }

    /**
     * @return Procedure|null
     */
    public function getMovedToProcedure()
    {
        return null === $this->movedStatement ? null : $this->movedStatement->getProcedure();
    }

    /**
     * @param StatementInterface|null $movedStatement
     */
    public function setMovedStatement($movedStatement)
    {
        $this->movedStatement = $movedStatement;
        // @improve: do not use association/relation to avoid coupling procedures. May we should use only an ID or flag
    }

    /**
     * @return Statement|null
     */
    public function getMovedStatement()
    {
        return $this->movedStatement;
    }

    /**
     * @return string|null
     */
    public function getMovedStatementId()
    {
        return null === $this->movedStatement ? null : $this->movedStatement->getId();
    }

    public function isPlaceholder(): bool
    {
        return null !== $this->getMovedStatement();
    }

    public function wasMoved(): bool
    {
        return null !== $this->getPlaceholderStatement();
    }

    /**
     * @return Statement|null
     */
    public function getPlaceholderStatement()
    {
        return $this->placeholderStatement;
    }

    /**
     * @param StatementInterface|null $placeholderStatement
     */
    public function setPlaceholderStatement($placeholderStatement)
    {
        $this->placeholderStatement = $placeholderStatement;
        // @improve: do not use association/relation to avoid coupling procedures. May we should use only an ID or flag
    }

    /**
     * @return Procedure|null
     */
    public function getMovedFromProcedure()
    {
        return $this->wasMoved() ? $this->placeholderStatement->getProcedure() : null;
    }

    /**
     * @return string|null
     */
    public function getMovedFromProcedureId()
    {
        return $this->wasMoved() ? $this->placeholderStatement->getProcedureId() : null;
    }

    /**
     * @return string|null
     */
    public function getMovedFromProcedureName()
    {
        return $this->wasMoved() ? $this->placeholderStatement->getProcedure()->getName() : null;
    }

    /**
     * @return string|null
     */
    public function getMovedToProcedureId()
    {
        return null !== $this->getMovedToProcedure() ? $this->getMovedToProcedure()->getId() : null;
    }

    public function getMovedToProcedureName(): ?string
    {
        return null !== $this->getMovedToProcedure() ? $this->getMovedToProcedure()->getName() : null;
    }

    /**
     * @return string|null
     */
    public function getFormerExternId()
    {
        return $this->wasMoved() ? $this->placeholderStatement->getExternId() : null;
    }

    /**
     * @return string|null
     */
    public function getDocumentParentId()
    {
        $id = null;
        if ($this->getDocument() instanceof SingleDocumentVersion) {
            $id = $this->getDocument()->getSingleDocumentId();
        }

        return $id;
    }

    /**
     * @return string|null
     */
    public function getProcedureOwningOrgaId()
    {
        return $this->getProcedure()->getOrgaId();
    }

    /**
     * Determines if this statement was created by an institution.
     * <p>
     * This is the case if the 'manual' field is set to false and the 'publicStatement' field is set to {@link INTERNAL}
     * as manual statements are created by planners and statements directly created by citizens are always {@link EXTERNAL}.
     */
    public function isCreatedByInvitableInstitution(): bool
    {
        return !$this->isManual() && StatementInterface::INTERNAL === $this->getPublicStatement();
    }

    public function isCreatedByCitizen(): bool
    {
        return !$this->isManual() && StatementInterface::EXTERNAL === $this->getPublicStatement();
    }

    public function isPlannerCreatedCitizenStatement(): bool
    {
        return $this->isManual() && StatementInterface::EXTERNAL === $this->getPublicStatement();
    }

    public function isPlannerCreatedInvitableInstitutionStatement(): bool
    {
        return $this->isManual() && StatementInterface::INTERNAL === $this->getPublicStatement();
    }

    /**
     * Get the email address used to submit this statement.
     */
    public function getSubmitterEmailAddress(): ?string
    {
        // If submitted by an institution use the email address used when sending the final notice to institutions
        // from the detail view of a statement.
        if ($this->isCreatedByInvitableInstitution()) {
            $orga = $this->getOrganisation();
            if (!$orga instanceof Orga) {
                return null;
            }
            // found an empty String for orga Id for an invalid orga in the wild
            $orgaId = $orga->getId();
            if ('' === $orgaId) {
                return null;
            }

            return $orga->getParticipationEmail();
        }

        // Otherwise (in case of planners/institutions/citizens) use the organizations email address.
        // If citizens don't want to get notificated this field will be empty.
        return $this->getOrgaEmail();
    }

    /**
     * Attempts to reverse the logic in {@link Statement::getSubmitterEmailAddress()}.
     */
    public function setSubmitterEmailAddress(string $emailAddress): self
    {
        // If submitted by an institution use the email address used when sending the final notice to institutions
        // from the detail view of a statement.
        if ($this->isCreatedByInvitableInstitution()) {
            $orga = $this->getOrganisation();
            if (!$orga instanceof Orga) {
                throw new InvalidArgumentException('No organisation set even though created by public interest body.');
            }

            $orga->setParticipationEmail($emailAddress);
        } else {
            // Otherwise (in case of planners/institutions/citizens) use the organizations email address.
            // If citizens don't want to get notificated this field will be empty.
            $this->setOrgaEmail($emailAddress);
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * @param string|null $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Determines if this statement is the copy of another (non-original) statement.
     */
    public function isCopy(): bool
    {
        return null !== $this->getParentId()
            && !$this->isOriginal()
            && $this->getOriginalId() !== $this->getParentId();
    }

    /**
     * Returns the Id of the submitter of this statement, if existing.
     * Will return null, in case of submitter unregistered User (or dummy user User::ANONYMOUS_USER_ID).
     *
     * @return string|null
     */
    public function getSubmitterId()
    {
        // internal:
        if (StatementInterface::INTERNAL === $this->getPublicStatement()) {
            // on internal statements, submitUId on meta should be always filled.
            return $this->getMeta()->getSubmitUId();
        }

        // external:
        // on external statements, the author is always the submitter
        return UserInterface::ANONYMOUS_USER_ID === $this->getUserId() ? null : $this->getUserId();
    }

    /**
     * Returns the Name of the submitter of this statement, if existing.
     * Will return null, in case of submitter unregistered User (or dummy user User::ANONYMOUS_USER_ID).
     */
    public function getSubmitterName(): ?string
    {
        // internal:
        if (StatementInterface::INTERNAL === $this->getPublicStatement()) {
            return $this->getMeta()->getSubmitName();
        }

        if (UserInterface::ANONYMOUS_USER_ID === $this->getUserId()) {
            return null;
        }

        if ($this->getUser() instanceof User) {
            return $this->getUser()->getName();
        }

        return null;
    }

    /**
     * Set GdprConsent to this Statement.
     *
     * @param GdprConsentInterface|null $gdprConsent
     *
     * @throws InvalidDataException
     */
    public function setGdprConsent($gdprConsent)
    {
        if (!$this->isOriginal()) {
            throw new InvalidDataException('can\'t set GdprConsent on non-original statements');
        }
        $this->gdprConsent = $gdprConsent;
        if ($gdprConsent instanceof GdprConsent && $gdprConsent->getStatement() !== $this) {
            $gdprConsent->setStatement($this);
        }
    }

    /**
     * Check if this statement was submitted and authored by an unregistered citizen.
     *
     * @return bool true, if this statement was submitted by an unregistered citizen, otherwise false
     */
    public function hasBeenSubmittedAndAuthoredByUnregisteredCitizen(): bool
    {
        return
            (UserInterface::ANONYMOUS_USER_ID === $this->getUserId() || null === $this->getUserId())
            && StatementInterface::EXTERNAL === $this->getPublicStatement()
            && !$this->isManual();
    }

    /**
     * Check if this statement was submitted and authored by a registered citizen.
     *
     * @return bool true, if this statement was submitted by an registered citizen, otherwise false
     */
    public function hasBeenSubmittedAndAuthoredByRegisteredCitizen(): bool
    {
        return
            UserInterface::ANONYMOUS_USER_ID !== $this->getUserId()
            && null !== $this->getUserId()
            && StatementInterface::EXTERNAL === $this->getPublicStatement()
            && !$this->isManual();
    }

    /**
     * Check if this statement was submitted and authored by a "InvitableInstitutionKoordinator".
     *
     * @return bool true, if this statement was submitted by an "InvitableInstitutionKoordinator", otherwise false
     */
    public function hasBeenSubmittedAndAuthoredByInvitableInstitutionKoordinator(): bool
    {
        return
            UserInterface::ANONYMOUS_USER_ID !== $this->getUserId()
            && null !== $this->getUserId()
            && StatementInterface::INTERNAL === $this->getPublicStatement()
            && !$this->isManual()
            && $this->getUserId() === $this->getMeta()->getSubmitUId();
    }

    /**
     * Check if this statement was authored by a "InstitutionSachbearbeiter".
     * This implicit means, that this statement has be submitted by InstitutionKoordinator!
     *
     * @return bool true, if this statement was submitted by an "InstitutionSachbearbeiter", otherwise false
     */
    public function hasBeenAuthoredByInstitutionSachbearbeiterAndSubmittedByInstitutionKoordinator(): bool
    {
        return
            UserInterface::ANONYMOUS_USER_ID !== $this->getUserId()
            && null !== $this->getUserId()
            && StatementInterface::INTERNAL === $this->getPublicStatement()
            && !$this->isManual()
            && $this->getUserId() !== $this->getMeta()->getSubmitUId();
    }

    public function isSubmitter(string $userId): bool
    {
        return $userId === $this->getSubmitterId();
    }

    public function isAuthor(string $userId): bool
    {
        return $userId === $this->getUserId();
    }

    public function setReplied(bool $replied): void
    {
        $this->replied = $replied;
    }

    public function isReplied(): bool
    {
        return $this->replied;
    }

    /**
     * Needed to create a grouped structure for an export in createElementsGroupStructure2().
     * This method allows to create a group structure with paragraphs and documents on the same level.
     */
    public function getParagraphParentIdOrDocumentParentId(): ?string
    {
        $id = $this->getParagraphParentId();
        if (null !== $id && '' !== $id) {
            return $id;
        }

        $id = $this->getDocumentParentId();
        if (null !== $id && '' !== $id) {
            return $id;
        }

        return null;
    }

    /**
     * Needed to create a grouped structure for an export in createElementsGroupStructure2().
     * This method allows to create a group structure with paragraphs and documents on the same level.
     */
    public function getParagraphParentTitleOrDocumentParentTitle(): ?string
    {
        $title = $this->getParagraphParentTitle();
        if ('' !== $title) {
            return $title;
        }

        $title = $this->getDocumentParentTitle();
        if ('' !== $title) {
            return $title;
        }

        return null;
    }

    public function setDraftsListJson(string $json): void
    {
        $this->draftsListJson = $json;
    }

    /**
     * @return string May be empty if none was set yet
     */
    public function getDraftsListJson(): string
    {
        if (null !== $this->draftsListJson) {
            return $this->draftsListJson;
        }

        return '';
    }

    /**
     * @return Collection<int, Segment>
     */
    public function getSegmentsOfStatement(): Collection
    {
        return $this->segmentsOfStatement;
    }

    /**
     * @param Collection<int, SegmentInterface> $segmentsOfStatement
     */
    public function setSegmentsOfStatement(Collection $segmentsOfStatement): void
    {
        $this->segmentsOfStatement = $segmentsOfStatement;
    }

    public function isAlreadySegmented(): bool
    {
        return !$this->getSegmentsOfStatement()->isEmpty();
    }

    /**
     * Returns true if the Entity is implementing a Segment.
     */
    public function isSegment(): bool
    {
        return false;
    }

    /**
     * @return Collection<int, OriginalStatementAnonymization>
     */
    public function getAnonymizations(): Collection
    {
        return $this->anonymizations;
    }

    /**
     * @param Collection<int, OriginalStatementAnonymizationInterface> $anonymizations
     */
    public function setAnonymizations(Collection $anonymizations): void
    {
        $this->anonymizations = $anonymizations;
    }

    /**
     * @return bool True if this statement has at least one {@link OriginalStatementAnonymization}
     *              entry in which the submitter or author data were deleted from the meta data.
     *              False otherwise. This will not take author/submitter data anonymizations in
     *              the statement text into account.
     */
    public function isSubmitterAndAuthorMetaDataAnonymized(): bool
    {
        return $this->getAnonymizations()->exists(static fn (int $index, OriginalStatementAnonymization $anonymization) => $anonymization->isSubmitterAndAuthorMetaDataAnonymized());
    }

    /**
     * @return bool True if this statement has at least one {@link OriginalStatementAnonymization}
     *              entry in which text passages in {@link text} were anonymized. False
     *              otherwise.
     */
    public function isTextPassagesAnonymized(): bool
    {
        return $this->getAnonymizations()->exists(static fn (int $index, OriginalStatementAnonymization $anonymization) => $anonymization->isTextPassagesAnonymized());
    }

    /**
     * @return bool True if this statement has at least one {@link OriginalStatementAnonymization}
     *              entry in which attachments were deleted. False otherwise.
     */
    public function isAttachmentsDeleted(): bool
    {
        return $this->getAnonymizations()->exists(static fn (int $index, OriginalStatementAnonymization $anonymization) => $anonymization->isAttachmentsDeleted());
    }

    public function getSegmentationPiRetries(): int
    {
        return $this->segmentationPiRetries;
    }

    public function incrementSegmentationPiRetries(): void
    {
        ++$this->segmentationPiRetries;
    }

    /**
     * Returns the Pdf the Statement was created from or null if there is none.
     */
    public function getOriginalFile(): ?File
    {
        foreach ($this->getAttachments() as $attachment) {
            if (StatementAttachmentInterface::SOURCE_STATEMENT === $attachment->getType()) {
                return $attachment->getFile();
            }
        }

        return null;
    }

    public function getPiSegmentsProposalResourceUrl(): ?string
    {
        return $this->piSegmentsProposalResourceUrl;
    }

    public function setPiSegmentsProposalResourceUrl(?string $piSegmentsProposalResourceUrl): void
    {
        $this->piSegmentsProposalResourceUrl = $piSegmentsProposalResourceUrl;
    }

    public function hasDefaultGuestUser(): bool
    {
        return $this->getUser() instanceof User && $this->getUser()->isDefaultGuestUser();
    }

    public function getSubmitterPhoneNumber(): string
    {
        return $this->getMeta()->getMiscDataValue('userPhone') ?? '';
    }

    /**
     * @return Collection<int, ProcedurePerson>
     */
    public function getSimilarStatementSubmitters(): Collection
    {
        return $this->similarStatementSubmitters;
    }

    public function addSimilarStatementSubmitter(ProcedurePerson $similarStatementSubmitter): void
    {
        if (!$this->similarStatementSubmitters->contains($similarStatementSubmitter)) {
            $this->similarStatementSubmitters->add($similarStatementSubmitter);
        }

        if (!$similarStatementSubmitter->getSimilarForeignStatements()->contains($this)) {
            $similarStatementSubmitter->addSimilarForeignStatement($this);
        }
    }

    /**
     * @param Collection<int, ProcedurePersonInterface> $similarStatementSubmitters
     */
    public function setSimilarStatementSubmitters(Collection $similarStatementSubmitters): Statement
    {
        // clear currently set submitters first because this is setting, not adding
        foreach ($this->similarStatementSubmitters as $submitter) {
            $submitter->removeSimilarForeignStatement($this); // handles both sites
            $this->removeSimilarStatementSubmitter($submitter);
        }

        foreach ($similarStatementSubmitters as $submitter) {
            $submitter->addSimilarForeignStatement($this); // handles both sites
            $this->addSimilarStatementSubmitter($submitter);
        }

        $this->similarStatementSubmitters = $similarStatementSubmitters;

        return $this;
    }

    public function removeSimilarStatementSubmitter(ProcedurePersonInterface $procedurePerson): void
    {
        if ($this->similarStatementSubmitters->contains($procedurePerson)) {
            $this->similarStatementSubmitters->removeElement($procedurePerson);
        }
        $procedurePerson->removeSimilarForeignStatement($this);
    }

    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    public function setAnonymous(bool $anonymous): Statement
    {
        $this->anonymous = $anonymous;

        return $this;
    }
}
