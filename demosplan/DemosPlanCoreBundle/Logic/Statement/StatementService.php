<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use Carbon\Carbon;
use Closure;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\StatementCreatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\StatementUpdatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\StatementServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementLike;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVersionField;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\Statement\ManualOriginalStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementUpdatedEvent;
use demosplan\DemosPlanCoreBundle\Exception\AsynchronousStateException;
use demosplan\DemosPlanCoreBundle\Exception\ClusterStatementCopyNotImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\ErroneousDoctrineResult;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\NoTargetsException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedDoctrineResultException;
use demosplan\DemosPlanCoreBundle\Exception\UnknownIdsException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ClusterCitizenInstitutionSorter;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\KeysAtEndSorter;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\KeysAtStartSorter;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ParagraphOrderSorter;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\TitleGroupsSorter;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\Document\SingleDocumentService;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\EntityGrouper;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGroup;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGrouper;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiPaginationParser;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentVersionRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementAttributeRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementFragmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementVoteRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SimilarStatementSubmitterResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanCoreBundle\StoredQuery\AssessmentTableQuery;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\Utilities\Pagination\DemosPlanArrayAdapter;
use demosplan\DemosPlanCoreBundle\Validator\StatementValidator;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\StatementBulkEditVO;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResult;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResultSet;
use demosplan\DemosPlanCoreBundle\ValueObject\MovedStatementData;
use demosplan\DemosPlanCoreBundle\ValueObject\StatementMovement;
use demosplan\DemosPlanCoreBundle\ValueObject\StatementMovementCollection;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Contracts\PathException;
use Elastica\Aggregation\GlobalAggregation;
use Elastica\Exception\ClientException;
use Elastica\Index;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Exception;
use Pagerfanta\Elastica\ElasticaAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;
use UnexpectedValueException;

use function array_map;

class StatementService extends CoreService implements StatementServiceInterface
{
    /**
     * The name of the terms aggregation on the {@link Statement::$status} field.
     */
    final public const AGGREGATION_STATEMENT_STATUS = 'status';
    /**
     * The name of the terms aggregation on the {@link Statement::$priority} field.
     */
    final public const AGGREGATION_STATEMENT_PRIORITY = 'priority';
    /**
     * Name of the {@link Statement::$status} field.
     */
    final public const FIELD_STATEMENT_STATUS = 'status';
    /**
     * Name of the {@link Statement::$priority} field.
     */
    final public const FIELD_STATEMENT_PRIORITY = 'priority';

    /**
     * @var ProcedureService
     */
    protected $procedureService;

    /** @var PriorityAreaService */
    protected $priorityAreaService;

    /** @var ElementsService */
    protected $serviceElements;

    /** @var AssignService */
    protected $assignService;

    /** @var Index */
    protected $esStatementType;

    /** @var array */
    protected $paginatorLimits = [25, 50, 100];

    /** @var PermissionsInterface */
    protected $permissions;

    /** @var UserService */
    protected $userService;

    /** @var HashedQueryService */
    protected $filterSetService;

    /**
     * @var JsonApiPaginationParser
     */
    protected $paginationParser;

    /** @var EntityContentChangeService */
    protected $entityContentChangeService;

    /** @var StatementFragmentService */
    protected $statementFragmentService;

    /** @var StatementCopyAndMoveService */
    protected $statementCopyAndMoveService;

    /** @var StatementCopier */
    protected $statementCopier;

    /** @var SingleDocumentService */
    protected $singleDocumentService;

    /**
     * @var StatementGeoService
     */
    protected $statementGeoService;

    /**
     * @var StatementValidator
     */
    protected $statementValidator;

    public function __construct(
        AssignService $assignService,
        private readonly ConsultationTokenService $consultationTokenService,
        private readonly CurrentUserInterface $currentUser,
        private readonly DateHelper $dateHelper,
        private readonly DepartmentRepository $departmentRepository,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly EditorService $editorService,
        private readonly ElasticSearchService $searchService,
        ElementsService $serviceElements,
        EntityContentChangeService $entityContentChangeService,
        private readonly EntityHelper $entityHelper,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FileContainerRepository $fileContainerRepository,
        private readonly FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        HashedQueryService $filterSetService,
        JsonApiPaginationParser $paginationParser,
        private readonly MessageBagInterface $messageBag,
        protected ParagraphService $paragraphService,
        PermissionsInterface $permissions,
        PriorityAreaService $priorityAreaService,
        private readonly ProcedureRepository $procedureRepository,
        ProcedureService $procedureService,
        private readonly ReportService $reportService,
        private readonly ResourceTypeService $resourceTypeService,
        private readonly RouterInterface $router,
        private readonly SimilarStatementSubmitterResourceType $similarStatementSubmitterResourceType,
        private readonly SingleDocumentRepository $singleDocumentRepository,
        SingleDocumentService $singleDocumentService,
        private readonly SingleDocumentVersionRepository $singleDocumentVersionRepository,
        private readonly StatementAttachmentService $statementAttachmentService,
        private readonly StatementAttributeRepository $statementAttributeRepository,
        StatementCopier $statementCopier,
        StatementCopyAndMoveService $statementCopyAndMoveService,
        private readonly StatementEntityGrouper $statementEntityGrouper,
        private readonly StatementFragmentRepository $statementFragmentRepository,
        StatementFragmentService $statementFragmentService,
        StatementGeoService $statementGeoService,
        private readonly StatementReportEntryFactory $statementReportEntryFactory,
        protected readonly StatementRepository $statementRepository,
        private readonly StatementResourceType $statementResourceType,
        StatementValidator $statementValidator,
        private readonly StatementVoteRepository $statementVoteRepository,
        private readonly TagRepository $tagRepository,
        private readonly TagTopicRepository $tagTopicRepository,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        UserService $userService,
        private readonly StatementDeleter $statementDeleter
    ) {
        $this->assignService = $assignService;
        $this->entityContentChangeService = $entityContentChangeService;
        $this->filterSetService = $filterSetService;
        $this->paginationParser = $paginationParser;
        $this->permissions = $permissions;
        $this->priorityAreaService = $priorityAreaService;
        $this->procedureService = $procedureService;
        $this->serviceElements = $serviceElements;
        $this->singleDocumentService = $singleDocumentService;
        $this->statementCopier = $statementCopier;
        $this->statementCopyAndMoveService = $statementCopyAndMoveService;
        $this->statementFragmentService = $statementFragmentService;
        $this->statementGeoService = $statementGeoService;
        $this->statementValidator = $statementValidator;
        $this->userService = $userService;
    }

    /**
     * Create a new (manual) original statement.
     *
     * @param array<string,mixed> $data
     *
     * @throws EntityNotFoundException
     * @throws InvalidDataException
     * @throws MessageBagException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotFoundException
     */
    public function createOriginalStatement($data): Statement
    {
        $em = $this->getDoctrine()->getManager();

        // Create and use versions of paragraph and SingleDocument
        if (\array_key_exists('paragraphId', $data) && 0 < \strlen((string) $data['paragraphId']) && '-' != $data['paragraphId']) {
            $data['paragraph'] = $this->paragraphService->createParagraphVersion(
                $em->getReference(Paragraph::class, $data['paragraphId'])
            );
        }

        if (\array_key_exists('documentId', $data) && 0 < \strlen((string) $data['documentId'])) {
            $data['document'] = $this->singleDocumentService->createSingleDocumentVersion(
                $em->getReference(SingleDocument::class, $data['documentId'])
            );
        }

        // get submitOrgaId to set it in generateObjectValues() to the statement->meta
        $data['submitOrgaId'] = $this->currentUser->getUser()->getOrganisationId();

        $statement = new Statement();
        $statement->setMeta(new StatementMeta());

        if (\array_key_exists('originalAttachmentFiles', $data)) {
            /** @var ArrayCollection<int,File> $originalAttachmentFiles */
            $originalAttachmentFiles = $data['originalAttachmentFiles'];
            $originalAttachments = $originalAttachmentFiles
                ->map(fn (File $file) => $this->statementAttachmentService->createOriginalAttachment(
                    $statement,
                    $file
                ));
            $statement->setAttachments($originalAttachments);
        }

        $statement = $this->statementRepository->generateObjectValues($statement, $data);

        // For now it would be ok to just use null as consentee ID, as currently
        // getInitialConsenteeIds returns an array with one single null value for manual statements.
        // However I'm afraid if I don't add checks here validating this assumption
        // the getInitialConsenteeIds will be changed without adjusting the code
        // here leading to (legally) dangerous database states.
        $consenteeIds = $this->getInitialConsenteeIds($statement);
        if (1 !== count($consenteeIds) || null !== $consenteeIds['submitter']) {
            throw new InvalidDataException('Expected exacly one null value');
        }
        $gdprConsent = new GdprConsent();
        $gdprConsent->setStatement($statement);
        $statement->setGdprConsent($gdprConsent);

        // if the project supports publicAllowed management, the statement publication is
        // disabled by planner and the statement creator can't set it, set to default
        if ($this->permissions->hasPermission('field_statement_public_allowed')
            && !$statement->getProcedure()->getPublicParticipationPublicationEnabled()) {
            $statement->setPublicVerified(Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED);
        }

        // Add MiscData to StatementMeta
        if (\array_key_exists('meta', $data) && \is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                $statement->getMeta()->setMiscDataValue($key, $value);
            }
        }

        // Validated Statement before creating it
        $violations = $this->statementValidator->validate($statement);
        if (0 === $violations->count()) {
            $statement = $this->statementRepository->addObject($statement);
        } else {
            /** @var ConstraintViolationInterface $error */
            foreach ($violations as $error) {
                $this->messageBag->add('error', $error->getMessage());
            }

            throw ViolationsException::fromConstraintViolationList($violations);
        }

        // add files to FileContainer
        if (\array_key_exists('file', $data)) {
            $statement = $this->addFilesToStatement($data['file'], $statement);
        }

        if (\array_key_exists('statementAttributes', $data) && \is_array($data['statementAttributes'])) {
            $attrRepo = $this->statementAttributeRepository;
            if (\array_key_exists('noLocation', $data['statementAttributes'])
                && true == $data['statementAttributes']['noLocation']) {
                $attrRepo->setNoLocation($statement);
            } elseif (\array_key_exists('county', $data['statementAttributes']) && 0 < \strlen((string) $data['statementAttributes']['county'])) {
                try {
                    $attrRepo->addCounty($statement, $data['statementAttributes']['county']);
                } catch (Exception) {
                    $attrRepo->removeCounty($statement);
                }
            }
        }

        $statementArray = $this->convertToLegacy($statement);
        try {
            $this->addReportNewStatement($statementArray);
        } catch (Exception $e) {
            $this->logger->warning('Add Report in newStatement() failed Message: ', [$e]);
        }

        /** @var StatementCreatedEvent $statementCreatedEvent */
        $statementCreatedEvent = $this->eventDispatcher->dispatch(new ManualOriginalStatementCreatedEvent($statement));

        // statement similarities are calculated?
        $statementSimilarities = $statementCreatedEvent->getStatementSimilarities();
        if (null !== $statementSimilarities) {
            foreach ($statementSimilarities as $statementSimilarity) {
                $this->messageBag->add('confirm', $statementSimilarity->__toString());
            }
            if (0 === count($statementSimilarities)) {
                $this->messageBag->add('confirm', 'Keine ähnlichen Statements gefunden.');
            }
        }

        return $statementCreatedEvent->getStatement();
    }

    /**
     * Erzeugt neue (manuelle) Stellungsnahme.
     *
     * @param array<string,mixed> $data
     *
     * @return statement|bool - Statement as array if successfully, otherwise false
     *
     * @deprecated use {@link StatementService::createOriginalStatement()} instead and handle exceptions properly
     */
    public function newStatement($data)
    {
        // creating originalStatement
        try {
            return $this->createOriginalStatement($data);
        } catch (Exception $e) {
            $this->logger->error('Create new Statement failed:', [$e]);

            return false;
        }
    }

    /**
     * @param array $fileStrings
     *
     * @deprecated use {@see addFilesToStatementObject()} if possible
     */
    public function addFilesToStatement($fileStrings, Statement $statement): ?Statement
    {
        // for legacyreasons single fileuploads are transported as string

        // If there are no files just return
        if (null === $fileStrings) {
            return $statement;
        }

        if (!\is_array($fileStrings)) {
            $fileStrings = [$fileStrings];
        }

        return $this->addFilesToStatementObject($fileStrings, $statement);
    }

    public function addFilesToStatementObject(array $fileStrings, Statement $statement): ?Statement
    {
        if (0 === count($fileStrings)) {
            return $statement;
        }

        $fileService = $this->fileService;

        \collect($fileStrings)
            ->map(function ($fileString) use ($fileService, $statement) {
                $fileService->addStatementFileContainer(
                    $statement->getId(),
                    $fileService->getInfoFromFileString($fileString, 'hash'),
                    $fileString
                );
            })->toArray();

        // Update Statement with attached files
        return $this->getStatement($statement->getId());
    }

    /**
     * @throws Exception
     */
    public function addFilesToCopiedStatement(Statement $newStatement, string $originalStatementId): void
    {
        $newStatementId = $newStatement->getId();
        $originalFileContainers = $this->fileContainerRepository->getStatementFileContainers($originalStatementId);

        $fileStrings = [];
        foreach ($originalFileContainers as $oldFileContainer) {
            $copy = $this->fileService->addFileContainerCopy($newStatementId, $oldFileContainer);
            $fileStrings[] = $copy->getFileString();
        }

        // Update Statement with attached files
        $newStatement->setFiles($fileStrings);
    }

    /**
     * This method basically uses the fingerprint principle: Whoever touches (claims = assignee) a DS, has his/her fingerprint on it (is lastClaimedId). Unless that person is a reviewer (uses gloves).
     *
     * Use after all other changes have been done to the object, but (of course) before the object is updated in the database.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/claim/ wiki: claiming
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     *
     * @deprecated use {@see StatementFragmentService::updateLastClaimedIdInCurrentObjectAfterAllOtherChanges()} instead
     */
    public function updateLastClaimedIdInCurrentObjectAfterAllOtherChanges(StatementFragment $fragmentObject): StatementFragment
    {
        return $this->statementFragmentService->updateLastClaimedIdInCurrentObjectAfterAllOtherChanges($fragmentObject);
    }

    /**
     * Add a report entry to the DB.
     */
    public function addReportNewStatement(array $statement): void
    {
        $entry = $this->statementReportEntryFactory->createStatementCreatedEntry(
            $statement
        );
        $this->reportService->persistAndFlushReportEntries($entry);
    }

    /**
     * Creates a new original Statement from a DraftStatement and returns a copy of that Statement as legacy
     * array data structure.
     *
     * @param User $user
     * @param bool $gdprConsentReceived true if the GDPR consent was received from the submitter
     *
     * @return Statement|false
     */
    public function submitDraftStatement(
        DraftStatement $draftStatement,
        $user,
        NotificationReceiver $notificationReceiver = null,
        bool $gdprConsentReceived = false
    ) {
        try {
            $originalStatement = $this->statementRepository
                ->submitDraftStatement($draftStatement, $user, $notificationReceiver, $gdprConsentReceived);

            // add Files to Statement
            $originalStatement = $this->addFilesToStatementObject($draftStatement->getFiles(), $originalStatement);

            $this->statementAttributeRepository->copyStatementAttributes($draftStatement, $originalStatement);

            // Create a statement copy for the assessment table
            $assessableStatement = $this->statementCopier->copyStatementObjectWithinProcedureWithRelatedFiles(
                $originalStatement,
                false,
                true
            );

            $assessableStatement = $this->postSubmitDraftStatement($assessableStatement, $draftStatement);

            /** @var StatementCreatedEvent $statementCreatedEvent */
            $statementCreatedEvent = $this->eventDispatcher->dispatch(
                new StatementCreatedEvent($assessableStatement),
                StatementCreatedEventInterface::class
            );

            return $statementCreatedEvent->getStatement();
        } catch (Exception $e) {
            $this->logger->error('Create Statement failed:', [$e]);

            return false;
        }
    }

    /**
     * Ruft Versionen von Feldern einzelner Stellungnahmen ab.
     *
     * @param string $ident
     *
     * @throws Exception
     */
    public function getVersionFields($ident): array
    {
        $statement = $this->getStatementByIdent($ident);
        if (isset($statement['version']) && $statement['version'] instanceof Collection) {
            $versionFields = [];
            foreach ($statement['version'] as $versionField) {
                if ($versionField instanceof StatementVersionField) {
                    $versionFieldArray = $this->entityHelper->toArray($versionField);
                    $versionFields[] = $this->dateHelper->convertDatesToLegacy($versionFieldArray);
                }
            }
            $statement['version'] = $versionFields;
            $statement['total'] = count($versionFields);
        }

        return $statement;
    }

    public function updatePersonEditableProperties(PropertiesUpdater $updater, ProcedurePerson $person): void
    {
        $updater->ifPresent($this->similarStatementSubmitterResourceType->city, $person->setCity(...));
        $updater->ifPresent($this->similarStatementSubmitterResourceType->streetName, $person->setStreetName(...));
        $updater->ifPresent($this->similarStatementSubmitterResourceType->streetNumber, $person->setStreetNumber(...));
        $updater->ifPresent($this->similarStatementSubmitterResourceType->postalCode, $person->setPostalCode(...));
        $updater->ifPresent($this->similarStatementSubmitterResourceType->emailAddress, $person->setEmailAddress(...));
    }

    public function getStatementResourcesCount(string $procedureId): int
    {
        $procedureCondition = $this->conditionFactory->propertyHasValue(
            $procedureId,
            $this->statementResourceType->procedure->id
        );

        return $this->statementResourceType->getEntityCount([$procedureCondition]);
    }

    public function getMovedStatementData(Procedure $procedure): ?MovedStatementData
    {
        if (!$this->permissions->hasPermission('feature_statement_move_to_procedure')) {
            return null;
        }

        return new MovedStatementData(
            $this->getStatementsMovedToThisProcedureCount($procedure),
            $this->getStatementsMovedFromThisProcedureCount($procedure)
        );
    }

    private function generateAccessMap(): array
    {
        $currentUser = $this->currentUser->getUser();
        $accessMap = [];
        if ($currentUser instanceof User && \in_array(Role::PRIVATE_PLANNING_AGENCY, $currentUser->getRoles())) {
            $accessMap['user'] = $currentUser;
            $accessMap['uName'] = $currentUser->getFullname();
            $accessMap['oName'] = $currentUser->getOrganisationNameLegal();
            $accessMap['uId'] = $currentUser->getIdent();
            $accessMap['oId'] = $currentUser->getOrganisationId();
        }

        return $accessMap;
    }

    /**
     * Returns the internId of the newest/youngest statement,
     * which internId is not NULL and is related to the given procedure.
     *
     * @param string $procedureId - identifies the procedure, whose related statements will be included
     *
     * @return string|null - null if be none found, otherwise the found ID as string
     */
    public function getNewestInternId($procedureId)
    {
        try {
            $id = $this->statementRepository->getNewestInternId($procedureId);
        } catch (Exception $e) {
            $this->logger->error('Get newest Intern Id of statement of the procedure: '.$procedureId.' failed: ', [$e]);

            return null;
        }

        return $id;
    }

    /**
     * Determines wheter a given internal id is unique in the scope of a procedure.
     *
     * @param string $internId
     * @param string $procedureId
     */
    public function isInternIdUniqueForProcedure($internId, $procedureId): bool
    {
        return $this->statementRepository->isInternIdUniqueForProcedure($internId, $procedureId);
    }

    /**
     * @param array<string,Statement> $statements
     */
    public function getGroupStructure(string $procedureId, AssessmentTableViewMode $viewMode, array $statements): StatementEntityGroup
    {
        $missingPriorityTitle = $this->translator->trans('priority.missing');

        if ($viewMode->is(AssessmentTableViewMode::ELEMENTS_VIEW)) {
            $groupStructure = $this->createElementsGroupStructureBobHH(
                $procedureId,
                $statements,
                $missingPriorityTitle
            );
        } elseif ($viewMode->is(AssessmentTableViewMode::TAG_VIEW)) {
            $groupStructure = $this->createTagsGroupStructure(
                $statements,
                $missingPriorityTitle
            );
        } else {
            throw new RuntimeException('invalid state');
        }

        return $groupStructure;
    }

    /**
     * @param non-empty-string $procedureId
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getExternIdsInUse(string $procedureId): array
    {
        return $this->statementRepository->getExternIdsInUse($procedureId);
    }

    /**
     * @param non-empty-string $procedureId
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getInternIdsInUse(string $procedureId): array
    {
        return $this->statementRepository->getInternIdsInUse($procedureId);
    }

    /**
     * Takes an array of statement IDs and retrieves the corresponding statement entities from doctrine.
     *
     * @param string                    $procedureId   all statements must be in this procedure
     * @param array                     $statementIds  the key and value of each entry must be the same
     * @param AssessmentTableQuery|null $filteredQuery
     *
     * @return array Same order as in the given $statementIds parameter. The key of each entry will be the
     *               statement id of its value.
     */
    protected function getStatementsWithIdsAsKey(string $procedureId, array $statementIds, $filteredQuery = null): array
    {
        $statementEntities = $this->getStatementsInProcedureWithId($procedureId, $statementIds);

        // validate the result
        $statementEntitiesCount = count($statementEntities);
        $statementIdsCount = count($statementIds);
        if ($statementEntitiesCount < $statementIdsCount) {
            $this->getLogger()->warning('At least one statement could not be found.
            It may have been deleted or moved into a different procedure.', [$procedureId]);
        }
        if ($statementEntitiesCount > $statementIdsCount) {
            $this->getLogger()->warning('Doctrine returned more results than asked for.', [$procedureId]);
        }

        // assign the objects to the corresponding keys, preserving the order of the Elasticsearch result
        foreach ($statementEntities as $statement) {
            if (!$statement instanceof Statement) {
                $this->getLogger()->warning('Not all results are statements.', [$procedureId]);
                continue;
            }
            $statementId = $statement->getId();
            if (!\array_key_exists($statementId, $statementIds)) {
                $this->getLogger()->warning('Doctrine returned statements not asked for.', [$procedureId]);
                continue;
            }
            // when query is filtered fetch number of fragments that match the query
            if ($filteredQuery instanceof AssessmentTableQuery) {
                // get fragments matching to current filter
                $filteredFragments = $this->statementFragmentService->getStatementFragmentsStatementES(
                    $statement->getId(),
                    $this->mapRequestFiltersToESFragmentFilters($filteredQuery->getFilters()),
                    $filteredQuery->getSearchWord()
                );
                // save the amount of fragments
                $statement->setFragmentsFilteredCount($filteredFragments->getTotal());
            }

            $statementIds[$statementId] = $statement;
        }

        // ensure that every value is a statement
        return \collect($statementIds)->filter(static fn ($entry) => $entry instanceof Statement)->toArray();
    }

    /**
     * Get all statements to a specific procedure.
     *
     * @param string                  $procedureId                  - identifies the procedure
     * @param array                   $filters                      - data to get more specific result
     * @param array                   $sort                         - data contains information of the order of the result
     * @param string                  $search
     * @param int                     $limit
     * @param int                     $page
     * @param array                   $searchFields
     * @param bool                    $aggregationsOnly
     * @param int                     $aggregationsMinDocumentCount
     * @param bool                    $logStatementViews
     * @param bool                    $addAllAggregations           - If true all aggregations will be used. Otherwise only those fields in $filters
     * @param list<GlobalAggregation> $customAggregations
     *
     * @throws Exception
     */
    public function getStatementsByProcedureId(
        $procedureId,
        $filters,
        $sort = null,
        $search = '',
        $limit = 0,
        $page = 1,
        $searchFields = [],
        $aggregationsOnly = false,
        $aggregationsMinDocumentCount = 1,
        $logStatementViews = true,
        $addAllAggregations = true,
        array $customAggregations = []
    ): ElasticsearchResultSet {
        try {
            // get Elasticsearch aggregations aka Userfilters
            $elasticsearchResult = $this->getElasticsearchResult(
                $filters,
                $procedureId,
                $search,
                $sort,
                $limit,
                $page,
                $searchFields,
                $aggregationsOnly,
                $aggregationsMinDocumentCount,
                $addAllAggregations,
                $customAggregations
            );

            $statementList = $this->searchService->simplifyEsStructure($elasticsearchResult, $search, $filters, $sort);
        } catch (Exception $e) {
            $this->logger->warning('get Statement List failed. Reason: ', [$e]);
            throw $e;
        }

        if ($logStatementViews && 0 < count($statementList->getResult())) {
            $this->logStatementViewed($procedureId, $statementList->getResult());
        }

        return $statementList;
    }

    /**
     * Create a log entry when statements are displayed to planning agency user.
     *
     * @param string            $procedureId
     * @param array|Statement[] $statements
     */
    public function logStatementViewed($procedureId, $statements): void
    {
        try {
            $accessMap = $this->generateAccessMap();
            if (0 !== count($accessMap) && 0 < sizeof($statements)) {
                foreach ($statements as $statement) {
                    $publicStatement = $statement instanceof Statement ? $statement->getPublicStatement() : $statement['publicStatement'];
                    $statementId = $statement instanceof Statement ? $statement->getId() : $statement['id'];
                    if (0 < count($accessMap) && 0 === \strcmp((string) $publicStatement, (string) Statement::EXTERNAL)) {
                        $this->addStatementViewedReport($procedureId, $accessMap, $statementId);
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->warning('protocol not saved: ', [$e]);
        }
    }

    /**
     * @param string $filterHash
     *
     * @throws AsynchronousStateException
     * @throws ErroneousDoctrineResult
     * @throws Exception
     */
    public function getResultsByFilterSetHash($filterHash, string $procedureId): array
    {
        $filterSet = $this->filterSetService->findHashedQueryWithHash($filterHash);
        $formValues = $this->getFormValues($this->getProcedureDefaultFilter());
        if (null === $filterSet) {
            $filterSet = $this->filterSetService->handleFilterHashWithoutRequest(
                $formValues,
                $procedureId,
                $filterHash
            );
            $filterHash = $filterSet->getHash();
        }

        /** @var AssessmentTableQuery $assessmentQuery */
        $assessmentQuery = $filterSet->getStoredQuery();

        $pagination = $this->paginationParser->parseApiPaginationProfile(
            ['number' => 1, 'size' => 1_000_000],
            (string) ToBy::createFromArray($assessmentQuery->getSorting(), 'submitDate', 'desc'),
            1_000_000
        );

        $result = $this->getResultByFilterSetHash($filterHash, $pagination)->getCurrentPageResults();

        return \collect($result)->all();
    }

    /**
     * @param array<int,class-string> $entityClassesToInclude all entites of which the Ids should be returned
     */
    public function getStatementsAndTheirFragmentsInOneFlatList(array $statements, array $entityClassesToInclude): array
    {
        return \collect($statements)
            ->flatMap(
                fn (Statement $statement): \Tightenco\Collect\Support\Collection => $this->getStatementAndItsFragmentsInOneFlatList(
                    $statement,
                    $entityClassesToInclude
                )
            )->all();
    }

    /**
     * Uses the given $filterSetHash to issue an elasticsearch request to get all statement IDs matching the filter.
     * The statement ID will be used to retrieve the corresponding statement entities from doctrine.
     *
     * @param string|null $filterSetHash
     *
     * @throws AsynchronousStateException
     * @throws ErroneousDoctrineResult
     */
    public function getResultByFilterSetHash($filterSetHash, APIPagination $pagination): DemosPlanPaginator
    {
        try {
            $filterSet = $this->filterSetService->findHashedQueryWithHash($filterSetHash);
            if (null === $filterSet) {
                throw new InvalidArgumentException('invalid filterhash');
            }

            $procedureId = $filterSet->getProcedure()->getId();
            /** @var AssessmentTableQuery $assessmentQuery */
            $assessmentQuery = $filterSet->getStoredQuery();

            $elasticsearchResult = $this->getElasticsearchResult(
                $assessmentQuery->getFilters(),
                $assessmentQuery->getProcedureId(),
                $assessmentQuery->getSearchWord(),
                $assessmentQuery->getSorting(),
                $pagination->getSize(),
                $pagination->getNumber(),
                $assessmentQuery->getSearchFields()
            );

            $isFiltered = false;
            if ('' !== $assessmentQuery->getSearchWord() || 1 < count($assessmentQuery->getFilters())) {
                $isFiltered = true;
            }

            // move the statement IDs from the Elasticsearch result into an array
            $statementIds = array_column($elasticsearchResult->getHits()['hits'], '_id', '_id');
            $statements = $this->getStatementsWithIdsAsKey(
                $procedureId,
                $statementIds,
                $isFiltered ? $assessmentQuery : null
            );
            $adapter = new DemosPlanArrayAdapter($statements);

            if ($elasticsearchResult->getPager() instanceof DemosPlanPaginator) {
                $adapter->setNbResults($elasticsearchResult->getPager()->getNbResults());
            }

            $outputPaginator = new DemosPlanPaginator($adapter);
            $outputPaginator->setCurrentPage($pagination->getNumber());
            $outputPaginator->setMaxPerPage($pagination->getSize());
            // save whether Result is filtered (by filter or search) to be able
            // to output this information in Response
            $outputPaginator->setFiltered($isFiltered);

            return $outputPaginator;
        } catch (UnexpectedDoctrineResultException $e) {
            throw new AsynchronousStateException('The relational database returned an unexpected result. It may be asynchronous with the Elasticsearch index.', 0, $e);
        }
    }

    /**
     * Load the Statements of the given Ids.
     *
     * @param string[] $statementIds - IDs of Statements which will be load
     *
     * @return Statement[] - laoded Statements
     *
     * @throws Exception
     */
    public function getStatementsByIds(array $statementIds)
    {
        try {
            $statementList = $this->statementRepository->getStatements($statementIds);
        } catch (Exception $e) {
            $this->logger->warning('get Statement List failed. Reason: ', [$e]);
            throw $e;
        }

        return $statementList;
    }

    public function integrateFilterSetIntoArray(
        HashedQuery $filterSet,
        array $rParams = [],
        bool $original = false): array
    {
        /** @var AssessmentTableQuery $assessmentTableQuery */
        $assessmentTableQuery = $filterSet->getStoredQuery();

        // Get sorting from filterSet
        if (\is_array($assessmentTableQuery->getSorting()) && 0 < count($assessmentTableQuery->getSorting())) {
            $rParams['sort'] = $assessmentTableQuery->getSorting();
        }

        // Get search from filterSet
        if ('' !== $assessmentTableQuery->getSearchWord()) {
            $rParams['search'] = $assessmentTableQuery->getSearchWord();
        }

        if (0 < count($assessmentTableQuery->getSearchFields())) {
            $rParams['searchFields'] = $assessmentTableQuery->getSearchFields();
        }

        // Get filters from loaded filterset which contains the fitlerHashValueObject
        $rParams['filters'] = $assessmentTableQuery->getFilters();

        // Switching original table
        $rParams['filters']['original'] = $original ? 'IS NULL' : 'IS NOT NULL';

        if ((!$this->permissions->hasPermission('feature_original_statements_use_pager') && $original)
            || (!$this->permissions->hasPermission('feature_assessmenttable_use_pager') && !$original)) {
            // T11850: display all original SN in list
            $rParams['request']['limit'] = 1_000_000;
        }

        // set procedureId for esRequest
        $rParams['procedure'] = $filterSet->getProcedure()->getId();

        /** @var AssessmentTableViewMode $viewMode */
        $viewMode = $assessmentTableQuery->getViewMode();
        // just hardcode the sorting for the grouped assessment table for now,
        // needs to be changed when different sortings are available
        // T15795: use default view in case the original-statement-table is requested
        if (false === $original && $viewMode->is(AssessmentTableViewMode::ELEMENTS_VIEW)) {
            $rParams['sort'] = ToBy::createArray('elementsView', 'desc');
        }

        return $rParams;
    }

    public function updateStatementFromObject($updatedStatement, $ignoreAssignment = false, $ignoreCluster = false, $ignoreOriginal = false)
    {
        return $this->updateStatement($updatedStatement, $ignoreAssignment, $ignoreCluster, $ignoreOriginal);
    }

    /**
     * Switches between legacy update method (array of data to update)
     * and updating a entire object (only the object to update).
     *
     * Will also execute various checks and generate an EntityContentChange entry.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/at_detail_view/ Wiki: Detailseite Stellungnahme/Stellungnahmengruppe
     *
     * @param Statement|array $updatedStatement - Statement as array or object
     * @param bool            $ignoreAssignment - Determines if a assignment statement will be updated regardless
     * @param bool            $ignoreCluster    - Determines if a clustered statement will be updated regardless
     * @param bool            $ignoreOriginal
     *
     * @return Statement|false|null if successful: the updated Statement will be returned
     *
     * @deprecated Use {@see StatementService::updateStatementFromObject()} instead if possible.
     *             When it is the only method remaining calling this method then this method should
     *             be removed (merged without the statement array stuff).
     */
    public function updateStatement($updatedStatement, $ignoreAssignment = false, $ignoreCluster = false, $ignoreOriginal = false)
    {
        try {
            $result = null;
            $contentChangeDiffs = [];
            $statementId = $this->entityHelper->extractId($updatedStatement);
            $currentText = $this->extractText($updatedStatement);
            $lockedByAssignmentOfHeadStatement = false;

            $currentStatementObject = $this->getStatement($statementId);

            // T12218: T12304: In case the text has changed && has obscured text
            // -> inform user, that related statement, are not obscured automatically
            // T16361: but only if statement fragments actually exist for this statement
            if (\is_array($updatedStatement)
                && \array_key_exists('text', $updatedStatement)
                && $this->editorService->hasObscuredText($currentText)
                && 0 < $currentStatementObject->getFragmentsCount()
            ) {
                $this->messageBag->add('warning', 'warning.not.obscured.text.in.fragment');
            }

            // T9081: calculate content change of update (will not be stored yet)
            if ($this->permissions->hasPermission('feature_statement_content_changes_save')) {
                $contentChangeDiffs = $this->getEntityContentChangeService()->calculateChanges($updatedStatement, Statement::class);
            }

            // check if statement to update is existing
            if (null === $currentStatementObject) {
                throw new InvalidArgumentException('Statement not found');
            }

            // check if statement is a member of a cluster
            $lockedByCluster = $this->isStatementLockedByCluster($currentStatementObject, $ignoreCluster);
            if ($lockedByCluster) {
                $this->addMessageLockedByCluster($currentStatementObject);
                $this->getLogger()->warning('Trying to update a locked by cluster statement.');
            }

            // T9701: In case of adding a statement to a headStatement, check if headStatement is claimed by current user
            if (false === $ignoreAssignment) {
                $lockedByAssignmentOfHeadStatement = $this->checkStatementAddToClusterLocked($updatedStatement);
            }

            if (\is_array($updatedStatement)) {
                foreach ($this->fileContainerRepository->getStatementFileContainers($statementId) as $fileContainer) {
                    /* @var $fileContainer FileContainer */
                    $fileIdent = $fileContainer->getFile()->getIdent();
                    $publicAllowed = isset($updatedStatement['attachmentPublicAllowed']) && \in_array($fileIdent, $updatedStatement['attachmentPublicAllowed'], true);
                    $fileContainer->setPublicAllowed($publicAllowed);
                    $this->fileContainerRepository->updateObject($fileContainer);
                }
            }

            $lockedByAssignment = $this->isStatementObjectLockedByAssignment($currentStatementObject, $ignoreAssignment);
            if ($lockedByAssignment) {
                $this->addMessageLockedByAssignment($currentStatementObject);
                $this->getLogger()->warning('Trying to update a locked by assignment statement.');
            }

            // there are fields, which are only allowed to modify on a manual statement?
            $hasManualStatementUpdateFields = $this->hasManualStatementUpdateFields($updatedStatement, $currentStatementObject);
            $updateForbidden = $hasManualStatementUpdateFields && !$currentStatementObject->isManual();
            if ($updateForbidden) {
                $this->messageBag->add('warning', 'warning.deny.update.manual.statement');
                $this->getLogger()->warning('Trying to update manualStatementUpdateFields on a normal statement.');
            }

            // is a original statement?
            $lockedByOriginal = false;
            $isOriginal = $currentStatementObject->isOriginal();
            if ($isOriginal && !$ignoreOriginal) {
                $lockedByOriginal = true;
                $this->messageBag->add('error', 'error.deny.update.original.statement');
                $this->getLogger()->warning('Trying to update a original statement.', ['backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)]);
            }

            if ($currentStatementObject->isPlaceholder()) {
                $this->messageBag->add('warning', 'warning.deny.update.placeholder.statement');
                $this->getLogger()->warning('Trying to update a placeholder statement.');
            }

            if (!$lockedByAssignment
                && !$lockedByAssignmentOfHeadStatement
                && !$lockedByCluster
                && !$updateForbidden
                && !$lockedByOriginal
                && !$currentStatementObject->isPlaceholder()) {
                $preUpdatedStatement = clone $currentStatementObject;
                if (\is_array($updatedStatement)) {
                    // @improve T12690
                    $this->getLogger()->debug('Update Statement', [$updatedStatement]);
                    $result = $this->updateStatementArray($updatedStatement);
                }

                if ($updatedStatement instanceof Statement) {
                    $result = $this->updateStatementObject($updatedStatement);
                }
                $this->eventDispatcher->dispatch(
                    new StatementUpdatedEvent($preUpdatedStatement, $currentStatementObject),
                    StatementUpdatedEventInterface::class
                );

                if (false !== $result && $this->permissions->hasPermission('feature_statement_content_changes_save')) {
                    // actually store contentChange in case of statement was updated successfully
                    $this->getEntityContentChangeService()->addEntityContentChangeEntries($currentStatementObject, $contentChangeDiffs);
                }

                return $result;
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Update Statement failed:', [$e, $e->getTraceAsString()]);

            return false;
        }

        return false;
    }

    /**
     * Tries to extract the Text from the given array|$arrayOrObject.
     * Given without key 'text', will lead into an InvalidArgumentException.
     *
     * @param Statement|array $arrayOrObject
     *
     * @throws InvalidArgumentException
     */
    private function extractText($arrayOrObject): ?string
    {
        try {
            if ($arrayOrObject instanceof CoreEntity) {
                return $arrayOrObject->getText();
            }

            if (\array_key_exists('text', $arrayOrObject)) {
                return $arrayOrObject['text'];
            }

            return null;
        } catch (Exception $e) {
            $this->getLogger()->warning(
                'Unable to get Text from given arrayOrObject. ', [$e]
            );
            throw new InvalidArgumentException('Unable to get Text from given arrayOrObject. ', 0, $e);
        }
    }

    /**
     * Uses the repository to find and return all FileContainer entities assigned to the Statement entity with the
     * given $statementId.
     *
     * @param string $statementId the ID of the Statement to get the fileContainers for
     *
     * @return array<int, FileContainer> the result from the repository
     *
     * @throws Exception
     */
    public function getFileContainersForStatement($statementId): array
    {
        /* @var $fileContainerRepo FileContainerRepository */
        $fileContainerRepo = $this->fileContainerRepository;

        return $fileContainerRepo->getStatementFileContainers($statementId);
    }

    /**
     * Creates and returns an array containing the ident properties of a FileContainer as keys and the respective
     * FileContainer as value. The FileContainers used are the ones assigned to the Statement with the given $statementId.
     *
     * @param string $statementId the ID of the Statement to get the FileContainers for
     *
     * @return FileContainer[] the array of FileContainers with their ident as array key
     *
     * @throws Exception
     */
    public function createFileHashToFileContainerMapping($statementId): array
    {
        /** @var FileContainer[] $fileContainers */
        $fileContainers = $this->getFileContainersForStatement($statementId);
        $fileHashToFileContainerMapping = [];
        foreach ($fileContainers as $fileContainer) {
            $fileHashToFileContainerMapping[$fileContainer->getFile()->getIdent()] = $fileContainer;
        }

        return $fileHashToFileContainerMapping;
    }

    /**
     * Determines if one of the fields which only can be modified on a manual statement, should be updated.
     *
     * @param statement|array $statement        - Statement as array or object
     * @param Statement       $currentStatement - current unmodified statement object, to compare with incoming update data
     *
     * @return bool - true if one of the 'critical' fields should be updated, otherwise false
     */
    private function hasManualStatementUpdateFields($statement, Statement $currentStatement): bool
    {
        $currentAuthorName = $currentStatement->getAuthorName();
        $currentSubmitterName = $currentStatement->getSubmitterName();
        $currentSubmitterEmailAddress = $currentStatement->getSubmitterEmailAddress();
        $currentDepartmentName = $currentStatement->getMeta()->getOrgaDepartmentName();
        // orgaName is submitterType:
        $currentSubmitterType = $currentStatement->getMeta()->getOrgaName();
        $currentOrgaPostalCode = $currentStatement->getOrgaPostalCode();
        $currentOrgaCity = $currentStatement->getOrgaCity();
        $currentOrgaStreet = $currentStatement->getOrgaStreet();
        $currentOrgaEmail = $currentStatement->getOrgaEmail();
        $currentAuthoredDateString = $currentStatement->getAuthoredDateString();
        $currentAuthoredDateTimeStamp = $currentStatement->getAuthoredDate();
        $currentSubmittedDateString = $currentStatement->getSubmitDateString();
        $currentSubmittedDateTimeStamp = $currentStatement->getSubmit();

        if (\is_array($statement)) {
            $statement = \collect($statement);
            if (
                ($statement->has('author_name') && $statement->get('author_name') != $currentAuthorName)
                || ($statement->has('submit_name') && $statement->get('submit_name') != $currentSubmitterName)
                || ($statement->has('submitterEmailAddress') && $statement->get('submitterEmailAddress') != $currentSubmitterEmailAddress)
                || ($statement->has('departmentName') && $statement->get('departmentName') != $currentDepartmentName)
                || ($statement->has('submitterType') && $statement->get('submitterType') != $currentSubmitterType)
                || ($statement->has('orga_postalcode') && $statement->get('orga_postalcode') != $currentOrgaPostalCode)
                || ($statement->has('orga_city') && $statement->get('orga_city') != $currentOrgaCity)
                || ($statement->has('orga_street') && $statement->get('orga_street') != $currentOrgaStreet)
                || ($statement->has('orga_email') && $statement->get('orga_email') != $currentOrgaEmail)
                || ($statement->has('authoredDate') && $statement->get('authoredDate') != $currentAuthoredDateString)
                || ($statement->has('submittedDate') && $statement->get('submittedDate') != $currentSubmittedDateString)
            ) {
                return true;
            }
        }

        if ($statement instanceof Statement) {
            if (
                $statement->getAuthorName() != $currentAuthorName
                || $statement->getSubmitterName() != $currentSubmitterName
                || $statement->getMeta()->getOrgaDepartmentName() != $currentDepartmentName
                || $statement->getMeta()->getOrgaName() != $currentSubmitterType
                || $statement->getSubmitterEmailAddress() != $currentSubmitterEmailAddress
                || $statement->getOrgaPostalCode() != $currentOrgaPostalCode
                || $statement->getOrgaCity() != $currentOrgaCity
                || $statement->getOrgaStreet() != $currentOrgaStreet
                || $statement->getOrgaEmail() != $currentOrgaEmail
                || $statement->getAuthoredDate() != $currentAuthoredDateTimeStamp
                || $statement->getSubmit() != $currentSubmittedDateTimeStamp
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if the given statement is "locked" because of assigned to another user.
     *
     * @param bool $ignoreAssignment overrides assignment state and only checks for enabled permission
     *
     * @return bool true if the given statement is locked, otherwise false
     */
    public function isStatementObjectLockedByAssignment(Statement $statement, $ignoreAssignment = false): bool
    {
        return $this->isStatementLockedByAssignment($statement, $ignoreAssignment);
    }

    /**
     * Determines if the given statement is "locked" because of assigned to another user.
     *
     * @param Statement|array $statement
     * @param bool            $ignoreAssignment overrides assignment state and only checks for enabled permission
     *
     * @return bool - true if the given statement is locked, otherwise false
     *
     * @deprecated Use {@see isStatementObjectLockedByAssignment()} instead if possible to get rid of array/object ambiguous format complexity.
     *             Remove it as soon as it is used by {@see isStatementObjectLockedByAssignment()} only.
     */
    public function isStatementLockedByAssignment($statement, $ignoreAssignment = false): bool
    {
        if ($ignoreAssignment) {
            return false;
        }

        if (!$this->permissions->hasPermission('feature_statement_assignment')) {
            return false;
        }

        if ($this->isStatementAssignedToCurrentUser($statement)) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if one of the following cases is true:
     * <ul>
     *  <li>the feature_statement_assignment is disabled (statements can't
     * be assigned)
     *  <li>the feature_statement_assignment is enabled and the statement is
     * assigned to the current user
     * </ul>.
     *
     * @param Statement $statement
     */
    public function hasCurrentUserStatementAssignWriteRights($statement): bool
    {
        return !$this->permissions->hasPermission('feature_statement_assignment')
            || $this->assignService->isStatementObjectAssignedToCurrentUser($statement);
    }

    /**
     * Determines if the given statement is "locked" because of it is a member of a cluster.
     *
     * @param bool $ignoreCluster
     *
     * @return bool - true if the given statement is locked, otherwise false
     */
    protected function isStatementLockedByCluster(Statement $statement, $ignoreCluster = false): bool
    {
        return
            !$ignoreCluster
            && $this->permissions->hasPermission('feature_statement_cluster')
            && $statement->isInCluster();
    }

    /**
     * Generate & add message to the MessageBag.
     *
     * @throws MessageBagException
     */
    public function addMessageLockedByAssignment(Statement $statement): void
    {
        $assignedUser = $statement->getAssignee();
        if (null === $assignedUser) {
            $this->messageBag->add(
                'warning', 'warning.statement.needLock',
                ['externId' => $statement->getExternId()]
            );
        } else {
            $this->messageBag->add(
                'warning', 'warning.statement.assigned.to',
                ['name' => $assignedUser->getName(), 'organisation' => $assignedUser->getOrga()->getName()]
            );
        }
    }

    /**
     * Generate & add message to the MessageBag.
     *
     * @throws MessageBagException
     */
    protected function addMessageLockedByCluster(Statement $statement): void
    {
        $headStatement = $statement->getHeadStatement();
        $this->messageBag->add(
            'error', 'error.statement.clustered.in',
            ['headStatementId' => $headStatement->getExternId()]
        );
    }

    /**
     * Check if the given Statement is assigned to the current user.
     *
     * @param Statement|array $statement
     *
     * @return bool true if the given Statement is assigned to the current user, otherwise false;
     *              also false if an array with an unknown ID was given
     *
     * @throws InvalidArgumentException Thrown if no ID could be extracted from the given $statement
     *
     * @deprecated if you got an actual statement object use {@see AssignService::isStatementObjectAssignedToCurrentUser()} instead
     */
    public function isStatementAssignedToCurrentUser($statement): bool
    {
        if (\is_array($statement)) {
            $statementId = $this->entityHelper->extractId($statement);
            $statement = $this->getStatement($statementId);
            if (null === $statement) {
                return false;
            }
        }

        if ($statement instanceof Statement) {
            return $this->assignService->isStatementObjectAssignedToCurrentUser($statement);
        }

        $type = gettype($statement);

        throw new InvalidArgumentException("Given statement is neither of type array nor Statement but {$type}.");
    }

    /**
     * Update Statement.
     *
     * @return Statement|array|false
     */
    protected function updateStatementArray(array $data)
    {
        try {
            if (!isset($data['ident'])) {
                return false;
            }

            // Create and use versions of paragraph and Element and recommendation
            $data = $this->getEntityVersions($data);

            $statement = $this->statementRepository->update($data['ident'], $data);

            if (isset($data['files'])) {
                $statement = $this->addFilesToStatement($data['files'], $statement);
            }

            try {
                $entry = $this->statementReportEntryFactory->createUpdateEntry($statement);
                $this->reportService->persistAndFlushReportEntries($entry);
                $this->logger->debug('generate report of updateStatement(). ReportID: '.$entry->getIdentifier());
            } catch (Exception $e) {
                $this->logger->warning('Add Report in updateStatement() failed Message: ', [$e]);
            }

            return $statement;
        } catch (Exception $e) {
            $this->logger->error('Update Statement failed:', [$e]);

            return false;
        }
    }

    /**
     * Add a report entry to the DB.
     *
     * @param string $procedureId
     * @param array  $accessMap
     * @param string $statementId
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     */
    private function addStatementViewedReport($procedureId, $accessMap, $statementId): void
    {
        // only log if user is known
        if (!\array_key_exists('user', $accessMap)) {
            return;
        }
        $alreadyLogged = $this->reportService
            ->statementViewLogged($procedureId, $accessMap['user'], $statementId);

        // logging access once is enough
        if ($alreadyLogged) {
            return;
        }

        $entry = $this->statementReportEntryFactory->createViewedEntry(
            $statementId,
            $procedureId,
            $accessMap
        );

        $this->reportService->persistAndFlushReportEntries($entry);
    }

    /**
     * Returns all Statements.
     *
     * @return Statement[]
     */
    public function getAllStatements()
    {
        try {
            return $this->statementRepository->findAll();
        } catch (Exception $e) {
            $this->getLogger()->warning($e);

            return [];
        }
    }

    /**
     * Returns the current assigned user of the given statement.
     * Returns null if no user is assigned or the given statement was not found.
     *
     * @param string $statementId
     *
     * @return User|null
     */
    public function getAssigneeOfStatement($statementId)
    {
        $statement = $this->getStatement($statementId);

        return null === $statement ? null : $statement->getAssignee();
    }

    /**
     * Get all Statements assigned by a user.
     *
     * @return array<int,Statement>|null
     */
    public function getAssignedStatements(User $user): ?array
    {
        try {
            $query = $this->statementRepository->createFluentQuery();
            $query->getConditionDefinition()
                ->assignedToUser($user);

            return $query->getEntities();
        } catch (Exception $e) {
            $this->logger->error('Could not get assigend Statements', [$e]);

            return null;
        }
    }

    /**
     * Ruft eine Stellungnahme zu einem Verfahren ab.
     *
     * @param string $ident
     *
     * @throws Exception
     *
     * @deprecated Use {@link StatementService::getStatement()} instead
     */
    public function getStatementByIdent($ident): array
    {
        $accessMap = $this->generateAccessMap();

        $statement = $this->statementRepository->get($ident);

        if (null === $statement) {
            $this->getLogger()->warning('Kein Statement gefunden!',
                ['id' => $ident, 'backtrace' => debug_backtrace()]
            );

            return [];
        }
        try {
            if (0 < count($accessMap) && 0 === \strcmp($statement->getPublicStatement(), (string) Statement::EXTERNAL)) {
                try {
                    $this->addStatementViewedReport($statement->getPId(), $accessMap, $statement->getId());
                } catch (Exception $e) {
                    $this->logger->warning('Add Report in getStatementByIdent() failed Message: ', [$e]);
                }
            }
        } catch (Exception $e) {
            $this->logger->warning('protocol not saved: ', [$e]);
        }

        // does not return null as $statement is not null
        return $this->convertToLegacy($statement);
    }

    public function getStatement($statementId): ?Statement
    {
        try {
            $statement = $this->statementRepository->get($statementId);
            if (null === $statement) {
                $this->getLogger()->warning('Kein Statement gefunden!',
                    ['id' => $statementId, 'backtrace' => debug_backtrace()]
                );

                return null;
            }

            // this is usually done via DoctrineStatementListener but sometimes this is not called
            // maybe when doctrine thinks that nothing changed.
            $files = $this->fileService->getEntityFileString(Statement::class, $statement->getId(), 'file');
            // add files to statement Entity
            $statement->setFiles($files);

            try {
                $accessMap = $this->generateAccessMap();
                if (0 < count($accessMap) && 0 === \strcmp($statement->getPublicStatement(), (string) Statement::EXTERNAL)) {
                    try {
                        $this->addStatementViewedReport($statement->getPId(), $accessMap, $statement->getId());
                    } catch (Exception $e) {
                        $this->logger->warning('Add Report in getStatement() failed Message: ', [$e]);
                    }
                }
            } catch (Exception $e) {
                $this->logger->warning('Add Report in getStatement() failed Message: ', [$e]);
            }

            return $statement;
        } catch (Exception $e) {
            $this->getLogger()->error($e);
            $this->getLogger()->warning('No Statement found for Id '.$statementId);

            return null;
        }
    }

    /**
     * Copy a specific statement.
     *
     * @param bool $createReport if this parameter is true, a copy-reportEntry will be generated
     *
     * @return Statement|false
     *
     * @throws CopyException
     * @throws ClusterStatementCopyNotImplementedException
     */
    public function copyStatementWithinProcedure(string $statement, bool $createReport = true)
    {
        try {
            $statementObject = $this->statementRepository->get($statement);
            if (null === $statementObject) {
                $this->getLogger()->warning('Could not find statement '.DemosPlanTools::varExport($statementObject, true));

                return false;
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not copy statement ', [$e]);

            return false;
        }

        return $this->statementCopier->copyStatementObjectWithinProcedure($statementObject, $createReport);
    }

    /**
     * @param array[] $statements array of statements in their elasticsearch array format
     *
     * @return array[] the given array but to each statement an 'attachment' field was added
     *                 containing the return of {@link Statement::getAttachments()}
     *
     * @throws Exception
     */
    public function addSourceStatementAttachments(array $statements): array
    {
        $entities = $this->elasticsearchStatementsToObjects($statements);

        return \array_map(static function (array $statement) use ($entities): array {
            $statement['attachments'] = array_filter(
                $entities[$statement['id']]->getAttachments()->getValues(),
                static fn (StatementAttachment $attachment) => StatementAttachment::SOURCE_STATEMENT === $attachment->getType()
            );

            return $statement;
        }, $statements);
    }

    /**
     * Add user vote to statement.
     */
    public function addVote(string $statementId, User $user): StatementVote|bool
    {
        try {
            // only one vote per user per statement
            $vote = $this->statementVoteRepository->findOneBy([
                'user'      => $user->getId(),
                'statement' => $statementId,
                'deleted'   => false,
                'active'    => true, ]);

            // user already voted this statement?
            if ($vote instanceof StatementVote) {
                $this->messageBag->add('error', 'error.statement.marked.voted');

                return $vote;
            }
            $statement = $this->statementRepository->get($statementId);

            $newVote = new StatementVote();
            $newVote->setStatement($statement);
            $newVote->setUser($user);
            $newVote->setFirstName($user->getFirstname());
            $newVote->setLastName($user->getLastname());

            $existingVotes = $statement->getVotes();
            $existingVotes->add($newVote);

            $statement->setVotes($existingVotes->toArray());

            $this->statementRepository->updateObject($statement);

            $this->messageBag->add('confirm', 'confirm.statement.marked.voted');

            return $newVote;
        } catch (Exception $e) {
            $this->logger->error('Create new StatementVote failed:', [$e]);
            $this->messageBag->add('error', 'error.statement.marked.voted');

            return false;
        }
    }

    /**
     * (Anonymous) User likes statement.
     *
     * @param string $statementId ID der Stellungnahme
     *
     * @return StatementLike|false
     */
    public function addLike($statementId, User $user = null)
    {
        try {
            $em = $this->getDoctrine()->getManager();

            $data = [
                'statement' => $em->getReference(Statement::class, $statementId),
            ];
            if ($user instanceof User) {
                $data['user'] = $em->getReference(User::class, $user->getId());
            }

            return $this->statementRepository->addLike($data);
        } catch (Exception $e) {
            $this->logger->error('Create new StatementLike failed:', [$e]);

            return false;
        }
    }

    /**
     * Die Schlussmitteilung wurde gesendet.
     *
     * @param string $ident
     *
     * @return bool
     */
    public function setSentAssessment($ident)
    {
        $data = [
            'ident'          => $ident,
            'sentAssessment' => true,
        ];
        $this->updateStatement($data, true);

        return true;
    }

    /**
     * Convert StatementObject to legacy.
     */
    public function convertToLegacy(?Statement $statement): ?array
    {
        if (null === $statement) {
            return null;
        }
        $statementId = $statement->getId();
        try {
            $statementAttributes = $statement->getStatementAttributes();
            $numberOfAnonymVotes = $statement->getNumberOfAnonymVotes();
            $submitterEmailAddress = $statement->getSubmitterEmailAddress();
            $createdByInstitution = $statement->isCreatedByInvitableInstitution();
            $createdByCitizen = $statement->isCreatedByCitizen();
            $votesNum = $statement->getVotesNum();
            $statement = $this->entityHelper->toArray($statement);

            $statement['createdByToeb'] = $createdByInstitution;
            $statement['createdByCitizen'] = $createdByCitizen;
            $statement['submitterEmailAddress'] = $submitterEmailAddress;

            $statement['numberOfAnonymVotes'] = $numberOfAnonymVotes;
            $statement['votesNum'] = $votesNum;
            $statement['categories'] = [];
            if ($statement['element'] instanceof Elements) {
                $statement['element'] = $this->serviceElements->convertElementToArray($statement['element']);
            }
            if ($statement['paragraph'] instanceof ParagraphVersion) {
                try {
                    // Legacy wird der Paragraph und nicht ParagraphVersion zurückgegeben!
                    $parentParagraph = $statement['paragraph']->getParagraph();
                    $statement['paragraph'] = $this->entityHelper->toArray($parentParagraph);
                } catch (Exception) {
                    // Einige alte Einträge verweisen möcglicherweise noch nicht auf eine ParagraphVersion
                    $this->logger->error(
                        'No ParagraphVersion found for Id '.DemosPlanTools::varExport($statement['paragraph']->getId(), true)
                    );
                    unset($statement['paragraph']);
                    $statement['paragraphId'] = null;
                }
            }
            if ($statement['procedure'] instanceof Procedure) {
                try {
                    $statement['procedure'] = $this->entityHelper->toArray($statement['procedure']);
                    $statement['procedure']['settings'] = $this->entityHelper->toArray($statement['procedure']['settings']);
                    $statement['procedure']['organisation'] = $this->entityHelper->toArray($statement['procedure']['organisation']);
                    $statement['procedure']['planningOffices'] =
                        isset($statement['procedure']['planningOffices']) ?
                            $this->entityHelper->toArray($statement['procedure']['planningOffices']) :
                            [];
                    $statement['procedure']['planningOfficeIds'] =
                        isset($statement['procedure']['planningOffices']) ?
                            $this->entityHelper->toArray($statement['procedure']['planningOffices']) :
                            [];
                } catch (Exception $e) {
                    $this->logger->warning(
                        'Could not convert  Statement Procedure to Legacy. Statement: '.DemosPlanTools::varExport(
                            $statement['id'],
                            true
                        ).$e
                    );
                }
            }
            if ($statement['organisation'] instanceof Orga) {
                try {
                    $statement['organisation'] = $this->entityHelper->toArray($statement['organisation']);
                } catch (Exception $e) {
                    $this->logger->warning(
                        'Could not convert Statement Organisation to Legacy. Statement: '.DemosPlanTools::varExport(
                            $statement['id'],
                            true
                        ).$e
                    );
                }
            }
            if ($statement['meta'] instanceof StatementMeta) {
                try {
                    $statement['meta'] = $this->entityHelper->toArray($statement['meta']);
                } catch (Exception $e) {
                    $this->logger->warning(
                        'Could not convert Statement Meta to Legacy. Statement: '.DemosPlanTools::varExport($statement['id'], true).$e
                    );
                }
            }

            // Enter StatementAttributes
            if ((is_countable($statementAttributes) ? count($statementAttributes) : 0) > 0) {
                $statement['statementAttributes'] = [];
            }
            foreach ($statementAttributes as $sa) {
                if (isset($statement['statementAttributes'][$sa->getType()])) {
                    if (\is_array($statement['statementAttributes'][$sa->getType()])) {
                        $statement['statementAttributes'][$sa->getType()][] = $sa->getValue();
                    } else {
                        $v = $statement['statementAttributes'][$sa->getType()];
                        $statement['statementAttributes'][$sa->getType()] = [$v];
                    }
                } else {
                    $statement['statementAttributes'][$sa->getType()] = $sa->getValue();
                }
            }

            // Lege ein mit der Stellungnahme verknüpftes SingleDocument auf oberster Ebene in das Array
            if (null !== $statement['documentId']) {
                $singleDocument = $this->singleDocumentVersionRepository->get($statement['documentId']);
                // Angezeigt wird das parent Singledocument
                $statement['document'] = $this->entityHelper->toArray($singleDocument->getSingleDocument());
            } else {
                unset($statement['documentId']);

                if (\array_key_exists('documentTitle', $statement)) {
                    unset($statement['documentTitle']);
                }

                if (\array_key_exists('document', $statement)) {
                    unset($statement['document']);
                }
            }
            $votes = [];
            if ($statement['votes'] instanceof Collection) {
                $votesArray = $statement['votes']->toArray();
                foreach ($votesArray as $vote) {
                    $votes[] = $this->dateHelper->convertDatesToLegacy($this->entityHelper->toArray($vote));
                }
            }
            $statement['votes'] = $votes;

            $statement = $this->dateHelper->convertDatesToLegacy($statement);
        } catch (Exception $e) {
            $this->logger->warning(
                'Could not convert Statement to Legacy.',
                [$statementId, $e]
            );
        }

        return $statement;
    }

    /**
     * Create and use versions of Paragraph & SingleDoc.
     *
     * @return array $data
     *
     * @throws Exception
     */
    protected function getEntityVersions(array $data): array
    {
        $em = $this->getDoctrine()->getManager();
        $currentStatement = $this->getStatement($data['ident']);

        if (\array_key_exists('paragraph', $data) && $data['paragraph'] instanceof Paragraph
            && $data['paragraph']->getId() != $currentStatement->getParagraphId()) {
            $data['paragraph'] = $this->paragraphService->createParagraphVersion($data['paragraph']);
        }
        // Wenn das Statement einen Absatz hat lege eine Version an, wenn sich der Absatz verändert hat
        if (\array_key_exists('paragraphId', $data)
            && 0 < \strlen((string) $data['paragraphId'])
            && $data['paragraphId'] != $currentStatement->getParagraphId()) {
            $data['paragraph'] = $this->paragraphService->createParagraphVersion(
                $em->getReference(Paragraph::class, $data['paragraphId'])
            );
        }

        if (\array_key_exists('document', $data) && $data['document'] instanceof SingleDocument
            && $data['document']->getId() != $currentStatement->getDocumentId()) {
            $data['document'] = $this->singleDocumentService->createSingleDocumentVersion($data['document']);
        }

        if (\array_key_exists('documentId', $data)
            && 0 < \strlen((string) $data['documentId'])
            && $data['documentId'] != $currentStatement->getDocumentId()) {
            $data['document'] = $this->singleDocumentService->createSingleDocumentVersion(
                $em->getReference(SingleDocument::class, $data['documentId'])
            );
        }

        if (\array_key_exists('recommendation', $data) && $data['recommendation'] != $currentStatement->getRecommendation()) {
            // Only save a version when there actually was a recommendationtext before
            $user = $this->currentUser->getUser();
            try {
                $doctrineUser = $this->userRepository->get($user->getIdent());
            } catch (NoResultException) {
                $doctrineUser = null;
            }
            $orga = $user->getOrga()->getNameLegal();
            $role = $doctrineUser->getDplanroles()[0]->getName();
            $recommendation = $data['recommendation'];
            $this->createRecommendationVersion($currentStatement, $recommendation, $user, $orga, $role);
        }

        return $data;
    }

    /**
     * Returns a statement.
     */
    public function getSegmentableStatement(string $procedureId, User $user): ?Statement
    {
        $resumableStatement = $this->statementRepository->getFirstClaimedSegmentableStatement($procedureId, $user);
        if (null !== $resumableStatement) {
            return $resumableStatement;
        }

        return $this->statementRepository->getFirstUnclaimedSegmentableStatement($procedureId);
    }

    public function getSegmentableStatementsCount(string $procedureId, User $user): int
    {
        return $this->statementRepository->getSegmentableStatementsCount($procedureId, $user);
    }

    /**
     * @return array<int,Statement>
     */
    public function getSegmentedStatements(string $procedureId, User $user): array
    {
        $query = $this->statementRepository->createFluentQuery();
        $query->getConditionDefinition()
            ->inProcedureWithId($procedureId)
            ->assignedToUser($user)
            ->hasSegments($procedureId);

        return $query->getEntities();
    }

    /**
     * @return StatementMeta[]
     */
    public function getAllStatementMetas(): array
    {
        return $this->statementRepository->getAllStatementMetas();
    }

    /**
     * @return StatementVersionField[]
     */
    public function getAllStatementVersionFields(): array
    {
        return $this->statementRepository->getAllStatementVersionFields();
    }

    /**
     * The result will be an associative array from the ID of an statement to the corresponding
     * statement entity object. The order in the array will be the same as it was in the given
     * array of elasticsearch statements, however statements that can't be found in the
     * database are removed from the result array.
     *
     * @param array[] $statements
     *
     * @return Statement[]
     *
     * @throws Exception
     */
    public function elasticsearchStatementsToObjects(array $statements): array
    {
        $statementIds = array_column($statements, 'id');
        $statements = $this->getStatementsByIds($statementIds);
        // keep the order of the elasticsearch result
        $statementsByIds = array_fill_keys($statementIds, null);
        foreach ($statements as $statement) {
            $statementsByIds[$statement->getId()] = $statement;
        }

        // remove items for statements that were returned by the ES but meanwhile deleted
        // in the database
        return array_filter($statementsByIds, static fn (?Statement $statement) => null !== $statement);
    }

    protected function getPriorityAreaService(): PriorityAreaService
    {
        return $this->priorityAreaService;
    }

    /**
     * @return Type
     */
    protected function getEsStatementType()
    {
        return $this->esStatementType;
    }

    /**
     * @return array
     */
    public function getPaginatorLimits()
    {
        return $this->paginatorLimits;
    }

    /**
     * @param array $paginatorLimits
     */
    public function setPaginatorLimits($paginatorLimits)
    {
        $this->paginatorLimits = $paginatorLimits;
    }

    /**
     * @param Index $esStatementType
     */
    public function setEsStatementType($esStatementType)
    {
        $this->esStatementType = $esStatementType;
    }

    /**
     * @param Statement $statement
     * @param string    $recommendation
     * @param User      $user
     * @param string    $orgaDisplayName
     * @param string    $role
     *
     * @throws Exception
     */
    protected function createRecommendationVersion($statement, $recommendation, $user, $orgaDisplayName, $role): StatementVersionField
    {
        $versionUser = null !== $user ? $user->getFullname().', ' : '';
        $versionUser .= $orgaDisplayName.', ';
        $versionUser .= $role;
        $data = [
            'stId'     => $statement->getId(),
            'userName' => $versionUser,
            'name'     => 'recommendation',
            'type'     => 'text',
            'value'    => $recommendation,
        ];

        return $this->statementRepository->addRecommendationVersion($data);
    }

    /**
     * @param string $statementId
     *
     * @deprecated use {@link Statement::isManual()} instead
     */
    public function isManualStatement($statementId)
    {
        try {
            return $this->statementRepository->isManualStatement($statementId);
        } catch (Exception $e) {
            $this->logger->error('Check statement for manual failed:', [$e]);
        }
    }

    /**
     * Returns default filter that are called for an unconfigured assessmenttable.
     */
    public function getProcedureDefaultFilter(): array
    {
        $viewModeString = $this->globalConfig->getAssessmentTableDefaultViewMode();

        return [
            'filters'       => [],
            'search_fields' => [],
            'search_word'   => '',
            'sort'          => 'submitDate:desc',
            'view_mode'     => $viewModeString,
        ];
    }

    /**
     * @param string   $procedureId
     * @param string[] $statementIds
     *
     * @return Statement[]
     */
    public function getStatementsInProcedureWithId($procedureId, $statementIds)
    {
        return $this->statementRepository->findBy(['id' => $statementIds, 'procedure' => $procedureId]);
    }

    /**
     * @param string[] $statementsIds
     * @param string[] $fragmentIds
     *
     * @throws Exception
     */
    public function createElementsGroupStructure(
        string $procedureId,
        array $statementsIds,
        array $fragmentIds
    ): StatementEntityGroup {
        $statements = $this->getStatementsByIds($statementsIds);
        $fragments = $this->statementFragmentRepository->getFragmentsById($fragmentIds);
        $entities = \array_merge($statements, $fragments);

        $groupingFields = [
            'getElementId'                           => 'getElementTitle',
            'getParagraphParentIdOrDocumentParentId' => 'getParagraphParentTitleOrDocumentParentTitle',
        ];
        // do not show subgroups for elements without any paragraphs and documents (like 'Gesamtstellungnahme')
        $notDividableGroupKeys = $this->serviceElements->getElementsIdsWithoutParagraphsAndDocuments($procedureId);
        // do not show subgroups for 'Keine Zuordnung'-groups
        $notDividableGroupKeys[] = EntityGrouper::MISSING_GROUP_KEY;
        $group = $this->statementEntityGrouper->createGroupStructureFromEntities(
            $entities,
            $groupingFields,
            ['getElementId' => $notDividableGroupKeys]
        );

        // the first sorting sorts by the group titles (alpha numerical)
        $this->statementEntityGrouper->sortSubgroupsAtAllLayers(
            $group,
            new TitleGroupsSorter()
        );
        // the second sorting keeps the group title sorting but places
        // groups containing the 'missing' at the end
        $this->statementEntityGrouper->sortSubgroupsAtAllLayers(
            $group,
            new KeysAtEndSorter([EntityGrouper::MISSING_GROUP_KEY])
        );
        $this->statementEntityGrouper->sortEntriesAtAllLayers(
            $group,
            new ClusterCitizenInstitutionSorter()
        );

        return $group;
    }

    /**
     * This grouping was originally developed for the BobHH project.
     *
     * If it becomes more common across projects it can be renamed to distinguish it from other
     * groupings doing similar but nonetheless different things. Ideally the method name
     * would reflect what it does instead for whom.
     *
     * @param Statement[] $statements
     *
     * @throws Exception
     */
    public function createElementsGroupStructureBobHH(string $procedureId, array $statements, string $missingGroupTitle): StatementEntityGroup
    {
        $group = $this->createPriorityGroup($missingGroupTitle);
        $groupingFields = [
            'getPriority'          => 'getPriority',
            'getElementId'         => 'getElementTitle',
            'getParagraphParentId' => 'getParagraphParentTitle',
        ];
        // do not show subgroups for elements without any paragraphs and documents (like 'Gesamtstellungnahme')
        $notDividableGroupKeys = $this->serviceElements->getElementsIdsWithoutParagraphsAndDocuments($procedureId);
        // do not show subgroups for 'Keine Zuordnung'-groups
        $notDividableGroupKeys[] = EntityGrouper::MISSING_GROUP_KEY;
        $this->statementEntityGrouper->fillEntitiesIntoGroupStructure(
            $statements,
            $groupingFields,
            $group,
            ['getElementId' => $notDividableGroupKeys]
        );

        // sort the paragraphs by their order
        $this->statementEntityGrouper->sortSubgroupsAtDepth(
            $group,
            new ParagraphOrderSorter($this->paragraphService),
            2
        );

        $noManualSortElementsIds = $this->serviceElements->getHiddenElementsIdsForProcedureId($procedureId);
        // sorting only needed if there are elements to be moved to the end
        if (0 < count($noManualSortElementsIds)) {
            // sort hidden elements to end: sort elements not manually sortable in the admin list (because hidden) at the end
            // the sorting is applied to all layers but only the elements layer will be affected as
            // the other two layers do not contain groups with element IDs
            $this->statementEntityGrouper->sortSubgroupsAtAllLayers(
                $group,
                new KeysAtEndSorter($noManualSortElementsIds)
            );
        }

        // sort missing paragraphs at beginning
        $this->statementEntityGrouper->sortSubgroupsAtAllLayers(
            $group,
            new KeysAtStartSorter([EntityGrouper::MISSING_GROUP_KEY])
        );

        return $group;
    }

    /**
     * Uses the priority and the tags of the given statements to group them.
     *
     * On the first level will be a subgroup for statements without
     * priority, a subgroup for statements with "A-Punkt"-priority and a subgroup
     * for statements with "B-Punkt"-priority. On the second level will a subgroup
     * for statements without tags and subgroups for each tag that exists in the given set of
     * statements. Inside these subgroups will be the corresponding subset of statements.
     *
     * Beside the two subgroups for statements without priority/tags, which are sorted at the top,
     * all subgroups will be sorted by their priority/tag title (not to be confused with the
     * sorting of the statements inside the subgroups.
     *
     * @param Statement[] $statements
     */
    public function createTagsGroupStructure(array $statements, string $missingGroupTitle): StatementEntityGroup
    {
        $group = $this->createPriorityGroup($missingGroupTitle);
        $groupingFields = [
            'getPriority' => 'getPriority',
            'getTags'     => 'getTags',
        ];
        $this->statementEntityGrouper->fillEntitiesIntoGroupStructure($statements, $groupingFields, $group);
        // because multiple tags are given for one statement, sorting the statements in
        // ES is not enough to get any order for the tag groups,
        // hence in case of the TagView we resort here by name
        $this->statementEntityGrouper->sortSubgroupsAtAllLayers(
            $group,
            new TitleGroupsSorter()
        );
        // sort 'missing' items (not assigned to tags) at top
        $this->statementEntityGrouper->sortSubgroupsAtAllLayers(
            $group,
            new KeysAtStartSorter([EntityGrouper::MISSING_GROUP_KEY])
        );

        return $group;
    }

    /**
     * @param string $missingGroupTitle
     */
    protected function createPriorityGroup($missingGroupTitle): StatementEntityGroup
    {
        $group = new StatementEntityGroup();
        $group->setSubgroup(EntityGrouper::MISSING_GROUP_KEY, new StatementEntityGroup($missingGroupTitle));
        $group->setSubgroup('B-Punkt', new StatementEntityGroup('B-Punkt'));
        $group->setSubgroup('A-Punkt', new StatementEntityGroup('A-Punkt'));

        return $group;
    }

    /**
     * Abwaegungstabelle - Speicherung von Formulardaten.
     *
     * @param array $rParams
     */
    public function getFormValues($rParams): array
    {
        $viewModeString = $this->globalConfig->getAssessmentTableDefaultViewMode();
        $resParams = [
            'filters'      => $this->collectFilters($rParams),
            'search'       => '',
            'searchFields' => [],
            'request'      => $this->collectRequest($rParams),
            'items'        => $this->collectItems($rParams),
            'view_mode'    => AssessmentTableViewMode::create($viewModeString),
            'sort'         => $this->getSorting($rParams),
        ];

        foreach ($rParams as $key => $value) {
            if ('Suchbegriff eingeben' !== $value
                && str_contains($key, 'search_word')) {
                $resParams['search'] = $value;
            }
            if (str_contains($key, 'search_fields')) {
                foreach ($value as $v) {
                    $resParams['searchFields'][] = $v;
                }
            }
            if ('r_view_mode' === $key && '' !== $rParams[$key]) {
                $resParams['view_mode'] = AssessmentTableViewMode::create($rParams[$key]);
            }
        }

        return $resParams;
    }

    /**
     * @param array<string, mixed> $rParams
     * @param array<string, mixed> $resParams
     *
     * @return array<string, array<string, string>>
     *
     * @deprecated please use {@link getSortingJsonFormat(array $rParams)} to adapt to JsonApi sorting standard {@link https://jsonapi.org/format/#fetching-sorting}
     */
    public function maybeAddSort(array $rParams, array $resParams): array
    {
        $sort = ToBy::createEmptyArray();
        foreach ($rParams as $key => $value) {
            if ('' !== $value && str_contains($key, 'sort')) {
                [$by, $to] = explode(':', (string) $value);
                $sort = ToBy::createArray($by, $to);
            }
        }

        if ([] !== $sort) {
            $resParams['sort'] = $sort;
        }

        return $resParams;
    }

    /**
     * @param array<string,mixed> $rParams
     *
     * @return array<int,mixed>
     */
    public function collectItems(array $rParams): array
    {
        $items = [];
        foreach ($rParams as $key => $value) {
            if ('' !== $value && str_contains($key, 'r_ident')) {
                $items[] = $value;
            }
            if (str_contains($key, 'item_check')) {
                foreach ($value as $v) {
                    $items[] = $v;
                }
            }
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $rParams
     *
     * @return array<string,array|string>
     */
    public function collectRequest(array $rParams): array
    {
        return \collect($rParams)->filter(
            static function ($value, string $key) {
                if ('r_submitterEmailAddress' === $key) {
                    return str_starts_with($key, 'r_') && (\is_string($value) || (\is_array($value) && 0 < count($value)));
                } else {
                    return str_starts_with($key, 'r_') && ((\is_string($value) && '' !== $value) || (\is_array($value) && 0 < count($value)));
                }
            }
        )->mapWithKeys(
            static function ($stringOrArrayValue, string $key) {
                // Use substr without r_ as key
                $requestKey = substr($key, 2);

                return [$requestKey => $stringOrArrayValue];
            }
        )->toArray();
    }

    /**
     * @param array<string,mixed> $rParams
     *
     * @return array<string,array>
     */
    public function collectFilters(array $rParams): array
    {
        return \collect($rParams)->filter(static fn ($value, string $key) => \is_array($value) && str_contains($key, 'filter_') && 0 < count($value))->mapWithKeys(static function (array $value, string $key) {
            $filterKey = str_replace('filter_', '', $key);

            return [$filterKey => $value];
        })->toArray();
    }

    /**
     * Returns the query based on search field.
     *
     * @param string $search
     *
     * @return array|AbstractQuery|null
     */
    private function getSearchQuery(string $procedureId, $search, array $searchFields, int $aggregationsMinDocumentCount)
    {
        $searchQuery = null;

        // store variable in class property to avoid passing it into methods
        $this->searchService->setAggregationsMinDocumentCount($aggregationsMinDocumentCount);

        // List may only be generated if procedure is set
        if (null === $procedureId) {
            return [];
        }

        // GET QUERY (INI)
        // userFilters may come in in strange formats
        if (\is_array($searchFields) && 1 === count($searchFields) && '' === $searchFields[0]) {
            $searchFields = [];
        }
        $this->profilerStart('ES');

        //
        // if a Searchterm is set use it
        if (\is_string($search) && 0 < \strlen($search)) {
            $availableSearchfields = [
                'text'                    => 'text.text',
                'oName'                   => 'oName^0.2',
                'dName'                   => 'dName^0.2',
                'uName'                   => 'uName^0.2',
                'elementTitle'            => 'elementTitle.text',
                'documentTitle'           => 'documentTitle.text',
                'paragraphTitle'          => 'paragraphTitle.text',
                'recommendation'          => 'recommendation.text',
                'municipalityNames'       => 'municipalityNames',
                'internId'                => 'internId',
                'externId'                => 'externId',
                'priorityAreaKeys'        => 'priorityAreaKeys',
                'countyNames'             => 'countyNames.raw',
                'tagNames'                => 'tagNames.text',
                'topicNames'              => 'topicNames.text',
                'meta_submitLastName'     => 'meta.submitLastName^0.2',
                'meta_caseWorkerLastName' => 'meta.caseWorkerLastName^0.2',
                'cluster_externId'        => 'cluster.externId',
                'clusterName'             => 'name.text',
                'cluster_uName'           => 'cluster.uName^0.1',
                'fragments.documentTitle.text',
                'fragments.paragraphTitle.text',
                'votes.firstName'         => 'votes.firstName',
                'votes.lastName'          => 'votes.lastName',
                'votes.name'              => 'votes.name',
                'filename'                => 'files',
                // after refactoring in T20362:
                'authorName'              => 'uName^0.2',
                'consideration'           => 'recommendation.text',
                'department'              => 'dName^0.2',
                'orgaCity'                => 'meta.orgaCity',
                'organisationName'        => 'oName^0.2',
                'orgaPostalCode'          => 'meta.orgaPostalCode',
                'planDocument'            => ['documentTitle.text', 'elementTitle.text', 'paragraphTitle.text'],
                'statementId'             => 'externId',
                'statementText'           => 'text.text',
                'topics'                  => 'topicNames.text',
            ];

            $usedSearchfields = [];
            if ([] === $searchFields) {
                $usedSearchfields = \array_values($availableSearchfields);
            } else {
                foreach ($searchFields as $field) {
                    if (\array_key_exists($field, $availableSearchfields)) {
                        $usedSearchfields[] = $availableSearchfields[$field];
                    }
                }
            }
            // do not create search query if only fragment fields are chosen
            if (0 < count($usedSearchfields)) {
                $searchQuery = $this->searchService->createSearchQuery(
                    $search,
                    $usedSearchfields
                );
            }
        }

        return $searchQuery;
    }

    /**
     * Basic filters to be applied to every query.
     *
     * @throws Exception
     */
    private function getBasicFilters(string $procedureId, array $userFilters): array
    {
        // Base Filters to apply always
        $boolMustFilter = [
            $this->searchService->getElasticaTermsInstance('pId', [$procedureId]),
            $this->searchService->getElasticaTermsInstance('deleted', [false]),
            ];

        $boolMustNotFilter = [
            // exclude clustered Statements
            $this->searchService->getElasticaExistsInstance('headStatementId'),
        ];

        // Ist es die Abwägungstabelle oder die Originalansicht?
        if (\array_key_exists('original', $userFilters) && 'IS NULL' === $userFilters['original']) {
            // Originalstellungnahmen haben null im Feld originalId
            $boolMustNotFilter[] = $this->searchService->getElasticaExistsInstance(
                'originalId'
            );
            // exclude Cluster
            $boolMustNotFilter[] = $this->searchService->getElasticaTermsInstance(
                'isClusterStatement',
                [true]
            );
        } else {
            $boolMustFilter[] = $this->searchService->getElasticaExistsInstance(
                'originalId'
            );
        }

        return [$boolMustFilter, $boolMustNotFilter];
    }

    /**
     * Mapping from filter names frontend / ES.
     */
    private function getRenamedUserFilters(array $userFilters): array
    {
        // map filternames from request to elasticsearch mapping names
        if (\array_key_exists('planningDocument', $userFilters)) {
            $userFilters['elementId'] = $userFilters['planningDocument'];
        }
        if (\array_key_exists('reasonParagraph', $userFilters)) {
            $userFilters['paragraphParentId'] = $userFilters['reasonParagraph'];
        }
        if (\array_key_exists('department', $userFilters)) {
            $userFilters['dName.raw'] = $userFilters['department'];
        }
        if (\array_key_exists('institution', $userFilters)) {
            $userFilters['oName.raw'] = $userFilters['institution'];
        }
        if (\array_key_exists('assignee_id', $userFilters)) {
            $userFilters['assignee.id'] = $userFilters['assignee_id'];
        }
        if (\array_key_exists('userState', $userFilters)) {
            $userFilters['meta.userState'] = $userFilters['userState'];
            unset($userFilters['userState']);
        }
        if (\array_key_exists('userGroup', $userFilters)) {
            $userFilters['meta.userGroup'] = $userFilters['userGroup'];
            unset($userFilters['userGroup']);
        }
        if (\array_key_exists('userOrganisation', $userFilters)) {
            $userFilters['meta.userOrganisation'] = $userFilters['userOrganisation'];
            unset($userFilters['userOrganisation']);
        }
        if (\array_key_exists('userPosition', $userFilters)) {
            $userFilters['meta.userPosition'] = $userFilters['userPosition'];
            unset($userFilters['userPosition']);
        }

        return $userFilters;
    }

    /**
     * Array with the filters to be applied for fragments.
     */
    private function getFragmentFilters(array $userFilters): array
    {
        $fragmentFilters = [
            'original',
            'fragments_element',
            'fragments_lastClaimed_id',
            'fragments_voteAdvice',
            'fragments_vote',
            'fragments_status',
            'fragments_element',
            'fragments_paragraphParentId',
            'fragments_reviewerName',
            'fragments_documentParentId',
            'fragments_municipalityNames',
            'fragments_countyNames',
            'fragments_tagNames',
            'fragments.priorityAreaKeys',
        ];

        // map filternames from request to elasticsearch mapping names
        if (\array_key_exists('planningDocument', $userFilters)) {
            $fragmentFilters[] = 'planningDocument';
        }
        if (\array_key_exists('reasonParagraph', $userFilters)) {
            $fragmentFilters[] = 'reasonParagraph';
        }
        if (\array_key_exists('department', $userFilters)) {
            $fragmentFilters[] = 'department';
        }
        if (\array_key_exists('institution', $userFilters)) {
            $fragmentFilters[] = 'institution';
        }
        if (\array_key_exists('assignee_id', $userFilters)) {
            $fragmentFilters[] = 'assignee_id';
        }

        return $fragmentFilters;
    }

    /**
     * Gets Aggegations from Elasticsearch to use as facetted filters.
     *
     * @param array                   $userFilters
     * @param string                  $procedureId
     * @param string                  $search
     * @param array|null              $sort
     * @param int                     $limit
     * @param int                     $page                         First page is 1
     * @param array                   $searchFields
     * @param bool                    $aggregationsOnly
     * @param int                     $aggregationsMinDocumentCount
     * @param bool                    $addAllAggregations
     * @param list<GlobalAggregation> $customAggregations
     */
    protected function getElasticsearchResult(
        $userFilters,
        $procedureId,
        $search = '',
        $sort = null,
        $limit = 0,
        $page = 1,
        $searchFields = [],
        $aggregationsOnly = false,
        $aggregationsMinDocumentCount = 1,
        $addAllAggregations = true,
        array $customAggregations = []
    ): ElasticsearchResult {
        $elasticsearchResultStatement = new ElasticsearchResult();
        try {
            $boolQuery = new BoolQuery();
            $searchQuery = $this->getSearchQuery($procedureId, $search, $searchFields, $aggregationsMinDocumentCount);
            [$boolMustFilter, $boolMustNotFilter] = $this->getBasicFilters($procedureId, $userFilters);
            $userFilters = $this->getRenamedUserFilters($userFilters);
            $fragmentFilters = $this->getFragmentFilters($userFilters);
            $userFragmentFilters = $this->mapRequestFiltersToESFragmentFilters($userFilters);
            $fragmentEsResult = (new ElasticsearchResult())->lock();

            if ((null !== $search && '' !== $search) || 0 < count($userFragmentFilters)) {
                $userFragmentFilters['procedureId'] = $procedureId;
                $fragmentEsResult = $this->statementFragmentService->getElasticsearchStatementFragmentResult($userFragmentFilters, $search, null, 10000, 1, $searchFields, $addAllAggregations);
                $statementMustIds = [];
                if (0 < (is_countable($fragmentEsResult->getHits()['hits']) ? count($fragmentEsResult->getHits()['hits']) : 0)) {
                    // use should filter as other filters may be applied as well
                    foreach ($fragmentEsResult->getHits()['hits'] as $fragmentHit) {
                        $statementMustIds[] = $fragmentHit['_source']['statementId'];
                    }
                } else {
                    $statementMustIds[] = 'not_existent';
                }
                $statementMustIds = \array_unique($statementMustIds);
                $shouldQuery = new BoolQuery();
                foreach ($statementMustIds as $statementMustId) {
                    $shouldQuery->addShould(
                        $this->searchService->getElasticaTermsInstance(
                            'id',
                            $statementMustId
                        ));
                }
                // add search query as a should request as we already found statements
                // that have the searchstring at their fragment
                if ($searchQuery instanceof AbstractQuery) {
                    $shouldQuery->addShould($searchQuery);
                }
                $shouldQuery = $this->searchService->setMinimumShouldMatch(
                    $shouldQuery,
                    1
                );
                $boolMustFilter[] = $shouldQuery;
            } else {
                if ($searchQuery instanceof Query) {
                    $boolMustFilter[] = $searchQuery;
                }
            }

            // Values that are === NULL instead of "" (empty string) if they are missing
            $nullValues = ['oName.raw', 'priorityAreaKeys', 'countyNames',
                'municipalityNames', 'topicNames', 'tagNames',
                'elementId', 'paragraphParentId', 'voteStk', 'votePla',
                'assignee.id', 'fragments.vote', 'fragments.voteAdvice',
                'fragments.lastClaimedUserId', 'fragments.element', 'fragments.paragraph',
                'fragments.countyNames', 'fragments.municipalityNames',
                'documentParentId', 'fragments_documentParentId', 'fragments.priorityAreaKeys',
            ];
            $rawFields = [
                'dName',
                'uName',
                'documentTitle',
                'paragraphTitle',
                'topicNames',
                'meta.submitName',
                'meta.caseWorkerName',
                'name',
                'countyNames',
                'municipalityNames',
                'tagNames',
            ];

            foreach ($userFilters as $filterName => $filterValues) {
                if (\in_array($filterName, $fragmentFilters)) {
                    continue;
                }

                $filterValues = \is_array($filterValues) ? \array_unique($filterValues) : $filterValues;

                if (\is_array($filterValues) && 1 < count($filterValues)) {
                    // for each filter with multiple options we need a distinct should
                    // query as filters should only be ORed within one field
                    $shouldQuery = new BoolQuery();
                    $shouldFilter = [];
                    $shouldNotFilter = [];
                    foreach ($filterValues as $filterValue) {
                        if ($filterValue === $this->searchService::KEINE_ZUORDNUNG || null === $filterValue || (\in_array($filterName, $nullValues) && '' === $filterValue)) {
                            $shouldNotFilter[] = $this->searchService->getElasticaExistsInstance(
                                $filterName
                            );
                        } else {
                            $filterName = $this->isRawFilteredTerm($filterName, $rawFields) ? $filterName.'.raw' : $filterName;
                            $value = $filterValue === $this->searchService::EMPTY_FIELD ? '' : $filterValue;
                            $shouldFilter[] = $this->searchService->getElasticaTermsInstance(
                                $filterName,
                                $value
                            );
                        }
                    }
                    array_map($shouldQuery->addShould(...), $shouldFilter);
                    // user wants to see not existent query as well as some filter
                    if (0 < count($shouldNotFilter)) {
                        $shouldNotBool = new BoolQuery();
                        array_map($shouldNotBool->addMustNot(...), $boolMustNotFilter);
                        $shouldQuery->addShould($shouldNotBool);
                    }
                    $shouldQuery = $this->searchService->setMinimumShouldMatch(
                        $shouldQuery,
                        1
                    );
                    // add as an ordinary bool Query
                    $boolMustFilter[] = $shouldQuery;
                } else {
                    [$boolMustFilter, $boolMustNotFilter] = $this->searchService->addUserFilter(
                        $filterName,
                        $userFilters,
                        $boolMustFilter,
                        $boolMustNotFilter,
                        null,
                        $rawFields,
                        $addAllAggregations
                    );
                }
            }

            if (0 < (is_countable($boolMustFilter) ? count($boolMustFilter) : 0)) {
                array_map($boolQuery->addMust(...), $boolMustFilter);
            }
            // do not include procedures in configuration
            if (0 < (is_countable($boolMustNotFilter) ? count($boolMustNotFilter) : 0)) {
                array_map($boolQuery->addMustNot(...), $boolMustNotFilter);
            }

            // generate Query
            $query = new Query();
            $query->setQuery($boolQuery);

            if ($aggregationsOnly) {
                $query->setSize(0);
            }

            // GET QUERY (END)

            /********************************** QUERY AGGREGATIONS (INI) *********************************************/

            /****************************************** CUSTOM AGGREGATIONS ******************************************/

            foreach ($customAggregations as $customAggregation) {
                $query->addAggregation($customAggregation);
            }

            /****************************************** EINREICHUNG **************************************************/

            // Öffentlichkeit/Institution - publicStatement - publicStatement
            if ($addAllAggregations || \array_key_exists('publicStatement', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'publicStatement');
            }
            // Institution/Name - institution - oName.raw
            if ($addAllAggregations || \array_key_exists('institution', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'oName.raw', null, null, 'oName.raw');
                $query = $this->searchService->addEsMissingAggregation($query, 'oName.raw');
            }
            // Abteilung - department - dName.raw
            if ($addAllAggregations || \array_key_exists('department', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'dName.raw', null, null, 'dName.raw');
                $query = $this->searchService->addEsMissingAggregation($query, 'dName.raw');
            }
            // Verfahrensschritt - phase - phase
            if ($addAllAggregations || \array_key_exists('phase', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'phase');
            }
            // Verschobene Stellungnahmen in dieses Verfahren - movedFromProcedureId - movedFromProcedureId
            if ($addAllAggregations || \array_key_exists('movedFromProcedureId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'movedFromProcedureId');
            }
            // Verschobene Stellungnahmen aus diesem Verfahren - movedToProcedureId - movedToProcedureId
            if ($addAllAggregations || \array_key_exists('movedToProcedureId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'movedToProcedureId');
            }
            // VeröffentlichungI - publicAllowed - publicAllowed
            if ($addAllAggregations || \array_key_exists('publicAllowed', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'publicAllowed');
            }
            // VeröffentlichungII - publicCheck - publicCheck
            if ($addAllAggregations || \array_key_exists('publicCheck', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'publicCheck');
            }
            // VeröffentlichungIII - publicVerify - publicVerify
            if ($addAllAggregations || \array_key_exists('publicVerified', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'publicVerified');
            }
            // Project specifics
            if ($addAllAggregations || \array_key_exists('meta.userState', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'meta.userState');
            }
            if ($addAllAggregations || \array_key_exists('meta.userGroup', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'meta.userGroup');
            }
            if ($addAllAggregations || \array_key_exists('meta.userOrganisation', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'meta.userOrganisation');
            }
            if ($addAllAggregations || \array_key_exists('meta.userPosition', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'meta.userPosition');
            }

            /***************************************** STELLUNGNAHME ************************************************/
            // Sachbearbeiter - assignee_id - assignee.id
            if ($addAllAggregations || \array_key_exists('assignee_id', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'assignee.id', null, null, 'assignee_id');
                $query = $this->searchService->addEsMissingAggregation($query, 'assignee.id');
            }
            // Bearbeitungsstatus - status - status
            if ($addAllAggregations || \array_key_exists(self::AGGREGATION_STATEMENT_STATUS, $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, self::FIELD_STATEMENT_STATUS, null, null, self::AGGREGATION_STATEMENT_STATUS);
            }
            // Votum - votePla - votePla
            if ($addAllAggregations || \array_key_exists('votePla', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'votePla', null, null, 'votePla'); // vote
                $query = $this->searchService->addEsMissingAggregation($query, 'votePla');
            }
            // Kreis - countyNames - countyNames.raw
            if ($addAllAggregations || \array_key_exists('countyNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'countyNames.raw', null, null, 'countyNames');
                $query = $this->searchService->addEsMissingAggregation($query, 'countyNames.raw');
            }
            // Gemeinde - municipalityNames - municipalityNames.raw
            if ($addAllAggregations || \array_key_exists('municipalityNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'municipalityNames.raw', null, null, 'municipalityNames');
                $query = $this->searchService->addEsMissingAggregation($query, 'municipalityNames.raw');
            }
            // Schlagwort - tagNames - tagNames.raw
            if ($addAllAggregations || \array_key_exists('tagNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'tagNames.raw', null, null, 'tagNames');
                $query = $this->searchService->addEsMissingAggregation($query, 'tagNames.raw');
            }
            // Potenzialflächen - priorityAreaKeys - priorityAreaKeys
            if ($addAllAggregations || \array_key_exists('priorityAreaKeys', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'priorityAreaKeys');
                $query = $this->searchService->addEsMissingAggregation($query, 'priorityAreaKeys');
            }
            // Dokument - planningDocument - elementId
            if ($addAllAggregations || \array_key_exists('elementId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'elementId', '_term', 'asc', 'elementId');
                $query = $this->searchService->addEsMissingAggregation($query, 'elementId');
            }
            // Kapitel - reasonParagraph - paragraphParentId
            if ($addAllAggregations || \array_key_exists('reasonParagraph', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'paragraphParentId');
                $query = $this->searchService->addEsMissingAggregation($query, 'paragraphParentId');
            }
            // Datei - documentParentId - documentParentId
            if ($addAllAggregations || \array_key_exists('documentParentId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'documentParentId');
                $query = $this->searchService->addEsMissingAggregation($query, 'documentParentId');
            }
            // Thema - topicNames - topicNames.raw
            if ($addAllAggregations || \array_key_exists('topicNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'topicNames.raw', null, null, 'topicNames');
                $query = $this->searchService->addEsMissingAggregation($query, 'topicNames.raw');
            }
            // ID - externId - externId
            if ($addAllAggregations || \array_key_exists('externId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'externId');
            }
            // Gruppenname - name - name.raw
            if ($addAllAggregations || \array_key_exists('name', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'name.raw');
            }
            // Art der Stellungnahme - type - type
            if ($addAllAggregations || \array_key_exists('type', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'type');
            }
            // Priorität - priority - priority
            if ($addAllAggregations || \array_key_exists(self::AGGREGATION_STATEMENT_PRIORITY, $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, self::FIELD_STATEMENT_PRIORITY, null, null, self::AGGREGATION_STATEMENT_PRIORITY);
            }
            // Empfehlung - voteStk - voteStk
            if ($addAllAggregations || \array_key_exists('voteStk', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'voteStk', null, null, 'voteStk'); // advice for vote
                $query = $this->searchService->addEsMissingAggregation($query, 'voteStk');
            }

            /*************************************** DATENSATZ / FRAGMENTS *******************************************/

            // Sachbearbeiter - fragments_lastClaimed_id - fragments.lastClaimedUserId
            if ($addAllAggregations || \array_key_exists('fragments_lastClaimed_id', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.lastClaimedUserId', null, null, 'fragments_lastClaimed_id');
                $query = $this->searchService->addEsFragmentsMissingAggregation('fragments.lastClaimedUserId', $query);
            }
            // Bearbeitungsstatus - fragments_status - fragments.status
            if ($addAllAggregations || \array_key_exists('fragments_status', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.status', null, null, 'fragments_status');
            }
            // Votum - fragments_vote - fragments.vote
            if ($addAllAggregations || \array_key_exists('fragments_vote', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.vote', null, null, 'fragments_vote');
                $query = $this->searchService->addEsFragmentsMissingAggregation('fragments.vote', $query);
            }
            // Kreis - fragments_countyNames - fragments.countyNames
            if ($addAllAggregations || \array_key_exists('fragments_countyNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.countyNames.raw', null, null, 'fragments_countyNames');
                $query = $this->searchService->addEsFragmentsMissingAggregation('fragments.countyNames.raw', $query);
            }
            // Gemeinde - fragments_municipalityNames - fragments.municipalityNames
            if ($addAllAggregations || \array_key_exists('fragments_municipalityNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.municipalityNames.raw', null, null, 'fragments_municipalityNames');
                $query = $this->searchService->addEsFragmentsMissingAggregation('fragments.municipalityNames.raw', $query);
            }
            // Schlagwort - fragments_tagNames - fragments.tags.name
            if ($addAllAggregations || \array_key_exists('fragments.tagNames', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.tags.name.raw', null, null, 'fragments_tagNames');
            }
            // Potenzialflächen - fragments.priorityAreaKeys - fragments.priorityAreaKeys
            if ($addAllAggregations || \array_key_exists('fragments.priorityAreaKeys', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.priorityAreaKeys', null, null, 'fragments.priorityAreaKeys');
            }
            // Dokument - fragments_element - fragments.elementId
            if ($addAllAggregations || \array_key_exists('fragments_element', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.elementId', null, null, 'fragments_element');
            }
            // Kapitel - fragments_paragraphParentId - fragments.paragraphParentId
            if ($addAllAggregations || \array_key_exists('fragments_paragraphParentId', $userFilters)) {
                $query = $this->searchService->addEsAggregation($query, 'fragments.paragraphParentId', null, null, 'fragments_paragraphParentId');
                $query = $this->searchService->addEsFragmentsMissingAggregation('fragments.paragraphParentId', $query);
            }
            // Datei - fragments_documentParentId - fragments.documentParentId
            if ($addAllAggregations || \array_key_exists('fragments_documentParentId', $userFilters)) {
                $query = $this->searchService->addEsFragmentsMissingAggregation('fragments.documentParentId', $query);
                $query = $this->searchService->addEsAggregation($query, 'fragments.documentParentId', null, null, 'fragments.documentParentId');
            }
            // Fachbehörde - fragments_reviewerName - fragments.departmentId
            if ($addAllAggregations || \array_key_exists('fragments_reviewerName', $userFilters)) {
                $query = $this->searchService->addEsFragmentsMissingAggregation('fragments.departmentId', $query);
                $query = $this->searchService->addEsAggregation($query, 'fragments.departmentId', null, null, 'fragments.departmentId');
            }

            // Sorting
            $esSort = $this->mapSorting($sort, $search);
            $query->addSort($esSort);

            $this->logger->debug('Elasticsearch StatementList Query: '.DemosPlanTools::varExport($query->getQuery(), true));

            $search = $this->getEsStatementType();
            // Don't let yourself be fooled, this basically does the search request, don't
            // go looking for an explicit call to search() anywhere in here. <3
            $elasticaAdapter = new ElasticaAdapter($search, $query);
            $paginator = new DemosPlanPaginator($elasticaAdapter);
            $paginator->setLimits($this->getPaginatorLimits());

            // setze einen Defaultwert
            if (0 === $limit) {
                $defaultLimits = $this->getPaginatorLimits();
                $limit = $defaultLimits[0];
            }

            $paginator->setMaxPerPage((int)$limit);
            // try to paginate Result, check for validity
            try {
                $paginator->setCurrentPage($page);
            } catch (NotValidCurrentPageException $e) {
                $this->logger->info('Received invalid Page for pagination', [$e]);
                $paginator->setCurrentPage(1);
            }

            try {
                /** @var array|Traversable $resultSet */
                $resultSet = $paginator->getCurrentPageResults();
                $result = $resultSet->getResponse()->getData();
                $elasticsearchResultStatement->setHits($result['hits']);
            } catch (ClientException $e) {
                $this->logger->error('Elasticsearch probably hit a timeout: ', [$e]);
                throw $e;
            }

            $esResultAggregations = $resultSet->getAggregations();
            $totalHits = $result['hits']['total'];
            if (is_array($totalHits) && array_key_exists('value', $totalHits) && 0 === $totalHits['value']) {
                $esResultAggregations = $this->addFilterToAggregationsWhenCausedResultIsEmpty($esResultAggregations, $userFilters);
            }

            $processedAggregation = [];
            $elementsAdminList = $this->serviceElements->getElementsAdminList($procedureId);
            $elementMap = \collect($elementsAdminList)
                ->mapWithKeys(static fn (Elements $element): array => [$element->getId() => $element->getTitle()])->all();

            /********************************** QUERY AGGREGATIONS (INI) *********************************************/

            /****************************************** CUSTOM AGGREGATIONS ******************************************/

            foreach ($customAggregations as $customAggregation) {
                $name = $customAggregation->getName();
                $processedAggregation = $this->searchService->addAggregationResultToArray($name, $name, $esResultAggregations, $processedAggregation);
            }

            /****************************************** EINREICHUNG **************************************************/
            // Öffentlichkeit/Institution - publicStatement - publicStatement
            if ($addAllAggregations || \array_key_exists('publicStatement', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('publicStatement', 'publicStatement', $esResultAggregations, $processedAggregation);
            }
            // Institution/Name - institution - oName.raw
            if ($addAllAggregations || \array_key_exists('institution', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('oName.raw', 'institution', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('oName.raw', 'institution', $esResultAggregations, $processedAggregation);
            }
            // Abteilung - department - dName.raw
            if ($addAllAggregations || \array_key_exists('department', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('dName.raw', 'department', $esResultAggregations, $processedAggregation);
            }
            // Verfahrensschritt - phase - phase
            if ($addAllAggregations || \array_key_exists('phase', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('phase', 'phase', $esResultAggregations, $processedAggregation);
            }
            // Verschobene Stellungnahmen in dieses Verfahren - movedFromProcedureId - movedFromProcedureId
            if ($addAllAggregations || \array_key_exists('movedFromProcedureId', $userFilters)) {
                $movedStatementCount = 0;
                $processedAggregation['movedFromProcedureId'] = [];
                if (isset($esResultAggregations['movedFromProcedureId'])) {
                    foreach ($esResultAggregations['movedFromProcedureId']['buckets'] as $agg) {
                        $procedure = $this->procedureRepository->get($agg['key']);
                        $label = $procedure instanceof Procedure ? $procedure->getName() : '';
                        $processedAggregation['movedFromProcedureId'][] = [
                            'count' => $agg['doc_count'],
                            'label' => $label,
                            'value' => $agg['key'],
                        ];
                        $movedStatementCount += $agg['doc_count'];
                    }
                }
                array_unshift($processedAggregation['movedFromProcedureId'], [
                    'label' => $this->translator->trans('all'),
                    'value' => $this->searchService::EXISTING_FIELD_FILTER,
                    'count' => $movedStatementCount,
                ]);
            }
            // Verschobene Stellungnahmen aus diesem Verfahren - movedToProcedureId - movedToProcedureId
            if ($addAllAggregations || \array_key_exists('movedToProcedureId', $userFilters)) {
                $movedStatementCount = 0;
                $processedAggregation['movedToProcedureId'] = [];
                if (isset($esResultAggregations['movedToProcedureId'])) {
                    foreach ($esResultAggregations['movedToProcedureId']['buckets'] as $agg) {
                        $procedure = $this->procedureRepository->get($agg['key']);
                        $label = $procedure instanceof Procedure ? $procedure->getName() : '';
                        $processedAggregation['movedToProcedureId'][] = [
                            'count' => $agg['doc_count'],
                            'label' => $label,
                            'value' => $agg['key'],
                        ];
                        $movedStatementCount += $agg['doc_count'];
                    }
                }
                array_unshift($processedAggregation['movedToProcedureId'], [
                    'label' => $this->translator->trans('all'),
                    'value' => $this->searchService::EXISTING_FIELD_FILTER,
                    'count' => $movedStatementCount,
                ]);
            }

            if ($addAllAggregations || \array_key_exists('publicCheck', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('publicCheck', 'publicCheck', $esResultAggregations, $processedAggregation);
            }

            /***************************************** STELLUNGNAHME ************************************************/

            // Sachbearbeiter - assignee_id
            if ($addAllAggregations || \array_key_exists('assignee_id', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('assignee.id', 'assignee_id', $esResultAggregations, $processedAggregation);
            }
            if (isset($esResultAggregations['assignee_id'])) {
                foreach ($esResultAggregations['assignee_id']['buckets'] as $agg) {
                    $user = $this->userService->getSingleUser($agg['key']);
                    $userName = $user instanceof User ?
                        $user->getFirstname().' '.$user->getLastname().' -- '.$user->getOrgaName() :
                        '';
                    $processedAggregation['assignee_id'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $userName,
                        'value' => $agg['key'],
                    ];
                }
            }
            // Bearbeitungsstatus - status
            if ($addAllAggregations || \array_key_exists(self::AGGREGATION_STATEMENT_STATUS, $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray(self::AGGREGATION_STATEMENT_STATUS, self::AGGREGATION_STATEMENT_STATUS, $esResultAggregations, $processedAggregation);
            }
            // Votum - votePla
            if ($addAllAggregations || \array_key_exists('votePla', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('votePla', 'votePla', $esResultAggregations, $processedAggregation);
            }
            if (isset($esResultAggregations['votePla'])) {
                foreach ($esResultAggregations['votePla']['buckets'] as $agg) {
                    $processedAggregation['votePla'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $this->translator->trans('fragment.vote.'.$agg['key']),
                        'value' => $agg['key'],
                    ];
                }
            }
            // Kreis - countyNames
            if ($addAllAggregations || \array_key_exists('countyNames', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('countyNames.raw', 'countyNames', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('countyNames', 'countyNames', $esResultAggregations, $processedAggregation);
            }
            // Gemeinde - municipalityNames
            if ($addAllAggregations || \array_key_exists('municipalityNames', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('municipalityNames.raw', 'municipalityNames', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('municipalityNames', 'municipalityNames', $esResultAggregations, $processedAggregation);
            }
            // Schlagwort - tagNames - tagNams.raw
            if ($addAllAggregations || \array_key_exists('tagNames', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('tagNames.raw', 'tagNames', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('tagNames', 'tagNames', $esResultAggregations, $processedAggregation);
            }
            // Potenzialflächen - priorityAreaKeys
            if ($addAllAggregations || \array_key_exists('priorityAreaKeys', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('priorityAreaKeys', 'priorityAreaKeys', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('priorityAreaKeys', 'priorityAreaKeys', $esResultAggregations, $processedAggregation);
            }
            // Dokument - planningDocument - elementId
            if ($addAllAggregations || \array_key_exists('elementId', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('elementId', 'planningDocument', $esResultAggregations, $processedAggregation, $elementMap);
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('elementId', 'planningDocument', $esResultAggregations, $processedAggregation);
            }
            // Kapitel - reasonParagraph - paragraphParentId
            if ($addAllAggregations || \array_key_exists('reasonParagraph', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('paragraphParentId', 'reasonParagraph', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('paragraphParentId', 'reasonParagraph', $esResultAggregations, $processedAggregation, $this->getParagraphMap($esResultAggregations['paragraphParentId']['buckets']));
            }
            // Datei - documentParentId
            if ($addAllAggregations || \array_key_exists('documentParentId', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('documentParentId', 'documentParentId', $esResultAggregations, $processedAggregation);
            }
            if (isset($esResultAggregations['documentParentId'])) {
                foreach ($esResultAggregations['documentParentId']['buckets'] as $agg) {
                    $document = $this->singleDocumentRepository->findOneBy(['id' => $agg['key']]);
                    $label = $document instanceof SingleDocument ? $document->getTitle() : '';
                    $processedAggregation['documentParentId'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $label,
                        'value' => $agg['key'],
                    ];
                }
            }
            // Thema - topicNames - topicNames.raw
            if ($addAllAggregations || \array_key_exists('topicNames', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('topicNames.raw', 'topicNames', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('topicNames', 'topicNames', $esResultAggregations, $processedAggregation);
            }
            // ID - externId - externId
            if ($addAllAggregations || \array_key_exists('externId', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('externId', 'externId', $esResultAggregations, $processedAggregation);
            }
            // Gruppenname - name - name.raw
            if ($addAllAggregations || \array_key_exists('name', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('name.raw', 'name', $esResultAggregations, $processedAggregation);
            }
            // Art der Stellungnahme - type
            if (isset($esResultAggregations['type'])) {
                foreach ($esResultAggregations['type']['buckets'] as $agg) {
                    $processedAggregation['type'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $this->translator->trans('statement.type.'.$agg['key']),
                        'value' => $agg['key'],
                    ];
                }
            }
            // Priorität - priority
            if ($addAllAggregations || \array_key_exists(self::AGGREGATION_STATEMENT_PRIORITY, $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray(self::AGGREGATION_STATEMENT_PRIORITY, self::AGGREGATION_STATEMENT_PRIORITY, $esResultAggregations, $processedAggregation);
            }
            // Empfehlung - voteStk
            if ($addAllAggregations || \array_key_exists('voteStk', $userFilters)) {
                $processedAggregation = $this->searchService->addMissingAggregationResultToArray('voteStk', 'voteStk', $esResultAggregations, $processedAggregation);
            }
            if (isset($esResultAggregations['voteStk'])) {
                foreach ($esResultAggregations['voteStk']['buckets'] as $agg) {
                    $processedAggregation['voteStk'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $this->translator->trans('fragment.vote.'.$agg['key']),
                        'value' => $agg['key'],
                    ];
                }
            }
            // project specifics
            if ($addAllAggregations || \array_key_exists('meta.userState', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('meta.userState', 'userState', $esResultAggregations, $processedAggregation);
            }
            if ($addAllAggregations || \array_key_exists('meta.userGroup', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('meta.userGroup', 'userGroup', $esResultAggregations, $processedAggregation);
            }
            if ($addAllAggregations || \array_key_exists('meta.userOrganisation', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('meta.userOrganisation', 'userOrganisation', $esResultAggregations, $processedAggregation);
            }
            if ($addAllAggregations || \array_key_exists('meta.userPosition', $userFilters)) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('meta.userPosition', 'userPosition', $esResultAggregations, $processedAggregation);
            }

            /*************************************** DATENSATZ / FRAGMENTS *******************************************/

            // We use $fragmentsEsResult for the filters and $aggregations for the Statement List

            // Sachbearbeiter - fragments_lastClaimed_id - fragments.lastClaimedUserId
            if ($addAllAggregations || \array_key_exists('fragments_lastClaimed_id', $userFilters)) {
                $processedAggregation = $this->searchService->addFragmentsMissingAggregationResultToArray('fragments.lastClaimedUserId', 'fragments_lastClaimed_id', $esResultAggregations, $processedAggregation);
            }
            $fragmentAggregations = $fragmentEsResult->getAggregations();
            if (isset($fragmentAggregations['lastClaimed_id'])) {
                $processedAggregation['fragments_lastClaimed_id'] = \array_merge($processedAggregation['fragments_lastClaimed_id'],
                    $this->searchService->generateFilterArrayFromUserAssignEsBucket(
                        $fragmentAggregations['lastClaimed_id'],
                        'value',
                        'value',
                        'count'
                    ));
            } elseif (isset($esResultAggregations['fragments_lastClaimed_id'])) {
                $processedAggregation['fragments_lastClaimed_id'] = \array_merge($processedAggregation['fragments_lastClaimed_id'],
                    $this->searchService->generateFilterArrayFromUserAssignEsBucket(
                        $esResultAggregations['fragments_lastClaimed_id']['buckets']
                    ));
            }
            // Bearbeitungsstatus - fragments_status - fragments.status
            if (isset($fragmentAggregations['status'])) {
                $processedAggregation = $this->searchService->addFragmentEsResultToArray('fragments_status', 'fragments_status', $fragmentAggregations, $processedAggregation);
            } elseif (isset($esResultAggregations['fragments_status'])) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('fragments_status', 'fragments_status', $esResultAggregations, $processedAggregation);
            }
            // Votum - fragments_vote - fragments.vote
            if (isset($fragmentAggregations['vote'])) {
                $processedAggregation = $this->searchService->addFragmentEsResultToArray('vote', 'fragments_vote', $fragmentAggregations, $processedAggregation, $this->statementFragmentService->getVoteLabelMap());
            } elseif (isset($esResultAggregations['fragments_vote'])) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('fragments_vote', 'fragments_vote', $esResultAggregations, $processedAggregation);
            }
            // Kreis - fragments_countyNames - fragments.countyNames
            if ($addAllAggregations || \array_key_exists('fragments_countyNames', $userFilters)) {
                $processedAggregation = $this->searchService->addFragmentsMissingAggregationResultToArray('fragments.countyNames', 'fragments_countyNames', $esResultAggregations, $processedAggregation);
            }
            if (isset($fragmentAggregations['countyNames'])) {
                $processedAggregation['fragments_countyNames'] = \array_merge($processedAggregation['fragments_countyNames'],
                    $this->searchService->generateFilterArrayFromUserAssignEsBucket(
                        $fragmentAggregations['countyNames'],
                        'value',
                        'value',
                        'count'
                    ));
            } elseif (isset($esResultAggregations['fragments_countyNames'])) {
                $processedAggregation['fragments_countyNames'] = \array_merge(\array_key_exists('fragments_countyNames', $processedAggregation) ? $processedAggregation['fragments_countyNames'] : [],
                    $this->searchService->generateFilterArrayFromUserAssignEsBucket(
                        $esResultAggregations['fragments_countyNames']['buckets']
                    ));
            }
            // Gemeinde - fragments_municipalityNames - fragments.municipalityNames
            if ($addAllAggregations || \array_key_exists('fragments_municipalityNames', $userFilters)) {
                $processedAggregation = $this->searchService->addFragmentsMissingAggregationResultToArray('fragments.municipalityNames', 'fragments_municipalityNames', $esResultAggregations, $processedAggregation);
            }
            if (isset($fragmentAggregations['municipalityNames'])) {
                $processedAggregation['fragments_municipalityNames'] = \array_merge($processedAggregation['fragments_municipalityNames'],
                    $this->searchService->generateFilterArrayFromUserAssignEsBucket(
                        $fragmentAggregations['municipalityNames'],
                        'value',
                        'value',
                        'count'
                    ));
            } elseif (isset($esResultAggregations['fragments_municipalityNames'])) {
                $processedAggregation['fragments_municipalityNames'] = \array_merge(\array_key_exists('fragments_municipalityNames', $processedAggregation) ? $processedAggregation['fragments_municipalityNames'] : [],
                    $this->searchService->generateFilterArrayFromUserAssignEsBucket(
                        $esResultAggregations['fragments_municipalityNames']['buckets']
                    ));
            }
            // Schlagwort - fragments_tagNames - fragments.tags.name
            if (isset($fragmentAggregations['tagNames'])) {
                $processedAggregation = $this->searchService->addFragmentEsResultToArray('tagNames', 'fragments_tagNames', $fragmentAggregations, $processedAggregation);
            }
            // Potenzialflächen - fragments.priorityAreaKeys - fragments.priorityAreaKeys
            if ($addAllAggregations || \array_key_exists('fragments.priorityAreaKeys', $userFilters)) {
                $processedAggregation = $this->searchService->addFragmentsMissingAggregationResultToArray('fragments.priorityAreaKeys', 'fragments.priorityAreaKeys', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('fragments.priorityAreaKeys', 'fragments.priorityAreaKeys', $esResultAggregations, $processedAggregation);
            }
            // Dokument - fragments_element - fragments.elementId
            if ($addAllAggregations || \array_key_exists('fragments_element', $userFilters)) {
                $processedAggregation = $this->searchService->addFragmentsMissingAggregationResultToArray('fragments.elementId', 'fragments_element', $esResultAggregations, $processedAggregation);
            }
            if (isset($esResultAggregations['fragments_element'])) {
                $processedAggregation = $this->searchService->addAggregationResultToArray('fragments_element', 'fragments_element', $esResultAggregations, $processedAggregation, $elementMap);
            }
            // Kapitel - fragments_paragraphParentId - fragments.paragraphParentId
            if ($addAllAggregations || \array_key_exists('fragments_paragraphParentId', $userFilters)) {
                $processedAggregation = $this->searchService->addFragmentsMissingAggregationResultToArray('fragments.paragraphParentId', 'fragments_paragraphParentId', $esResultAggregations, $processedAggregation);
                $processedAggregation = $this->searchService->addAggregationResultToArray('fragments_paragraphParentId', 'fragments_paragraphParentId', $esResultAggregations, $processedAggregation, $this->getParagraphMap($esResultAggregations['fragments_paragraphParentId']['buckets']));
            }
            if (isset($fragmentAggregations['paragraphParentId'])) {
                $processedAggregation = $this->searchService->addFragmentEsResultToArray('fragments_paragraphParentId', 'fragments_paragraphParentId', $fragmentAggregations, $processedAggregation, $this->getParagraphMap(
                    $fragmentAggregations['fragments_paragraphParentId'], 'value'));
            }
            if ($addAllAggregations || \array_key_exists('fragments_documentParentId', $userFilters)) {
                $processedAggregation = $this->searchService->addFragmentsMissingAggregationResultToArray('fragments.documentParentId', 'fragments_documentParentId', $esResultAggregations, $processedAggregation);
            }
            // Datei - fragments_documentParentId - fragments.documentParentId
            if (isset($esResultAggregations['fragments.documentParentId'])) {
                foreach ($esResultAggregations['fragments.documentParentId']['buckets'] as $agg) {
                    $document = $this->singleDocumentRepository->findOneBy(['id' => $agg['key']]);
                    $label = $document instanceof SingleDocument ? $document->getTitle() : '';
                    $processedAggregation['fragments_documentParentId'][] = [
                        'count' => $agg['doc_count'],
                        'label' => $label,
                        'value' => $agg['key'],
                    ];
                }
            }
            // Fachbehörde - fragments_reviewerName - fragments.departmentId
            if (isset($fragmentAggregations['departmentId'])) {
                $processedAggregation = $this->searchService->addFragmentsMissingAggregationResultToArray('fragments.departmentId', 'fragments_reviewerName', $esResultAggregations, $processedAggregation);
            }
            $useEsResult2 = isset($fragmentAggregations['departmentId']);
            $useAggregationResult2 = isset($esResultAggregations['fragments_reviewerName']);
            if (true === $useEsResult2 || true === $useAggregationResult2) {
                $countKey2 = true === $useEsResult2 ? 'count' : 'doc_count';
                $valueKey2 = true === $useEsResult2 ? 'value' : 'key';
                $listToUse2 = true === $useEsResult2 ? $fragmentAggregations['departmentId'] : $esResultAggregations['fragments_reviewerName']['buckets'];
                if ($useEsResult2) {
                    foreach ($listToUse2 as $agg) {
                        $department = $this->departmentRepository->get($agg[$valueKey2]);
                        if (null !== $department) {
                            $processedAggregation['fragments_reviewerName'][] = $this->buildReviewerAggregationArray($department, $agg, $countKey2, $valueKey2);
                        } else {
                            $this->logger->warning('$department is null for id `'.$agg[$valueKey2].'`');
                        }
                    }
                }
            }

            /************************************* ADD AGGREGATIONS TO RESULT (END) **********************************/

            // add modified Aggregations to Result
            $elasticsearchResultStatement->setAggregations($processedAggregation);
            $elasticsearchResultStatement->setPager($paginator);
            $elasticsearchResultStatement->setSearchFields($searchFields);

            $this->profilerStop('ES');
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getStatementAggregation failed. ', [$e]);

            $elasticsearchResultStatement = $this->searchService->getESEmptyResult(
                'warning.search.query.invalid'
            );
        }

        return $elasticsearchResultStatement->lock();
    }

    /**
     * @param Department $department
     * @param array      $agg
     * @param string     $countKey
     * @param string     $valueKey
     */
    protected function buildReviewerAggregationArray($department, $agg, $countKey, $valueKey): array
    {
        $orgaName = $department->getOrgaName();
        $label = $orgaName.' -- '.$department->getName();

        return [
            'count' => $agg[$countKey],
            'label' => $label,
            'value' => $agg[$valueKey],
        ];
    }

    /**
     * Returns true if the aggregation/filter key must be
     * suffixed with ".raw" in order to function correctly.
     *
     * @param string $key
     * @param array  $rawFields
     */
    protected function isRawFilteredTerm($key, $rawFields): bool
    {
        return \in_array($key, $rawFields, true);
    }

    /**
     * Create Label => Value map of paragraphs included in aggregation.
     *
     * @param array  $bucket
     * @param string $idKey
     *
     * @throws Exception
     */
    protected function getParagraphMap($bucket, $idKey = 'key'): array
    {
        if (!\is_array($bucket) || 0 === count($bucket)) {
            return [];
        }
        $ids = [];

        foreach ($bucket as $entry) {
            $ids[] = $entry[$idKey];
        }
        $paragraphMap = [];
        $paragraphList = $this->paragraphService->getParaDocumentListByIds($ids);
        if (null === $paragraphList) {
            return $paragraphMap;
        }
        /** @var Paragraph $paragraph */
        foreach ($paragraphList as $paragraph) {
            $paragraphMap[$paragraph->getId()] = $paragraph->getTitle();
        }

        return $paragraphMap;
    }

    /**
     * Map given sort to es-sort.
     * Also set default sort values.
     *
     * @param array       $sort
     * @param string|null $search
     *
     * @return array - mapped es-sorting
     */
    protected function mapSorting($sort, $search = null): array
    {
        // sort by score if something has been searched for
        if (\is_string($search) && '*' !== $search && 0 < mb_strlen($search)) {
            return ['_score' => 'desc'];
        }

        $sortObject = $this->addMissingSortKeys($sort, 'submitDate', 'asc');
        $sortProperty = $sortObject->getPropertyName();
        $sortDirection = $sortObject->getDirection();

        $esSort = [];
        if ('submitDate' === $sortProperty) {
            $esSort = ['submit' => $sortDirection];
        }
        if (self::FIELD_STATEMENT_PRIORITY === $sortProperty) {
            $esSort = [self::FIELD_STATEMENT_PRIORITY => $sortDirection];
        }
        if ('forPoliticians' === $sortProperty) {
            $esSort = [
                'prioritySort'      => 'asc',
                'elementTitle.sort' => 'asc',
                'paragraphOrder'    => 'asc',
            ];
        }
        if ('elementsView' === $sortProperty) {
            $esSort = [
                'elementOrder'   => 'asc',
                'paragraphOrder' => 'asc',
            ];
        }

        // workaround until we can use recent Versions of Elasticsearch & Elastica
        // https://github.com/ruflin/Elastica/issues/717
        // use -1000 instead of -1 as written in ticket referenced above
        // as -1 leads to errors when testing in kopf plugin and it only needs
        // to be a big number
        if ('planningDocument' === $sortProperty) {
            $esSort = [
                'elementTitle.sort' => [
                    'order'   => $sortDirection,
                    'missing' => \PHP_INT_MAX - 1000,
                ],
                'paragraphOrder'    => [
                    'order'   => $sortDirection,
                    'missing' => \PHP_INT_MAX - 1000,
                ],
            ];
        }
        if ('institution' === $sortProperty) {
            // When sorting for institution sort also submitter name
            $esSort = [
                'isClusterStatement' => 'asc',
                'publicStatement'    => 'desc',
                'oName.sort'         => $sortDirection,
                'dName.sort'         => $sortDirection,
                'uName.sort'         => $sortDirection,
                'cluster.oName.sort' => $sortDirection,
                'cluster.dName.sort' => $sortDirection,
                'cluster.uName.sort' => $sortDirection,
            ];
        }

        // add default sort, additionally to primary sort
        if (!\array_key_exists('submit', $esSort) || 'asc' !== strtolower((string) $esSort['submit'])) {
            $esSort['submit'] = 'desc';
        }

        return $esSort;
    }

    /**
     * @param Tag       $tag
     * @param Statement $statement
     *
     * @throws Exception
     *
     * @deprecated Used by tests only. Tags are automatically persisted when their statement is
     *             persisted, so you can simply add Tags to statements and persist the statements
     *             instead of using this method.
     */
    public function addTagToStatement($tag, $statement): Statement
    {
        $statement->addTag($tag);
        $this->tagRepository->updateObject($tag);

        return $this->updateStatementObject($statement);
    }

    /**
     * @param Statement      $statementAbwaegungstabelle
     * @param DraftStatement $draftStatement
     *
     * @return Statement
     */
    protected function postSubmitDraftStatement($statementAbwaegungstabelle, $draftStatement)
    {
        if (!$this->permissions->hasPermission('feature_statement_geolocate_dataport')) {
            return $statementAbwaegungstabelle;
        }

        if (!is_null($statementAbwaegungstabelle->getPolygon()) && 0 < \strlen($statementAbwaegungstabelle->getPolygon())) {
            try {
                $this->statementGeoService->scheduleFetchGeoData($statementAbwaegungstabelle->getId());
            } catch (Exception $e) {
                $this->getLogger()->warning('Fetch Geodata could not be scheduled', [$e]);
            }
        }

        // Speichere ggf. ein Potenzialfläche am Statement
        /** @var Collection|null $statementAttributes */
        $statementAttributes = $draftStatement->getStatementAttributes();
        if (!is_null($statementAttributes) && 0 < $statementAttributes->count()) {
            try {
                // gibt es ein StatementAttribut, dass eine Potenzialfläche gespeichert ist
                $hasPriorityArea = $statementAttributes->filter(
                    fn ($entry) =>
                        /* @var StatementAttribute $entry */
                        'priorityAreaKey' === $entry->getType()
                );
                if (1 == $hasPriorityArea->count()) {
                    // lade die Potenzialfläche
                    /** @var Collection|null $priorityArea */
                    $priorityArea = $this->getPriorityAreaService()->getPriorityAreasByKey($hasPriorityArea->first()->getValue());
                    if (!is_null($priorityArea) && 1 === count($priorityArea)) {
                        // Füge die Potenzialfläche der SN zu
                        $statementAbwaegungstabelle->addPriorityArea($priorityArea[0]);
                    }
                }
            } catch (Exception $e) {
                $this->getLogger()->warning('Priorityarea could not be saved for Statement'.$statementAbwaegungstabelle->getId(), [$e]);
            }
        }

        return $statementAbwaegungstabelle;
    }

    /**
     * Hook für das Prozessieren von Geodaten zu Statements.
     *
     * @param int $limit
     *
     * @return int
     *
     * @throws Exception
     */
    public function processScheduledFetchGeoData($limit = 2)
    {
        if (!$this->permissions->hasPermission('feature_statement_geolocate_dataport')) {
            return 0;
        }

        $statements = [];
        $statementAttributes = $this->statementAttributeRepository->findBy(['type' => 'fetchGeodataPending'], null, $limit);
        if (!is_null($statementAttributes)) {
            foreach ($statementAttributes as $statementAttribute) {
                $statements[] = $statementAttribute->getStatement();
            }
        }
        if (0 < count($statements)) {
            $this->statementGeoService->saveStatementGeoData($statements);
        }

        return count($statements);
    }

    /**
     * Updates a Statement-Object without creating a report entry!
     * This method does not include any checks for claimed, clustered, manual, ..etc...!
     * Use {@see updateStatement()} instead.
     *
     * @return Statement the updated object
     *
     * @throws Exception
     */
    public function updateStatementObject(Statement $statement): Statement
    {
        return $this->statementRepository->updateStatementObject($statement);
    }

    /**
     * Returns a list of all external statementIds used in
     * the procedure that are currently being used.
     *
     * @param string $procedureId procedure of which we want to obtain external statement ids
     *
     * @return array|null an array filled with all external statement ids of the procedure
     */
    public function getInternIdsFromProcedure($procedureId): ?array
    {
        try {
            $result = [];
            $statements = $this->statementRepository
                ->getInternIdsOfStatementsOfProcedure($procedureId);

            foreach ($statements as $statement) {
                $result[] = $statement['internId'];
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Get internIds of statement of the procedure: '.$procedureId.' failed: ', [$e]);

            return null;
        }
    }

    /**
     * @return array<int, Statement>
     */
    public function getStatementsOfProcedureAndOrganisation(string $procedureId, string $organisationId): array
    {
        return $this->statementRepository->getStatementsOfProcedureAndOrganisation($procedureId, $organisationId);
    }

    /**
     * Get all HeadStatements of the given statementIds.
     *
     * @param array $statementIds
     */
    public function getHeadStatementIdsOfStatements($statementIds): \Tightenco\Collect\Support\Collection
    {
        $result = \collect([]);
        try {
            $statements = $this->statementRepository
                ->getAllStatementsOfHeadStatements($statementIds);

            /** @var Statement $clusterMember */
            foreach ($statements as $clusterMember) {
                if (!$result->contains($clusterMember->getHeadStatementId())) {
                    $result->push($clusterMember->getHeadStatementId());
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Get HeadStatement IDs of statements : '.DemosPlanTools::varExport($statementIds, true).' failed: ', [$e]);
        }

        return $result;
    }

    /**
     * @param string $procedureId
     *
     * @return Statement[]
     */
    public function getCititzenStatementsByProcedureId($procedureId)
    {
        return $this->statementRepository->getCitizenStatementsByProcedureId($procedureId);
    }

    /**
     * @param string $procedureId
     *
     * @return Statement[]
     */
    public function getInstitutionStatementsByProcedureId($procedureId)
    {
        return $this->statementRepository->getInstitutionStatementsByProcedureId($procedureId);
    }

    /**
     * Check if given Statement has set a HeadStatement
     * and if the HeadStatement is claimed by the current user.
     *
     * Check for already set HeadStatement of incoming updatedStatement is not necessary,
     * because member of Cluster are not allowed to update anyway.
     *
     * This method also generates the appropriate message.
     *
     * This method has to be able to handle a Statement Object as well as an array.
     *
     * @param array|Statement $updatedStatement
     *
     * @return bool - true if the given HeadStatement is locked, otherwise false
     *
     * @throws MessageBagException
     */
    private function checkStatementAddToClusterLocked($updatedStatement): bool
    {
        $isLocked = false;
        $headStatement = null;

        if ($updatedStatement instanceof Statement) {
            $headStatement = $updatedStatement->getHeadStatement();
        } elseif (\array_key_exists('headStatementId', $updatedStatement)) {
            $headStatement = $this->getStatement($updatedStatement['headStatementId']);
        }

        if (null !== $headStatement) {
            $isLocked = $this->isStatementObjectLockedByAssignment($headStatement);
        }

        if ($isLocked) {
            $assignedUser = $headStatement->getAssignee();
            if (null === $assignedUser) {
                $this->messageBag->add('warning', 'warning.statement.cluster.needLock');
            } else {
                $this->messageBag->add(
                    'warning', 'warning.statement.cluster.assigned.to',
                    ['name' => $assignedUser->getName(), 'organisation' => $assignedUser->getOrga()->getName()]
                );
            }
            $this->logger->info('Trying to add a Statement to a locked by assignment HeadStatement.');
        }

        return $isLocked;
    }

    protected function mapStatementToStatementId(Statement $statement): string
    {
        $id = $statement->getId();
        if (null === $id) {
            throw new InvalidArgumentException('Statement has no ID');
        }

        return $id;
    }

    /**
     * @throws ConnectionException
     * @throws NoTargetsException
     * @throws Exception
     */
    public function bulkEditStatementsAddData(StatementBulkEditVO $statementEdit): void
    {
        $targetIds = $statementEdit->getStatementIdsInProcedure()->getStatementIds();
        if (0 === (is_countable($targetIds) ? count($targetIds) : 0)) {
            throw new NoTargetsException('No statements given');
        }
        /** @var Statement[] $targetStatements */
        $targetStatements = $this->statementRepository->findBy(
            [
                'id'        => $targetIds,
                'procedure' => $statementEdit->getStatementIdsInProcedure()->getProcedureId(),
            ]
        );
        if (0 === count($targetStatements)) {
            throw new NoTargetsException('No statements found');
        }
        if (count($targetStatements) !== (is_countable($targetIds) ? count($targetIds) : 0)) {
            $e = new UnknownIdsException('Some statement IDs were not found.');
            $e->setExpectedIds($targetIds);
            $e->setFoundIds(\array_map($this->mapStatementToStatementId(...), $targetStatements));
            throw $e;
        }
        // transaction is needed here, because we want both the Statement changes and the
        // ContentChange creations inside a single transaction
        /** @var Connection $conn */
        $conn = $this->getDoctrine()->getConnection();
        try {
            $conn->beginTransaction();
            foreach ($targetStatements as $statement) {
                // update recommendation if it was changed
                $recommendationAddition = $statementEdit->getRecommendationAddition();
                if (null !== $recommendationAddition) {
                    $statement->addRecommendationParagraph($recommendationAddition);
                }

                // remember previous assignee for late update
                // will be null in case of not changing assignment:
                $ignoreAssignment = null !== $statementEdit->getAssigneeId();

                // update assignee if it was changed (assigneeId must not be null, to unset the assignee a separate field has to be used)
                $newAssigneeId = $statementEdit->getAssigneeId();
                if (null !== $newAssigneeId) {
                    try {
                        $newAssigneeUser = $this->userRepository->get($newAssigneeId);
                    } catch (NoResultException $e) {
                        throw new InvalidArgumentException('No user entity exists for the given ID. Hence assignee of statement can not be changed.', 0, $e);
                    }
                    $statement->setAssignee($newAssigneeUser);
                }

                $updatedStatement = $this->updateStatementFromObject($statement, $ignoreAssignment);
                if (!$updatedStatement instanceof Statement) {
                    throw new InvalidDataException('update of statement failed');
                }
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Gets the internal or external phase of the given statement depending on
     * the value set for the 'publicStatement' field.
     *
     * @param array $statement The statement entity as array
     *
     * @return string the internal or external phase of the given statement
     *
     * @deprecated use {@link getInternalOrExternalPhaseNameFromObject} instead
     */
    public function getInternalOrExternalPhaseName(array $statement): string
    {
        return $this->getPhaseNameFromStatementsPublicState(
            $statement['publicStatement'],
            $statement['phase']
        );
    }

    public function getInternalOrExternalPhaseNameFromObject(Statement $statement): string
    {
        return $this->getPhaseNameFromStatementsPublicState(
            $statement->getPublicStatement(),
            $statement->getPhase()
        );
    }

    protected function getPhaseNameFromStatementsPublicState(string $publicStatementValue, string $phase): string
    {
        return Statement::INTERNAL === $publicStatementValue
            ? $this->globalConfig->getPhaseNameWithPriorityInternal($phase)
            : $this->globalConfig->getPhaseNameWithPriorityExternal($phase);
    }

    /**
     * @param string $elementsId
     *
     * @return Statement[]
     */
    public function getStatementsAssignedToElementsId($elementsId)
    {
        return $this->statementRepository->findBy(['element' => $elementsId]);
    }

    /**
     * @param string $paragraphId
     *
     * @return Statement[]
     */
    public function getStatementsAssignedToParagraphVersionId($paragraphId)
    {
        return $this->statementRepository->findBy(['paragraph' => $paragraphId]);
    }

    /**
     * @deprecated use StatementService or StatementHandler instead
     */
    public function getStatementPublicRepository(): StatementRepository
    {
        return $this->statementRepository;
    }

    /**
     * @param array $filters ['fieldName' =>[mustMatchValues]]
     */
    public function getStatementsMovedToThisProcedureCount(Procedure $procedure, array $filters = []): StatementMovementCollection
    {
        $total = 0;
        $procedures = [];
        try {
            $this->profilerStart('ES');
            $boolQuery = new BoolQuery();
            $boolQuery->addMust($this->searchService->getElasticaTermsInstance('deleted', [false]));
            $boolQuery->addMust($this->searchService->getElasticaTermsInstance('pId', [$procedure->getId()]));

            foreach ($filters as $key => $values) {
                $boolQuery->addMust(
                    $this->searchService->getElasticaTermsInstance($key, $values)
                );
            }

            // generate Query
            $query = new Query();
            $query->setQuery($boolQuery);

            $query = $this->searchService->addEsAggregation($query, 'movedFromProcedureId');

            $search = $this->getEsStatementType();
            $elasticaAdapter = new ElasticaAdapter($search, $query);
            $paginator = new DemosPlanPaginator($elasticaAdapter);
            $paginator->setLimits(0); // ok because we need just aggregations
            $esResult = $paginator->getCurrentPageResults();
            $this->profilerStop('ES');

            $aggs = $esResult->getAggregations('movedFromProcedureId');

            foreach ($aggs as $agg) {
                foreach ($agg['buckets'] as $bucket) {
                    /** @var Procedure $procedure */
                    $procedure = $this->procedureService->getProcedure($bucket['key']);
                    $procedures[] = new StatementMovement(
                        'from-'.$procedure->getId(),
                        $procedure->getName(),
                        $bucket['doc_count']
                    );
                    $total += $bucket['doc_count'];
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getStatementsMovedToProcedureCount failed. ', [$e]);
        }

        return new StatementMovementCollection($procedures, $total);
    }

    /**
     * @param array $filters ['fieldName' =>[mustMatchValues]]
     */
    public function getStatementsMovedFromThisProcedureCount(Procedure $procedure, array $filters = []): StatementMovementCollection
    {
        $total = 0;
        $procedures = [];
        try {
            $this->profilerStart('ES');
            $boolQuery = new BoolQuery();
            $boolQuery->addMust($this->searchService->getElasticaTermsInstance('deleted', [false]));
            $boolQuery->addMust($this->searchService->getElasticaTermsInstance('pId', [$procedure->getId()]));
            $boolQuery->addMust($this->searchService->getElasticaTermsInstance('isPlaceholder', [true]));

            foreach ($filters as $key => $values) {
                $boolQuery->addMust(
                    $this->searchService->getElasticaTermsInstance($key, $values)
                );
            }

            // generate Query
            $query = new Query();
            $query->setQuery($boolQuery);

            $query = $this->searchService->addEsAggregation($query, 'movedToProcedureId');

            $search = $this->getEsStatementType();
            $elasticaAdapter = new ElasticaAdapter($search, $query);
            $paginator = new DemosPlanPaginator($elasticaAdapter);
            $paginator->setLimits(0); // ok because we need just aggregations
            $esResult = $paginator->getCurrentPageResults();
            $this->profilerStop('ES');

            $aggs = $esResult->getAggregations('movedToProcedureId');
            foreach ($aggs as $agg) {
                foreach ($agg['buckets'] as $bucket) {
                    $procedure = $this->procedureService->getProcedure($bucket['key']);
                    $procedures[] = new StatementMovement(
                        'to-'.$procedure->getId(),
                        $procedure->getName(),
                        $bucket['doc_count']
                    );
                    $total += $bucket['doc_count'];
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getStatementsMovedFromProcedureCount failed. ', [$e]);
        }

        return new StatementMovementCollection($procedures, $total);
    }

    /**
     * @param string[] $statementIds
     *
     * @return string[]
     */
    public function removePlaceholderStatementIds(array $statementIds): array
    {
        if ([] === $statementIds) {
            return [];
        }
        $placeholderStatementIds = $this->statementRepository->getPlaceholderStatementIds($statementIds);

        return array_diff($statementIds, $placeholderStatementIds);
    }

    /**
     * @param int|null $limit Will only be set if not null
     */
    public function addPaginationToParams(int $page, $limit, array $rParams = []): array
    {
        $rParams['page'] = $page;
        if (null !== $limit) {
            $rParams['request']['limit'] = $limit;
        }

        return $rParams;
    }

    /**
     * @return Statement[]
     */
    public function getSubmittedOrAuthoredStatements(string $userId): array
    {
        return $this->statementRepository->getSubmittedOrAuthoredStatements($userId);
    }

    /**
     * Determines for pre-GDPR statements which user IDs are relevant for the GDPR consent regarding the given
     * statement. The returned IDs do not indicate a given consent but that the consent of the user is relevant for the
     * given statement.
     *
     * @return array The relevant IDs, may contain null values when a consent of a person is relevant but no
     *               User entity could be found for that person in the database. The first value will be the
     *               submitter, the second one (if it is different from the submitter) the author
     *
     * @throws InvalidDataException
     */
    public function getInitialConsenteeIds(Statement $statement): array
    {
        return $this->statementRepository->getInitialConsenteeIds($statement);
    }

    public function getStatementPublicationNotificationEmailVariables(Statement $statement): array
    {
        return [
            'procedureTitle' => $statement->getProcedure()->getExternalName(),
            'procedureUrl'   => $this->router->generate(
                'DemosPlan_procedure_public_detail',
                ['procedure' => $statement->getProcedureId()]
            ),
            'statementText'  => $statement->getText(),
            'orgaEmail'      => $statement->getOrgaEmail(),
        ];
    }

    /**
     * Convert Filters from the request to an array understood by FragmentES method.
     *
     * @param array $userFilters
     *
     * @deprecated use a pre-filter approach utilizing {@link FluentRepository::getEntities()}
     *             instead and access the Elasticsearch index with the result
     */
    public function mapRequestFiltersToESFragmentFilters($userFilters): array
    {
        $userFragmentFilters = [];
        // Sachbearbeiter
        if (\array_key_exists('fragments_lastClaimed_id', $userFilters)) {
            $userFragmentFilters['lastClaimedUserId'] = $userFilters['fragments_lastClaimed_id'];
        }
        // Bearbeitungsstatus
        if (\array_key_exists('fragments_status', $userFilters)) {
            $userFragmentFilters['status'] = $userFilters['fragments_status'];
        }
        // Votum
        if (\array_key_exists('fragments_vote', $userFilters)) {
            $userFragmentFilters['vote'] = $userFilters['fragments_vote'];
        }
        // Kreis
        if (\array_key_exists('fragments_countyNames', $userFilters)) {
            $userFragmentFilters['countyNames'] = $userFilters['fragments_countyNames'];
        }
        // Gemeinde
        if (\array_key_exists('fragments_municipalityNames', $userFilters)) {
            $userFragmentFilters['municipalityNames'] = $userFilters['fragments_municipalityNames'];
        }
        // Schlagwort
        if (\array_key_exists('fragments_tagNames', $userFilters)) {
            $userFragmentFilters['tagNames'] = $userFilters['fragments_tagNames'];
        }
        // Potenzialflächen
        if (\array_key_exists('fragments.priorityAreaKeys', $userFilters)) {
            $userFragmentFilters['priorityAreaKeys'] = $userFilters['fragments.priorityAreaKeys'];
        }
        // Dokument
        if (\array_key_exists('fragments_element', $userFilters)) {
            $userFragmentFilters['element'] = $userFilters['fragments_element'];
        }
        // Kapitel
        if (\array_key_exists('fragments_paragraphParentId', $userFilters)) {
            $userFragmentFilters['paragraphParentId'] = $userFilters['fragments_paragraphParentId'];
        }
        // Datei
        if (\array_key_exists('fragments_documentParentId', $userFilters)) {
            $userFragmentFilters['documentParentId'] = $userFilters['fragments_documentParentId'];
        }
        // Fachbehörde
        if (\array_key_exists('fragments_reviewerName', $userFilters)) {
            $userFragmentFilters['departmentId'] = $userFilters['fragments_reviewerName'];
        }

        return $userFragmentFilters;
    }

    protected function getEntityContentChangeService(): EntityContentChangeService
    {
        return $this->entityContentChangeService;
    }

    /**
     * Extract values of $rParams['request'] into a new array if the key exists.
     *
     * @param array $data
     * @param bool  $isManualStatement determines if the new statement will be a manual statement
     *
     * @throws Exception
     */
    public function fillNewStatementArray($data, $isManualStatement = false): array
    {
        $statement = [];

        $statement['isManualStatement'] = $isManualStatement;

        if (\array_key_exists('r_author_name', $data)) {
            $statement['author_name'] = $data['r_author_name'];
            $statement['submit_name'] = $data['r_author_name'];
        }

        if (\array_key_exists('r_internId', $data)) {
            $statement['internId'] = $data['r_internId'];
        }
        if (\array_key_exists('r_orga_street', $data)) {
            $statement['orga_street'] = $data['r_orga_street'];
        }

        if (\array_key_exists('r_orga_postalcode', $data)) {
            $statement['orga_postalcode'] = $data['r_orga_postalcode'];
        }

        if (\array_key_exists('r_orga_city', $data)) {
            $statement['orga_city'] = $data['r_orga_city'];
        }

        if (\array_key_exists('r_orga_email', $data)) {
            $statement['orga_email'] = $data['r_orga_email'];
            // Globaleinstellung Rückmeldung per email (wichtig für SN von Bürgern)
            $statement['feedback'] = 'email';
            // Save that user wants feedback. Might be better dedicated checkbox to explicitly set wish
            // for feedback. Keep current implicit behavior to avoid BC break.
            $statement['author_feedback'] = true;
        }
        if (\array_key_exists('r_feedback', $data)) {
            $statement['feedback'] = $data['r_feedback'];
            // save that user wants some kind of feedback
            $statement['author_feedback'] = true;
        }

        if (\array_key_exists('r_orga_name', $data)) {
            $statement['orga_name'] = $data['r_orga_name'];
        }

        if (\array_key_exists('r_orga_department_name', $data)) {
            $statement['orga_department_name'] = $data['r_orga_department_name'];
        }

        if (\array_key_exists('r_text', $data)) {
            $statement['text'] = $data['r_text'];
        }

        if (\array_key_exists('r_memo', $data)) {
            $statement['memo'] = $data['r_memo'];
        }

        if (\array_key_exists('r_phase', $data)) {
            $statement['phase'] = $data['r_phase'];
        }

        if (\array_key_exists('r_created_date', $data)) {
            $statement['createdDate'] = $data['r_created_date'];
        }

        if (\array_key_exists('r_submitted_date', $data)) {
            // set default value if not set e.g. in manual statement
            if ('' === $data['r_submitted_date']) {
                $data['r_submitted_date'] = Carbon::now()->format('d.m.Y H:i:s');
            } else {
                $incomingDate = Carbon::createFromTimestamp(strtotime((string) $data['r_submitted_date']));
                $now = Carbon::now();
                // On CREATE: Enrich which current hour, minute and second, to allow distinct order by submitDate
                $incomingDate->setTime($now->hour, $now->minute, $now->second);
                $statement['submittedDate'] = $incomingDate->format('d.m.Y H:i:s');
            }
        }

        if (\array_key_exists('r_ident', $data)) {
            $statement['pId'] = $data['r_ident'];
        }

        //        do not set fileupload if emtpystring, because id '' will not be found and lead to error on add filecontainer
        if (\array_key_exists('fileupload', $data) && '' !== $data['fileupload']) {
            $statement['file'] = $data['fileupload'];
        }

        // get Gesamtstellungnahme as default:
        $statement['element'] = $this->serviceElements->getStatementElement($statement['pId']);

        if (\array_key_exists('r_element', $data) && 36 === \strlen((string) $data['r_element'])) {
            $statement['elementId'] = $data['r_element'];

            if (\array_key_exists('r_paragraph_'.$statement['elementId'], $data)) {
                $statement['paragraphId'] = $data['r_paragraph_'.$statement['elementId']];
                $statement['documentId'] = '';
            }

            if (\array_key_exists('r_document_'.$statement['elementId'], $data)) {
                $statement['documentId'] = $data['r_document_'.$statement['elementId']];
                $statement['paragraphId'] = '';
            }

            if (!\array_key_exists('r_document_'.$statement['elementId'], $data) && !\array_key_exists('r_paragraph_'.$statement['elementId'], $data)) {
                $statement['documentId'] = '';
                $statement['paragraphId'] = '';
            }
        }

        $statement['publicVerified'] = Statement::PUBLICATION_PENDING;
        if ($this->permissions->hasPermission('field_statement_public_allowed')) {
            if (\array_key_exists('r_publicVerified', $data) && !empty($data['r_publicVerified'])) {
                // validation is done in setPublicVerified in Statement
                $statement['publicVerified'] = $data['r_publicVerified'];
            }
        } else {
            $statement['publicVerified'] = Statement::PUBLICATION_NO_CHECK_SINCE_PERMISSION_DISABLED;
        }

        if (\array_key_exists('r_categories', $data)) {
            $statement['categories'] = $data['r_categories'];
        }

        // Kennzeichne manuelle SN von Bürgern und übergebe Ihnen den richtigen Wert für Feedback

        if (\array_key_exists('r_role', $data)) {
            if ('0' === $data['r_role']) {
                $statement['civic'] = true;
                $statement['meta'][StatementMeta::SUBMITTER_ROLE] = 'citizen';
            } else {
                $statement['civic'] = false;
                $statement['meta'][StatementMeta::SUBMITTER_ROLE] = 'publicagency';
            }
        }

        if (\array_key_exists('r_userState', $data) && 0 < \strlen((string) $data['r_userState'])) {
            $statement['meta']['userState'] = $data['r_userState'];
        }
        if (\array_key_exists('r_userGroup', $data) && 0 < \strlen((string) $data['r_userGroup'])) {
            $statement['meta']['userGroup'] = $data['r_userGroup'];
        }
        if (\array_key_exists('r_userOrganisation', $data) && 0 < \strlen((string) $data['r_userOrganisation'])) {
            $statement['meta']['userOrganisation'] = $data['r_userOrganisation'];
        }
        if (\array_key_exists('r_userPosition', $data) && 0 < \strlen((string) $data['r_userPosition'])) {
            $statement['meta']['userPosition'] = $data['r_userPosition'];
        }
        if (\array_key_exists('r_phone', $data) && 0 < \strlen((string) $data['r_phone'])) {
            $statement['meta'][StatementMeta::USER_PHONE] = $data['r_phone'];
        }

        if (\array_key_exists('r_authored_date', $data) && 0 < \strlen((string) $data['r_authored_date'])) {
            $statement['authoredDate'] = $data['r_authored_date'];
        }

        if (\array_key_exists('r_submit_type', $data)) {
            $statement['submitType'] = $data['r_submit_type'];
        }

        if (\array_key_exists('r_counties', $data)) {
            $statement['counties'] = $data['r_counties'];
        }

        if (\array_key_exists('r_municipalities', $data)) {
            $statement['municipalities'] = $data['r_municipalities'];
        }

        if (\array_key_exists('r_priorityAreas', $data)) {
            $statement['priorityAreas'] = $data['r_priorityAreas'];
        }

        if (\array_key_exists('r_tags', $data)) {
            $statement['tags'] = $data['r_tags'];
        }

        if (\array_key_exists('r_voters', $data)) {
            $statement['votes'] = $data['r_voters'];
        }

        if (\array_key_exists('r_voters_anonym', $data) && is_numeric($data['r_voters_anonym'])) {
            $statement['numberOfAnonymVotes'] = abs(intval($data['r_voters_anonym']));
        }

        if (\array_key_exists('r_head_statement', $data)) {
            $statement['headStatementId'] = $data['r_head_statement'];
        }

        if (\array_key_exists('r_recommendation', $data)) {
            $statement['recommendation'] = $data['r_recommendation'];
        }

        if (\array_key_exists('r_houseNumber', $data)) {
            $statement['houseNumber'] = $data['r_houseNumber'];
        }

        if (\array_key_exists('originalAttachments', $data)) {
            $originalAttachmentFiles = (new ArrayCollection($data['originalAttachments']))
                ->map(Closure::fromCallable($this->fileService->getFileIdFromUploadFile(...)))
                ->map(Closure::fromCallable($this->fileService->getFileById(...)));
            $statement['originalAttachmentFiles'] = $originalAttachmentFiles;
        }

        $statement['externId'] = $this->getNextValidExternalIdForProcedure($statement['pId'], $isManualStatement);

        return $statement;
    }

    /**
     * Returns the next unused externId of Statements AND Draftstatements within a specific procedure.
     * Take also the prefixed externIds into account.
     *
     * @param string $procedureId        - identifies the procedure
     * @param bool   $forManualStatement - determines if externId will leaded by a 'M'
     * @param int    $offset             - Allows to define an offset, which will be added to caluclated next valid external Id
     */
    public function getNextValidExternalIdForProcedure(string $procedureId, bool $forManualStatement = false, int $offset = 0): string
    {
        $externId = $this->statementRepository->getNextValidExternalIdForProcedure($procedureId);
        $externId += $offset;

        return $forManualStatement ? 'M'.$externId : $externId;
    }

    /**
     * Returns only original statements and these whose related procedure is not deleted.
     *
     * @return Statement[]
     */
    public function getOriginalStatements(): array
    {
        return $this->statementRepository->getOriginalStatements();
    }

    /**
     * Checks the corresponding Permission for the publicVerified field.
     * If enabled sets it to the received value.
     * Otherwise sets the default value.
     * If received publicVerified value not valid, throws an Exception.
     *
     * @throws UnexpectedValueException
     */
    public function setPublicVerified(
        Statement $statement,
        string $publicVerifiedWithPermissionEnabled): Statement
    {
        $publicVerified = $this->permissions->hasPermission('field_statement_public_allowed')
            ? $publicVerifiedWithPermissionEnabled
            : Statement::PUBLICATION_NO_CHECK_SINCE_PERMISSION_DISABLED;

        $statement->setPublicVerified($publicVerified);

        return $statement;
    }

    /**
     * @param array<string, mixed> $rParams
     *
     * @return array<string, array<string, string>>
     */
    private function getSorting(array $rParams): array
    {
        $sort = $this->maybeAddSort($rParams, []);
        if (!empty($sort) && \array_key_exists('sort', $sort) && '' !== $sort['sort']) {
            return $sort['sort'];
        }

        $sort = $this->getSortingJsonFormat($rParams);

        return null === $sort ? [] : $sort->toArray();
    }

    /**
     * @param array<string, mixed> $rParams
     */
    private function getSortingJsonFormat(array $rParams): ?ToBy
    {
        if (isset($rParams['currentTableSort'])) {
            $sortTo = str_starts_with((string) $rParams['currentTableSort'], '-')
                ? 'desc'
                : 'asc';

            $sortBy = str_starts_with((string) $rParams['currentTableSort'], '-')
                ? substr((string) $rParams['currentTableSort'], 1)
                : $rParams['currentTableSort'];

            return ToBy::create($sortBy, $sortTo);
        }

        return null;
    }

    /**
     * @param array<int,class-string> $allowedClasses
     */
    private function includeStatements(array $allowedClasses): bool
    {
        return \in_array(Statement::class, $allowedClasses, true);
    }

    /**
     * @param array<int,class-string> $allowedClasses
     */
    private function includeStatementFragments(array $allowedClasses): bool
    {
        return \in_array(StatementFragment::class, $allowedClasses, true);
    }

    /**
     * @param array<int,class-string> $entityClassesToInclude
     */
    private function getStatementAndItsFragmentsInOneFlatList(Statement $statement, array $entityClassesToInclude): \Tightenco\Collect\Support\Collection
    {
        $explodedStatement = \collect();

        if ($this->includeStatements($entityClassesToInclude)) {
            $explodedStatement->add($statement);
        }

        if ($this->includeStatementFragments($entityClassesToInclude)) {
            $explodedStatement->push(...$statement->getFragments());
        }

        return $explodedStatement;
    }

    /**
     * @return array<int, Statement>
     *
     * @throws PathException
     */
    public function getStatementsForSubmitterExport(string $procedureId): array
    {
        $condition = $this->conditionFactory->propertyHasValue(
            $procedureId,
            $this->statementResourceType->procedure->id
        );

        return $this->statementResourceType->getEntities([$condition], []);
    }

    public function addMissingSortKeys($sort, string $defaultPropertyName, string $defaultDirection): ToBy
    {
        $direction = $defaultDirection;
        if (\is_array($sort) && \array_key_exists('to', $sort)) {
            $direction = $sort['to'];
        }

        $propertyName = $defaultPropertyName;
        if (\is_array($sort) && \array_key_exists('by', $sort)) {
            $propertyName = $sort['by'];
        }

        return ToBy::create($propertyName, $direction);
    }

    private function addFilterToAggregationsWhenCausedResultIsEmpty(array $aggregations, array $userfilters): array
    {
        foreach ($userfilters as $label => $value) {
            if (\array_key_exists($label, $aggregations)
                    && \is_array($aggregations[$label])
                    && \array_key_exists('buckets', $aggregations[$label])
                    && empty($aggregations[$label]['buckets'])
            ) {
                // A filter was set by the user that caused an empty search result - therefore the filter ist not
                // set within the aggregations by default - add those filters manually to let the FE know we used a filter
                $aggregations[$label]['buckets'] = [['key' => $value[0], 'doc_count' => 0]];
            }
        }

        return $aggregations;
    }
}
