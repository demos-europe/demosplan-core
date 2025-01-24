<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterHandleImportedTagsRecordsEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterPrePersistTagsEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ManualStatementCreatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Handler\StatementHandlerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\GetOriginalFileFromAnnotatedStatementEvent;
use demosplan\DemosPlanCoreBundle\Event\MultipleStatementsSubmittedEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\ExcelImporterHandleImportedTagsRecordsEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\ExcelImporterPrePersistTagsEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\ManualStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Exception\AsynchronousStateException;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTitleException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTopicTitleException;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ErroneousDoctrineResult;
use demosplan\DemosPlanCoreBundle\Exception\GdprConsentRequiredException;
use demosplan\DemosPlanCoreBundle\Exception\HandlerException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\NotAllStatementsGroupableException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedurePublicationException;
use demosplan\DemosPlanCoreBundle\Exception\StatementFragmentNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNameTooLongException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\TagNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\TagTopicNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ArrayHelper;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\Document\SingleDocumentService;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FlashMessageHandler;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\LinkMessageSerializable;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentVersionRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementVoteRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SimilarStatementSubmitterResourceType;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryFragment;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResultSet;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\CountyNotificationData;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\PdfFile;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\QueryException;
use Exception;
use Illuminate\Support\Collection;
use League\Csv\Reader;
use League\Csv\UnableToProcessCsv;
use ReflectionException;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use Webmozart\Assert\Assert;
use function array_key_exists;
use function is_string;

class StatementHandler extends CoreHandler implements StatementHandlerInterface
{
    use RefreshElasticsearchIndexTrait;

    /** @var DraftStatementService */
    protected $draftStatementService;

    /** @var DraftStatementHandler */
    protected $draftStatementHandler;

    /** @var bool */
    protected $displayNotices = true;

    /** @var StatementService */
    protected $statementService;

    /** @var AssignService */
    protected $assignService;

    /** @var StatementFragmentService */
    protected $statementFragmentService;

    /** @var EntityContentChangeService */
    protected $entityContentChangeService;

    /** @var PriorityAreaService */
    protected $priorityAreaService;

    /** @var CountyService */
    protected $countyService;

    /** @var MunicipalityService */
    protected $municipalityService;

    /** @var ServiceImporter */
    protected $serviceImporter;

    /** @var ProcedureService */
    protected $procedureService;

    /** @var Permissions */
    protected $permissions;

    /** @var ProcedureHandler */
    protected $procedureHandler;

    /** @var ServiceOutput */
    protected $procedureOutput;

    /** @var UserService */
    protected $userService;

    /** @var MailService */
    protected $mailService;

    /** @var Environment */
    protected $twig;

    /** @var QueryFragment */
    protected $esQueryFragment;

    /** @var StatementClusterService */
    protected $statementClusterService;
    /**
     * @var TagService
     */
    protected $tagService;

    /** @var StatementCopyAndMoveService */
    protected $statementCopyAndMoveService;

    /** @var ParagraphService */
    protected $paragraphService;

    public function __construct(
        private readonly ArrayHelper $arrayHelper,
        AssignService $assignService,
        CountyService $countyService,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserInterface $currentUser,
        DraftStatementHandler $draftStatementHandler,
        DraftStatementService $draftStatementService,
        private readonly EditorService $editorService,
        private readonly ElementsService $elementsService,
        EntityContentChangeService $entityContentChangeService,
        private readonly EntityManagerInterface $entityManager,
        Environment $twig,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FlashMessageHandler $flashMessageHandler,
        private FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly JsonApiActionService $jsonApiActionService,
        MailService $mailService,
        MessageBagInterface $messageBag,
        MunicipalityService $municipalityService,
        private readonly OrgaService $orgaService,
        ParagraphService $paragraphService,
        PermissionsInterface $permissions,
        PriorityAreaService $priorityAreaService,
        ProcedureHandler $procedureHandler,
        ProcedureService $procedureService,
        QueryFragment $esQueryFragment,
        ServiceImporter $serviceImporter,
        ServiceOutput $procedureOutput,
        private readonly SimilarStatementSubmitterResourceType $similarStatementSubmitterResourceType,
        private readonly SingleDocumentService $singleDocumentService,
        StatementClusterService                $statementClusterService,
        StatementCopyAndMoveService            $statementCopyAndMoveService,
        StatementFragmentService               $statementFragmentService,
        StatementService                       $statementService,
        TagService                             $tagService,
        private readonly TranslatorInterface   $translator,
        UserService                            $userService,
        private readonly StatementCopier       $statementCopier,
        private readonly ValidatorInterface    $validator,
        private readonly StatementDeleter      $statementDeleter, private readonly TagRepository $tagRepository, private readonly TagTopicRepository $tagTopicRepository,
    ) {
        parent::__construct($messageBag);
        $this->assignService = $assignService;
        $this->countyService = $countyService;
        $this->draftStatementHandler = $draftStatementHandler;
        $this->draftStatementService = $draftStatementService;
        $this->entityContentChangeService = $entityContentChangeService;
        $this->esQueryFragment = $esQueryFragment;
        $this->mailService = $mailService;
        $this->municipalityService = $municipalityService;
        $this->paragraphService = $paragraphService;
        $this->permissions = $permissions;
        $this->priorityAreaService = $priorityAreaService;
        $this->procedureHandler = $procedureHandler;
        $this->procedureOutput = $procedureOutput;
        $this->procedureService = $procedureService;
        $this->serviceImporter = $serviceImporter;
        $this->statementClusterService = $statementClusterService;
        $this->statementCopyAndMoveService = $statementCopyAndMoveService;
        $this->statementFragmentService = $statementFragmentService;
        $this->statementService = $statementService;
        $this->tagService = $tagService;
        $this->twig = $twig;
        $this->userService = $userService;
    }

    /**
     * Save a statement created by a unregistered citizen to the database.
     *
     * @throws GdprConsentRequiredException thrown if GDPR consent is required but was not given
     * @throws MessageBagException
     * @throws Throwable
     */
    public function savePublicStatement(string $procedureId): Statement
    {
        $inData = $this->prepareIncomingData('statementpublicnew');

        // check if GDPR consent was given, regardless of the feature_statement_gdpr_consent permission
        $gdprConsentReceived = array_key_exists('r_gdpr_consent', $inData) && 'on' === $inData['r_gdpr_consent'];

        // Formulardaten einsammeln
        $inData['r_uploaddocument'] = '';

        // Storage Formulardaten übergeben
        $storageDraftResult = $this->newPublicHandler(
            $procedureId,
            $inData
        );

        if (false == $storageDraftResult
            || !array_key_exists('ident', $storageDraftResult)
        ) {
            throw new Exception('Speichern der Daten fehlgeschlagen');
        }

        // Bei erfolgter Speicherung des Entwurfs wird die Stellungnahme direkt freigegeben.
        $draftStatementIdent = $storageDraftResult['ident'];
        $this->releaseDraftStatement($draftStatementIdent);
        // Bei erfolgter Freigabe des Stellungnahme wird  sie direkt eingereicht
        $submittedStatements = $this->submitStatement($draftStatementIdent, '', true, $gdprConsentReceived);

        if ($this->isDisplayNotices()) {
            $this->getMessageBag()->add(
                'confirm',
                $this->translator->trans('confirm.statement.saved')
            );
        }

        return $submittedStatements[0];
    }

    /**
     * Verarbeitet alle eingegebenen Daten aus dem Neu-Formular aus der Öffentlichkeitsbeteiligung
     * Liefert das Ergebnis aus dem Webservice.
     *
     * @param string $procedureId
     * @param array  $data
     *
     * @return false|array
     *
     * @throws Exception
     */
    protected function newPublicHandler($procedureId, $data)
    {
        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('statementpublicnew' !== $data['action']) {
            return false;
        }

        // fülle $statement mit den bereinigten Werten aus $data
        $statement = $this->draftStatementHandler->getStatementStandardData($procedureId, $data);

        // prüfe die spezifischen Anforderungen
        $statement = $this->getStatementPublicData($data, $statement);

        // Kennzeichnet, dass Stellungnahme aus der Beteiligungsebene eingereicht wurde. Nötig um eventuelle
        // Daten zur Organisation aus der Session zu entfernen (z.B. bei eingeloggten Fachplaner)
        $statement['anonym'] = true;

        return $this->draftStatementService->addDraftStatement($statement);
    }

    public function getStatement($id): ?Statement
    {
        return $this->statementService->getStatement($id);
    }

    /**
     * @throws StatementNotFoundException
     */
    public function getStatementWithCertainty(string $statementId): Statement
    {
        $statement = $this->statementService->getStatement($statementId);
        if (!$statement instanceof Statement) {
            throw StatementNotFoundException::createFromId($statementId);
        }

        return $statement;
    }

    /**
     * Returns one StatementFragment.
     *
     * @param string $fragmentId
     */
    public function getStatementFragment($fragmentId): ?StatementFragment
    {
        return $this->statementFragmentService->getStatementFragment($fragmentId);
    }

    /**
     * @throws StatementFragmentNotFoundException
     */
    public function getNonNullStatementFragment(string $fragmentId): StatementFragment
    {
        return $this->statementFragmentService->getNonNullStatementFragment($fragmentId);
    }

    /**
     * Returns all existing StatementFragments for a Statement.
     *
     * @param string $statementId
     *
     * @return StatementFragment[]|null
     */
    public function getStatementFragmentsStatement($statementId)
    {
        return $this->statementFragmentService->getStatementFragmentsStatement($statementId);
    }

    /**
     * Returns StatementFragments, related to a specific department.
     *
     * @param string $departmentId
     *
     * @return StatementFragment[]|null
     *
     * @throws Exception
     */
    public function getStatementFragmentsDepartment($departmentId)
    {
        $esQuery = $this->getEsQueryFragment();
        $esQuery->addFilterMust('departmentId', $departmentId);
        $sort = $esQuery->getAvailableSort('assignedToFbDate');
        $sort->setDirection('desc');
        $esQuery->setSort([$sort]);

        return $this->statementFragmentService->getStatementFragmentsDepartment($esQuery, $this->getRequestValues());
    }

    /**
     * Returns edited archived StatementFragments, related to a specific department.
     *
     * @param string $departmentId
     *
     * @return StatementFragment[]|null
     */
    public function getStatementFragmentsDepartmentArchive($departmentId)
    {
        $esQuery = $this->getEsQueryFragment();
        $esQuery->addFilterMust('versions.modifiedByDepartmentId', $departmentId);
        $esQuery->addFilterMustMissing('departmentId');
        $esQuery->setIncludeVersions(true);
        $sort = $esQuery->getAvailableSort('versionCreated');
        $sort->setDirection('desc');
        $esQuery->setSort([$sort]);

        return $this->statementFragmentService->getStatementFragmentsDepartmentArchive($esQuery, $this->getRequestValues(), $departmentId);
    }

    public function addSourceStatementAttachments(array $statements)
    {
        return $this->statementService->addSourceStatementAttachments($statements);
    }

    /**
     * get vars from request.
     *
     * @return array
     *
     * @deprecated
     */
    protected function getRequestVars()
    {
        // Always give names to variables, that describe, what they do
        $rereplacedDotsWithUnderscoreRequestVars = [];
        foreach ($this->getRequestValues() as $name => $val) {
            $indexName = str_replace('_', '.', $name);
            if (!array_key_exists($indexName, $rereplacedDotsWithUnderscoreRequestVars) || !is_array($rereplacedDotsWithUnderscoreRequestVars[$indexName])) {
                $rereplacedDotsWithUnderscoreRequestVars[$indexName] = [];
            }
            $rereplacedDotsWithUnderscoreRequestVars[$indexName][] = $val;
        }

        return $rereplacedDotsWithUnderscoreRequestVars;
    }

    /**
     * Returns versions of a StatementFragment.
     *
     * @param string $fragmentId
     * @param string $departmentId
     * @param bool   $isReviewer
     *
     * @return array|null
     */
    public function getStatementFragmentVersions($fragmentId, $departmentId, $isReviewer = true)
    {
        return $this->statementFragmentService->getStatementFragmentVersions($fragmentId, $departmentId, $isReviewer);
    }

    /**
     * @param bool $propagateTags add tags added to this fragment to the corresponding statement as well
     *
     * @return StatementFragment|false|null
     *
     * @throws MessageBagException
     * @throws Exception
     */
    protected function updateStatementFragmentAndRelatedStatement(string $fragmentId, StatementFragment $fragmentToUpdate, array $statementFragmentData, bool $isReviewer, bool $notifyReviewer, bool $propagateTags = true)
    {
        $formerDepartmentId = $fragmentToUpdate->getDepartmentId();

        // T12218 T12304:text has changed && has obscured text? -> infrom user, that related statement, are not obscured automatically
        if (array_key_exists('text', $statementFragmentData)
            && $this->editorService->hasObscuredText($statementFragmentData['text'])) {
            $this->getMessageBag()->add('warning', 'warning.not.obscured.text.in.statement');
        }

        // get areaInformation which will be deleted
        $areaInformationIDsToDelete = $this->calculateParentPropertyIdsToDelete($statementFragmentData, $fragmentToUpdate);

        // this needs to be done before the fragment is updated
        if ($propagateTags) {
            // add only tags to the related statement that were added to the fragment
            $previousTagIds = $fragmentToUpdate->getTagIds();
            $currentTagIds = $statementFragmentData['tags'] ?? [];
            $newTagIds = array_diff($currentTagIds, $previousTagIds);
        } else {
            $newTagIds = [];
        }

        $statementFragmentData['id'] = $fragmentId;
        $result = $this->statementFragmentService->updateStatementFragment($statementFragmentData, false, $isReviewer);

        // on successfully update only:
        if ($result instanceof StatementFragment) {
            $relatedFragments = $this->getStatementFragmentsStatement($result->getStatementId());

            // get areaInformation which are now on the related Statement only
            $isolatedAreaInformation = $this->getIsolatedInformationIds($areaInformationIDsToDelete, $relatedFragments);

            // generate warning for user
            $this->generateIsolatedCountyMessage($isolatedAreaInformation->get('counties'), $result->getStatement());
            $this->generateIsolatedPriorityAreaMessage($isolatedAreaInformation->get('priorityAreas'), $result->getStatement());
            $this->generateIsolatedMunicipalityMessage($isolatedAreaInformation->get('municipalities'), $result->getStatement());
            $this->generateIsolatedTagMessage($isolatedAreaInformation->get('tags'), $result->getStatement());

            $this->getMessageBag()->add('confirm', 'confirm.fragment.edit', ['id' => $fragmentToUpdate->getDisplayId()]);

            $newTags = collect($result->getTags())->filter(static fn (Tag $tag) => in_array($tag->getId(), $newTagIds, true));
            $this->addAdditionalAreaInformationToStatement($result, $newTags);

            // reset assignment when planner changed reviewer but not to null
            // @link https://yaits.demos-deutschland.de/w/demosplan/functions/claim/ wiki: claiming
            $departmentId = $result->getDepartmentId();
            if (!$isReviewer && $formerDepartmentId !== $departmentId && null !== $departmentId) {
                $this->setAssigneeOfStatementFragment($fragmentToUpdate);
            }
        }

        if ($notifyReviewer && null !== $result && false !== $result) {
            try {
                $this->notifyReviewerOfFragment($result, $isReviewer);
            } catch (Exception) {
                $this->getMessageBag()->add('error', 'warning.fragment.assignment.information');
            }
        }

        return $result;
    }

    /**
     * Update a specific StatementFragment by array data.
     *
     * @param string $fragmentId identifies the statement to update
     * @param array  $data       contains the data to update
     * @param bool   $isReviewer determines if the update is initiated by a reviewer
     *
     * @return StatementFragment|false|null
     *
     * @throws Exception
     */
    public function updateStatementFragment($fragmentId, $data, bool $isReviewer)
    {
        $fragmentToUpdate = $this->statementFragmentService->getStatementFragment($fragmentId);

        if (!($fragmentToUpdate instanceof StatementFragment)) {
            throw new EntityNotFoundException('StatementFragment not found: '.$fragmentId);
        }

        $statementFragmentData = $this->createStatementFragmentArrayFromPostData($data, $fragmentToUpdate, $isReviewer);

        return $this->updateStatementFragmentAndRelatedStatement($fragmentId, $fragmentToUpdate, $statementFragmentData, $isReviewer, array_key_exists('r_notify', $data));
    }

    /**
     * @param bool $propagateTags add tags added to this fragment to the corresponding statement as well
     *
     * @return bool|StatementFragment|false|null
     *
     * @throws MessageBagException
     */
    public function updateStatementFragmentFromResource(string $statementFragmentId, ResourceObject $resource, bool $isReviewer, bool $notifyReviewer, bool $propagateTags = true)
    {
        $fragmentToUpdate = $this->statementFragmentService->getStatementFragment($statementFragmentId);

        if (!($fragmentToUpdate instanceof StatementFragment)) {
            $this->getLogger()->error('Could not update StatementFragment, Fragment not found: '.$statementFragmentId);

            return false;
        }

        /** @var array $updateFields */
        $updateFields = $resource['attributes'];
        if (array_key_exists('element', $updateFields)) {
            $updateFields['elementId'] = $updateFields['element'];
            unset($updateFields['element']);
        }
        if (array_key_exists('paragraph', $updateFields)) {
            $updateFields['paragraphId'] = $updateFields['paragraph'];
            unset($updateFields['paragraph']);
        }

        /* The following fields need to be handled in a special way because
         * the update method in the repository assumes the values in the
         * StatementFragment should be deleted if the field does not
         * exist in the updateData array. However what we want is to leave
         * the values unchanged if the field(s) does not exist. Hence
         * we set the field to the values stored in the database so nothing
         * is deleted in updateStatementFragmentAndRelatedStatement.
         */
        if (!array_key_exists('tags', $updateFields)) {
            $updateFields['tags'] = $fragmentToUpdate->getTagIds();
        }
        if (!array_key_exists('municipalities', $updateFields)) {
            $updateFields['municipalities'] = $fragmentToUpdate->getMunicipalityIds();
        }
        if (!array_key_exists('counties', $updateFields)) {
            $updateFields['counties'] = $fragmentToUpdate->getCountyIds();
        }
        if (!array_key_exists('element', $updateFields)) {
            $updateFields['element'] = $fragmentToUpdate->getElementId();
        }
        if (!array_key_exists('paragraph', $updateFields)) {
            $updateFields['paragraph'] = $fragmentToUpdate->getParagraphId();
        }
        if (!array_key_exists('priorityAreas', $updateFields)) {
            $updateFields['priorityAreas'] = $fragmentToUpdate->getPriorityAreaIds();
        }

        /*
         * The consideration needs to be copied into the considerationAdvice field if the reviewer
         * is set.
         * See StatementHandler::handleSetReviewerAndSideEffects for more informations.
         */
        if (array_key_exists('departmentId', $updateFields)) {
            if (!array_key_exists('consideration', $updateFields)) {
                $updateFields['consideration'] = $fragmentToUpdate->getConsideration();
            }
            $updateFields = $this->handleSetReviewerAndSideEffects([
                'r_reviewer' => $updateFields['departmentId'],
            ], $fragmentToUpdate, $updateFields);
        }

        return $this->updateStatementFragmentAndRelatedStatement($statementFragmentId, $fragmentToUpdate, $updateFields, $isReviewer, $notifyReviewer, $propagateTags);
    }

    /**
     * Delete a specifc statementfragment.
     *
     * @param string $fragmentId
     *
     * @throws EntityIdNotFoundException
     * @throws LockedByAssignmentException
     */
    public function deleteStatementFragment($fragmentId, bool $ignoreAssignment = false): bool
    {
        $statementService = $this->statementService;
        $fragmentToDelete = $this->statementFragmentService->getStatementFragment($fragmentId);

        if (null === $fragmentToDelete) {
            $this->getLogger()->error('Could not delete StatementFragment, Fragment not found: '.$fragmentId);

            return false;
        }

        $statement = $fragmentToDelete->getStatement();

        return $this->statementFragmentService->deleteStatementFragment($fragmentId, $ignoreAssignment);
    }

    /**
     * @return string[]
     */
    protected function getExtraEmailAddressStringsFromProcedure(Procedure $procedure): array
    {
        return $procedure->getAgencyExtraEmailAddresses()
            ->map(
                static fn (EmailAddress $emailAddress) => $emailAddress->getFullAddress()
            )->toArray();
    }

    /**
     * Send a notification mail to the currently assigned organization of a
     * Statement Fragment.
     *
     * @param StatementFragment $fragment
     * @param bool              $isReviewer
     *
     * @throws Exception
     */
    protected function notifyReviewerOfFragment($fragment, $isReviewer)
    {
        $procedure = $fragment->getProcedure();
        $fragmentDepartment = $fragment->getDepartment();
        $procedureOrga = $procedure->getOrga();

        $ccAddresses = [];
        if ($isReviewer) {
            if (null === $procedureOrga) {
                return;
            }
            $toAddress = $procedure->getAgencyMainEmailAddress();
            $agencyExtraEmailAddresses = $this->getExtraEmailAddressStringsFromProcedure($procedure);
            $ccAddresses = array_merge($ccAddresses, $agencyExtraEmailAddresses);
        } else {
            if (null === $fragmentDepartment) {
                return;
            }
            $toAddress = [];
            foreach ($fragmentDepartment->getUsers() as $user) {
                if ($this->permissions->hasPermission('feature_statements_fragment_advice')) {
                    $toAddress[] = $user->getEmail();
                }
            }
        }
        if (null === $toAddress || '' == $toAddress || [] == $toAddress) {
            $this->getLogger()->warning('could not send email to '.($isReviewer ? $procedureOrga->getName() : $fragmentDepartment->getName()).'. there seems to be no email adress');

            return;
        }

        $vars = [];

        $emails = [];
        $fragmentOrga = '';
        if ($fragmentDepartment instanceof Department) {
            $fragmentOrga = $fragmentDepartment->getOrga();
        }

        if ($isReviewer) {
            $orgaName = $this->currentUser->getUser()->getOrganisationNameLegal();
            $orgaId = $this->currentUser->getUser()->getOrganisationId();

            $subject = $this->translator->trans('fragment.evaluated.notification.subject');
            $emailText = $this->twig
                ->load('@DemosPlanCore/DemosPlanStatement/email_fragment_notify_planner.html.twig')
                ->render(
                    [
                        'templateVars' => [
                            'title'        => $procedure->getName(),
                            'organisation' => $orgaName,
                            'procedureId'  => $procedure->getId(),
                            'fragmentId'   => $fragment->getId(),
                        ],
                    ]
                );
            $emails[] = [
                'subject' => $subject,
                'body'    => $emailText,
                'to'      => $toAddress,
                'cc'      => $ccAddresses,
            ];

            // try to send email to reviewer admin if needed
            if ($this->permissions->hasPermission('feature_organisation_email_reviewer_admin')) {
                $userOrga = $this->getOrgaService()->getOrga($orgaId);
                if ($userOrga instanceof Orga) {
                    $toAddress = $userOrga->getEmailReviewerAdmin();

                    $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
                    $constraints = $validator->validate($toAddress, [new Email()]);
                    if (0 === (is_countable($constraints) ? count($constraints) : 0)) {
                        $subject = $this->translator->trans(
                            'email.subject.reviewer.admin.orga.reassign.fragment'
                        );
                        $emailText = $this->twig
                            ->load('@DemosPlanCore/DemosPlanStatement/email_fragment_notify_reviewer_admin_reassign_planner.html.twig')
                            ->render([
                                'templateVars' => [
                                    'title'     => $procedure->getName(),
                                    'procedure' => $procedure,
                                    'fragment'  => $fragment,
                                ],
                            ]);

                        $fragmentId = $fragment->getId();
                        $procedureId = $procedure->getId();
                        $pdfResult = $this->generateFragmentPdf($fragmentId, $procedureId);

                        $emails[] = [
                            'subject'     => $subject,
                            'body'        => $emailText,
                            'to'          => $toAddress,
                            'attachments' => [$pdfResult],
                            'cc'          => $ccAddresses,
                        ];
                    }
                }
            }
        } else {
            $subject = $this->translator->trans('fragment.assigned.notification.subject');
            $emailText = $this->twig
                ->load('@DemosPlanCore/DemosPlanStatement/email_fragment_notify_reviewer.html.twig')
                ->render(
                    [
                        'templateVars' => [
                            'title'          => $procedure->getName(),
                            'organisation'   => $fragmentOrga,
                            'procedureId'    => $procedure->getId(),
                            'fragmentId'     => $fragment->getId(),
                            'departmentName' => $fragmentDepartment->getName(),
                        ],
                    ]
                );
            $emails[] = [
                'subject' => $subject,
                'body'    => $emailText,
                'to'      => $toAddress,
                'cc'      => $ccAddresses,
            ];

            // try to send email to reviewer admin if needed
            if ($this->permissions->hasPermission('feature_organisation_email_reviewer_admin')) {
                $toAddress = $fragment->getDepartment()
                    ->getOrga()
                    ->getEmailReviewerAdmin();
                if (filter_var($toAddress, FILTER_VALIDATE_EMAIL)) {
                    $subject = $this->translator->trans('email.subject.reviewer.admin.orga.new.fragment');
                    $emailText = $this->twig
                        ->load(
                            '@DemosPlanCore/DemosPlanStatement/email_fragment_notify_reviewer_admin_new.html.twig'
                        )
                        ->render(
                            [
                                'templateVars' => [
                                    'title'     => $procedure->getName(),
                                    'procedure' => $procedure,
                                    'fragment'  => $fragment,
                                ],
                            ]
                        );

                    $pdfResult = $this->generateFragmentPdf($fragment->getId(), $procedure->getId());

                    $emails[] = [
                        'subject'     => $subject,
                        'body'        => $emailText,
                        'to'          => $toAddress,
                        'attachments' => [$pdfResult],
                        'cc'          => $ccAddresses,
                    ];
                }
            }
        }

        foreach ($emails as $email) {
            $scope = 'extern';
            $vars['mailbody'] = $email['body'];
            $vars['mailsubject'] = $email['subject'];
            $toAddress = $email['to'];
            $attachments = array_key_exists('attachments', $email) ? $email['attachments'] : [];
            $attachments = array_map(static fn (PdfFile $pdfFile): array => $pdfFile->toArray(), $attachments);

            // schicke E-Mail ab
            $this->mailService->sendMail(
                'dm_subscription',
                'de_DE',
                $toAddress,
                '',
                $email['cc'],
                '',
                $scope,
                $vars,
                $attachments
            );
        }
    }

    /**
     * Loads a confirmation text to be shown after the given statement was submitted by a user.
     * The text is loaded from the {@link ProcedureUiDefinition::getStatementPublicSubmitConfirmationText()}
     * of the statement. It may contain a placeholder, in which case such placeholder is replaced
     * by the extern ID of the given statement and the result returned.
     *
     * The actual placeholder can be any non-empty string. It will be identified by a surrounding
     * HTML `span` element with the attribute `data-mention-id` with the
     * {@link ProcedureUiDefinition::STATEMENT_PUBLIC_SUBMIT_CONFIRMATION_TEXT_PLACEHOLDER appropriate value}.
     * The `span` element will be replaced by the extern ID inside a `strong` HTML element.
     */
    public function getPresentableStatementSubmitConfirmationText(string $externId, Procedure $procedure): string
    {
        $text = $procedure->getProcedureUiDefinition()->getStatementPublicSubmitConfirmationText();
        $placeholderId = ProcedureUiDefinition::STATEMENT_PUBLIC_SUBMIT_CONFIRMATION_TEXT_PLACEHOLDER;
        $replacePattern = "/<span.+data-mention-id=\"$placeholderId\".*>.+<\/span>/";
        $replacementValue = "<strong>{$externId}</strong>";

        return preg_replace($replacePattern, $replacementValue, (string) $text);
    }

    /**
     * Prüfe die spezifischen Felder der Öffentlichkeitsbeteiligung.
     *
     * @param array $data
     * @param array $statement
     *
     * @return array $statement
     *
     * @throws ValidatorException
     */
    protected function getStatementPublicData($data, $statement)
    {
        $mandatoryErrors = [];

        // Stellungnahmen aus der Beteiligungsebene dürfen keine Tags haben
        if (array_key_exists('r_text', $data)) {
            $data['r_text'] = str_replace("\r\n", '', (string) $data['r_text']);
            $statement['text'] = strip_tags(
                $data['r_text'],
                '<a><br><em><i><li><mark><ol><p><s><span><strike><strong><u><ul>'
            );
        }

        if (!array_key_exists('r_privacy', $data) || '' === trim((string) $data['r_privacy'])) {
            $mandatoryErrors[] = $this->createMandatoryErrorMessage('privacy');
        }
        if (!array_key_exists('r_text', $data) || '' === trim((string) $data['r_text'])) {
            $mandatoryErrors[] = $this->createMandatoryErrorMessage('statementtext');
        }

        $statement['publicAllowed'] = false;
        if (array_key_exists('r_makePublic', $data) && 'off' !== $data['r_makePublic']) {
            $statement['publicAllowed'] = true;
        }

        if (array_key_exists('r_location', $data) && 'notLocated' === $data['r_location']) {
            $statement['statementAttributes']['noLocation'] = true;
        }

        // in 3 Fällen wird r_location == point übergeben: Ortsbezug, Vorranggebietsauswahl und Ortseinzeichung
        if (array_key_exists('r_location', $data) && 'point' === $data['r_location']) {
            // Punkteinzeichnung
            if (array_key_exists('r_location_geometry', $data) && 0 < strlen((string) $data['r_location_geometry'])) {
                $statement['polygon'] = $data['r_location_geometry'];
            }

            // Vorranggebiet
            if (array_key_exists('r_location_priority_area_key', $data) && 0 < strlen(
                (string) $data['r_location_priority_area_key']
            )
            ) {
                $statement['statementAttributes']['priorityAreaKey'] = $data['r_location_priority_area_key'];
            }

            // Ortsbezug
            if (array_key_exists('r_location_point', $data) && 0 < strlen((string) $data['r_location_point'])) {
                try {
                    // wandle die Punktkoordinate in ein valides GeoJson um
                    $statement['polygon'] = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":['.$data['r_location_point'].']},"properties":null}]}';
                } catch (Exception $e) {
                    $this->getLogger()->warning('Could not create Point Polygon', ['data' => $data['r_location_point'], 'exception' => $e]);
                }
            }
        }

        if (array_key_exists('r_county', $data) && 0 < strlen((string) $data['r_county'])) {
            $statement['statementAttributes']['county'] = $data['r_county'];
        } elseif (array_key_exists('r_county', $data)) {
            $statement['statementAttributes']['county'] = '';
        }

        // Alle Stellungnahmen sind externe SN
        $statement['publicStatement'] = Statement::EXTERNAL;

        // Vorname Nachname nur mit Leerzeichen zusammensetzen, wenn beides ausgefüllt
        $userName = [];

        if (array_key_exists('r_firstname', $data)
            && isset($data['r_firstname'])
        ) {
            $userName[] = $data['r_firstname'];
        }
        if (array_key_exists('r_lastname', $data)
            && isset($data['r_lastname'])
        ) {
            $userName[] = $data['r_lastname'];
        }
        $statement['uName'] = implode(' ', $userName);

        // Prüfen, ob Name übergeben werden soll
        if (array_key_exists('r_useName', $data)) {
            if (0 == $data['r_useName']) {
                $statement['useName'] = false;
                $statement['anonymous'] = true;
                $statement['uName'] = '';
            } else {
                $statement['useName'] = true;
                if (array_key_exists('r_street', $data)) {
                    $statement['uStreet'] = $data['r_street'];
                }
                if (array_key_exists('r_houseNumber', $data)) {
                    $statement['houseNumber'] = $data['r_houseNumber'];
                }
                if (array_key_exists('r_postalCode', $data)) {
                    $statement['uPostalCode'] = $data['r_postalCode'];
                }
                if (array_key_exists('r_city', $data)) {
                    $statement['uCity'] = $data['r_city'];
                }
            }
        }

        // Wenn Rückmeldung gewünscht, dann speicher die Rückmeldungsvariante ab
        $statement['feedback'] = '';
        $statement['uFeedback'] = false;
        // r_email: stores email address: probably stored in draftstatement::umail
        // feedback: type of email: snailmail, email; filled from r_getEvaluation; stored in draftstatement in 'feedback'
        // r_getFeedback: 1 or not present, indicating if feedback of any kind is desired; stored in DraftStatement::uFeedback as boolean
        // @improve T20546 Validation
        if (array_key_exists('r_getFeedback', $data) && array_key_exists('r_getEvaluation', $data)) {
            $statement['uFeedback'] = true;
            $statement['feedback'] = $data['r_getEvaluation'];

            if ('snailmail' === $data['r_getEvaluation'] && 0 == $data['r_useName']) {
                $mandatoryErrors[] = $this->createMandatoryErrorMessage('statement.use.name');
            }

            if ('email' === $data['r_getEvaluation']
                && $this->permissions->hasPermission('feature_statements_feedback_check_email')) {
                if (isset($data['r_email2']) && trim((string) $data['r_email']) != trim((string) $data['r_email2'])) {
                    $mandatoryErrors[] = [
                        'type'    => 'error',
                        'message' => $this->translator->trans('error.email.repeated'),
                    ];
                }
            }
        }

        // Storing the e-mail-address if given, even there is no feedback required
        if (array_key_exists('r_email', $data)) {
            $statement['uEmail'] = $data['r_email'];
            if ('' === trim((string) $data['r_email'])) {
                $mandatoryErrors[] = $this->createMandatoryErrorMessage('email');
            }
            // ist es eine gültige E-Mail-Adresse?
            $email = $data['r_email'];
            $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
            $constraints = $validator->validate($email, [new Email()]);
            if (0 < (is_countable($constraints) ? count($constraints) : 0)) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->translator->trans('error.email.invalid'),
                ];
            }
        }

        // add misc fields
        $miscData = collect();

        if (array_key_exists('r_userGroup', $data)) {
            $miscData->put('userGroup', $data['r_userGroup']);
        }
        if (array_key_exists('r_userOrganisation', $data)) {
            $miscData->put('userOrganisation', $data['r_userOrganisation']);
        }
        if (array_key_exists('r_userPosition', $data)) {
            $miscData->put('userPosition', $data['r_userPosition']);
        }
        if (array_key_exists('r_userState', $data)) {
            $miscData->put('userState', $data['r_userState']);
        }
        if (array_key_exists('r_phone', $data)) {
            $miscData->put('userPhone', $data['r_phone']);
        }
        if (array_key_exists('r_submitter_role', $data)) {
            $miscData->put('submitterRole', $data['r_submitter_role']);
        }

        $statement['miscData'] = $miscData->toArray();

        if (0 < count($mandatoryErrors)) {
            if ($this->isDisplayNotices()) {
                $this->flashMessageHandler->setFlashMessages($mandatoryErrors);
            }

            throw new ValidatorException('Some Validation errors occurred: '.print_r($mandatoryErrors, true));
        }

        return $statement;
    }

    public function isDisplayNotices(): bool
    {
        return $this->displayNotices;
    }

    /**
     * @param bool $displayNotices
     */
    public function setDisplayNotices($displayNotices)
    {
        $this->displayNotices = $displayNotices;
    }

    /**
     * Freigeben einer Stellungnahme.
     *
     * @param string $ident
     *
     * @throws Throwable
     */
    public function releaseDraftStatement($ident)
    {
        $this->draftStatementHandler->releaseHandler([$ident]);
    }

    /**
     * Einreichen der Stellungnahme(n).
     *
     * @param string $notificationReceiverId
     * @param bool   $public
     * @param bool   $gdprConsentReceived    true if the GDPR consent was received
     *
     * @return array<int, Statement> Statement entities in their legacy array format
     *
     * @throws GdprConsentRequiredException thrown if GDPR consent is required but was not given
     * @throws Exception
     * @throws Throwable
     */
    public function submitStatement(
        $draftStatementIds,
        $notificationReceiverId = '',
        $public = false,
        bool $gdprConsentReceived = false,
    ): array {
        if (!is_array($draftStatementIds)) {
            $draftStatementIds = [$draftStatementIds];
        }

        return $this->submitStatements($draftStatementIds, $notificationReceiverId, $public, $gdprConsentReceived);
    }

    /**
     * Einreichen der Stellungnahmen.
     *
     * @param string $notificationReceiverId
     * @param bool   $public
     * @param bool   $gdprConsentReceived    true if the GDPR consent was received
     *
     * @return array<int, Statement>
     *
     * @throws GdprConsentRequiredException thrown if GDPR consent is required but was not given
     * @throws Exception
     * @throws Throwable
     */
    protected function submitStatements(
        array $draftStatementIds,
        $notificationReceiverId = '',
        $public = false,
        bool $gdprConsentReceived = false,
    ): array {
        $permissions = $this->permissions;
        // throw exception if GDPR consent is required, but was not given
        if (!$gdprConsentReceived && $permissions->hasPermission('feature_statement_gdpr_consent_submit')) {
            throw new GdprConsentRequiredException('No GDPR consent was given when saving the statement');
        }

        $submittedStatements = $this->draftStatementHandler->submitHandler(
            $draftStatementIds,
            $notificationReceiverId,
            $gdprConsentReceived
        );

        $this->eventDispatcher->dispatch(new MultipleStatementsSubmittedEvent($submittedStatements, $public));

        return $submittedStatements;
    }

    /**
     * Generiert das gewünschte PDF-Dokument aus der Datensatzliste.
     *
     * @param array|string $fragmentIds
     * @param string|null  $procedureId
     * @param string|null  $departmentId The Id of the department that the user doing the export belongs to
     * @param bool         $is_archive   true if the export should apply to the archive rather to the fragment list
     *
     * @throws HandlerException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generateFragmentPdf($fragmentIds = [], $procedureId = null, $departmentId = null, $is_archive = false): PdfFile
    {
        if (!is_null($fragmentIds) && is_string($fragmentIds)) {
            $fragmentIds = [$fragmentIds];
        }

        $esQuery = $this->getEsQueryFragment();
        $esQuery->setScope(QueryFragment::SCOPE_PLANNER);

        if ($is_archive) {
            $esQuery->addFilterMust('versions.modifiedByDepartmentId', $departmentId);
            $esQuery->setIncludeVersions(true);
            $sort = $esQuery->getAvailableSort('versionCreated');
            $sort->setDirection('desc');
        } else {
            if (!is_null($departmentId)) {
                $esQuery->addFilterMust('departmentId', $departmentId);
            }
            $sort = $esQuery->getAvailableSort('created');
            $sort->setDirection('desc');
        }

        $esQuery->setSort([$sort]);
        $vars = $this->getRequestValues();
        $statementFragmentService = $this->statementFragmentService;

        // if there are selected items, select only them:
        if (is_array($fragmentIds) && 0 < count($fragmentIds)) {
            $esQuery->addFilterMust('id', $fragmentIds);
        }

        if ($is_archive) {
            $fragments = $statementFragmentService->getStatementFragmentsDepartmentArchive($esQuery, $vars, $departmentId);
        } else {
            $fragments = $statementFragmentService->getStatementFragmentsDepartment($esQuery, $vars);
        }

        if (is_null($fragments)) {
            $fragments = [];
        }

        $filenamePrefix = $this->translator->trans('fragment');
        $title = 'fragment';

        if (1 < count($fragments)) {
            $filenamePrefix = $this->translator->trans('fragments');
            $title = 'fragments';
        }

        $procedure = is_null($procedureId) ? [] : $this->procedureHandler->getProcedure($procedureId);
        $templateVars = [];

        // replace formOption values like vote
        $formOptions = $this->getDemosplanConfig()->getFormOptions();
        /** @var array $fragment */
        foreach ($fragments as $fragment) {
            if (0 < strlen((string) $fragment['voteAdvice'])) {
                $voteAdviceLabel =
                    array_key_exists('statement_fragment_advice_values', $formOptions)
                    && array_key_exists($fragment['voteAdvice'], $formOptions['statement_fragment_advice_values'])
                        ? $formOptions['statement_fragment_advice_values'][$fragment['voteAdvice']] : '';
                $fragment['voteAdvice'] = $voteAdviceLabel;
            }
            // get Statement of Fragment to set fragment.statement.element.title and fragment.statement.paragraph.title
            $fragment['statement'] = $this->getStatement($fragment['statement']['id']);
            $templateVars['fragments'][] = $fragment;

            if (is_null($procedureId)) {
                $procedure[$fragment['id']] = $this->procedureHandler->getProcedure($fragment['procedureId']);
            }
        }
        $templateVars['titleTransKey'] = $title;

        $content = $this->twig->render(
            '@DemosPlanCore/DemosPlanStatement/fragment_list.tex.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
            ]
        );

        // Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
        $this->profilerStart('Rabbit_PDF');
        try {
            $response = $this->serviceImporter->exportPdfWithRabbitMQ(base64_encode($content));
        } catch (Exception $e) {
            $this->getLogger()->error('Communication with PDF Service failed', $e->getTrace());
            throw HandlerException::fragmentExportFailedException('pdf');
        }

        $this->profilerStop('Rabbit_PDF');

        $content = base64_decode($response);

        if (is_array($procedure)) {
            $name = $filenamePrefix.'.pdf';
        } else {
            $name = $filenamePrefix.'_'.$procedure->getName().'.pdf';
        }
        $this->getLogger()->debug('Got Response: '.DemosPlanTools::varExport($content, true));

        return new PdfFile($name, $content);
    }

    /**
     * Yields all organisations that are considered to be a "Fachplaner-Fachbehoerde"
     * An organization qualifies for being Fachbehoerde when it has at least one user
     * with the role RMOPFB or RMOPPO.
     *
     * @param bool $asObject
     *
     * @return Department[]
     *
     * @throws Exception
     */
    public function getAgencyData($asObject = true)
    {
        $users = $this->userService->getUsersOfRole(Role::PLANNING_SUPPORTING_DEPARTMENT); // Fachplaner-Fachbehoerde

        /** @var User[] $users */
        $departments = [];
        foreach ($users as $user) {
            $department = $user->getDepartment();
            if (!in_array($department, $departments) && !is_null($department)) {
                if ($asObject) {
                    $returnValue = $department;
                } else {
                    $returnValue = [
                        'orgaName'       => $department->getOrgaName(),
                        'departmentName' => $department->getName(),
                    ];
                }
                $departments[$department->getId()] = $returnValue;
            }
        }

        // sort by Organame and Department. Works because this is only
        // used for sorting and does not alter any data
        if ($asObject) {
            $departments = collect($departments)->sortBy(fn ($department) =>
                /* @var Department $department */
                $department->getOrgaName().$department->getName())->toArray();
        } else {
            $departments = collect($departments)->sortBy(fn ($department) =>
                /* @var array $department */
                $department['orgaName'].$department['departmentName'])->toArray();
        }

        return $departments;
    }

    /**
     * Speichert die Formulardaten von der Bestätigungssseite.
     *
     * @param string $statementToRelease
     * @param array  $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function updateDraftStatement($statementToRelease, $data)
    {
        $data['r_ident'] = $statementToRelease;
        $data['action'] = 'statementedit';
        $storageStatementResult = $this->draftStatementHandler->updateDraftStatement($data);

        return $storageStatementResult;
    }

    /**
     * Get the externalIds for a set of draftstatements.
     *
     * @param array $idents
     *
     * @return array $numbers
     */
    public function getDraftStatementNumbers($idents)
    {
        $numbers = [];
        foreach ($idents as $ident) {
            $statement = $this->draftStatementService->getDraftStatement($ident);
            $numbers[] = $statement['number'];
        }

        return $numbers;
    }

    /**
     * Updates the text of a Statement.
     *
     * Update Statement
     *
     * @param array $data should contain the text and the id of the statement
     *
     * @return Statement|false|null
     *
     * @throws Exception
     */
    public function updateStatementText(array $data)
    {
        $statementData = [];
        if (array_key_exists('r_text', $data)) {
            $statementData['text'] = $data['r_text'];
        }

        if (array_key_exists('r_recommendation', $data)) {
            $statementData['recommendation'] = $data['r_recommendation'];
        }

        if (array_key_exists('r_ident', $data)) {
            $statementData['ident'] = $data['r_ident'];
        }

        return $this->statementService->updateStatement($statementData);
    }

    /**
     * @param array $data
     * @param bool  $propagateTags add tags added to this fragment to the corresponding statement as well
     *
     * @return StatementFragment|null
     *
     * @throws Exception
     */
    public function createStatementFragment($data, bool $propagateTags = true)
    {
        $statementFragmentData = [];
        if (!array_key_exists('r_text', $data) || '' === $data['r_text']) {
            $this->getMessageBag()->add(
                'warning',
                'error.mandatoryfield',
                ['name' => $this->translator->trans('fragment')]
            );

            return null;
        }

        $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'statementId', '');
        $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'procedureId', '');

        if (array_key_exists('r_reviewer', $data)) {
            $statementFragmentData['departmentId'] = $data['r_reviewer'];

            if ('' != $data['r_reviewer']) {
                $statementFragmentData['status'] = 'fragment.status.assignedToFB';
            }
        }
        // add Tags and their boilerplate texts if defined
        if (array_key_exists('r_tags', $data)) {
            $statementFragmentData['tags'] = $data['r_tags'];
            $statementFragmentData['consideration'] = $this->addBoilerplatesOfTags($statementFragmentData['tags']);
        }

        $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'text');
        $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'counties');
        $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'municipalities');
        $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'priorityAreas');
        $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'modifiedByUserId');
        $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'modifiedByDepartmentId');

        if (array_key_exists('r_element', $data) && '' != $data['r_element']) {
            $statementFragmentData['element'] = $data['r_element'];
        }
        if (array_key_exists('r_paragraph', $data) && '' != $data['r_paragraph']) {
            $statementFragmentData['paragraph'] = $data['r_paragraph'];
        }

        if (array_key_exists('r_document', $data) && '-' != $data['r_document']) {
            /** @var SingleDocumentRepository $singleDocumentRepository */
            $singleDocumentRepository = $this->entityManager->getRepository(SingleDocument::class);
            /** @var SingleDocumentVersionRepository $singleDocumentVersionRepository */
            $singleDocumentVersionRepository = $this->entityManager->getRepository(SingleDocumentVersion::class);
            $document = $singleDocumentRepository->findOneBy(['id' => $data['r_document']]);
            if ($document instanceof SingleDocument) {
                $documentVersion = $singleDocumentVersionRepository->createVersion($document);
                $statementFragmentData['document'] = $documentVersion;
            }
            $statementFragmentData['paragraph'] = '';
        }

        $result = $this->statementFragmentService->createStatementFragment($statementFragmentData);

        if ($result instanceof StatementFragment) {
            // add all tags from the new fragment to the related statement if propagation is enabled
            $tagsToAdd = collect($propagateTags ? $result->getTags() : []);
            $relatedStatementUpdated = $this->addAdditionalAreaInformationToStatement($result, $tagsToAdd);
        }

        if (array_key_exists('r_notify', $data) && !is_null($result) && false !== $result) {
            $this->notifyReviewerOfFragment($result, false);
        }

        return $result;
    }

    /**
     * Calculate difference of new incoming tags and already attached tags of a fragment.
     *
     * @param string[] $tagIds
     *
     * @return Collection
     */
    public function getNewAttachedTags(StatementFragment $fragmentToUpdate, $tagIds)
    {
        $fragmentTagIds = collect($fragmentToUpdate->getTagIds());
        $tagIds = collect($tagIds);

        return $tagIds->diff($fragmentTagIds);
    }

    public function addBoilerplatesOfTags($tagIds, $considerationText = ''): string
    {
        foreach ($tagIds as $tagId) {
            $tag = null;
            try {
                $tag = $this->tagService->getTag($tagId);
                if ($tag instanceof Tag && $tag->hasBoilerplate()) {
                    $considerationText .= nl2br($tag->getBoilerplate()->getText());
                }
            } catch (Exception) {
                $this->getLogger()->warning('Could not resolve Tag with ID: '.$tagId);
                continue;
            }
        }

        return $considerationText;
    }

    /**
     * Send a notification mail to the countyOrganization.
     *
     * @param array  $idents
     * @param string $countyId
     *
     * @throws Exception
     */
    public function getCountyNotificationData($idents, $countyId, string $procedureId): CountyNotificationData
    {
        // Retrieve organisation
        if (!is_array($idents) || count($idents) < 1) {
            throw new Exception('Cannot send notification email for empty statement array');
        }
        if (!is_string($countyId)) {
            throw new Exception('County OrganisationId must be a string');
        }

        try {
            $this->countyService->getCounty($countyId);
        } catch (Exception $e) {
            $this->getLogger()->error('Could not send Email to county, county not found: ', [$e]);
            throw $e;
        }

        // Generate PDFs
        $statements = [];
        foreach ($idents as $ident) {
            $statements[] = $this->draftStatementService->getDraftStatement($ident);
        }

        $orga = $this->currentUser->getUser()->getOrganisationNameLegal();
        $procedure = $this->currentProcedureService->getProcedureArray();
        $pdfResult = $this->draftStatementService->generatePdf($statements, 'list_final_group', $procedureId);

        $files = [];
        foreach ($statements as $statement) {
            if (array_key_exists('files', $statement) && 0 < (is_countable($statement['files']) ? count($statement['files']) : 0)) {
                $files = array_merge($files, $statement['files']);
            }
        }

        return new CountyNotificationData($orga, $procedure, $files, $pdfResult);
    }

    protected function getStatementService(): StatementService
    {
        return $this->statementService;
    }

    /**
     * @deprecated use StatementHandler or StatementService directly instead
     */
    public function getPublicServiceStatement(): StatementService
    {
        return $this->statementService;
    }

    /**
     * @param StatementService $statementService
     */
    public function setStatementService($statementService)
    {
        $this->statementService = $statementService;
    }

    protected function getEntityContentChangeService(): EntityContentChangeService
    {
        return $this->entityContentChangeService;
    }

    /**
     * @param EntityContentChangeService $entityContentChangeService
     */
    public function setEntityContentChangeService($entityContentChangeService)
    {
        $this->entityContentChangeService = $entityContentChangeService;
    }

    protected function getPriorityAreaService(): PriorityAreaService
    {
        return $this->priorityAreaService;
    }

    protected function getCountyService(): CountyService
    {
        return $this->countyService;
    }

    /**
     * Gib ein DraftStatement aus.
     *
     * @param string $draftStatementId
     *
     * @return array|null
     */
    public function getDraftStatement($draftStatementId)
    {
        $draftStatementService = $this->draftStatementService;

        return $draftStatementService->getDraftStatement($draftStatementId);
    }

    /**
     * Handles actions that concern editing tags and their
     * relations to boilerplate texts.
     *
     * @param string $tagId
     * @param array  $data
     *
     * @throws Exception
     */
    public function handleTagBoilerplate($tagId, $data, string $procedureId)
    {
        $tag = $this->tagService->getTag($tagId);
        if (null === $tag) {
            throw TagNotFoundException::createFromId($tagId);
        }
        if ($procedureId !== $tag->getTopic()->getProcedure()->getId()) {
            throw new TagNotFoundException("No tag with the ID '$tagId' found in the procedure with the ID '$procedureId'");
        }
        switch ($data['action']) {
            case 'none':
                $boilerplate = $tag->getBoilerplate();
                $this->tagService->detachBoilerplateFromTag($tag, $boilerplate);
                break;
            case 'new':
                $boilerplate = $this->procedureService->addBoilerplate($procedureId, [
                    'title'     => $data['boilerplateTitle'],
                    'text'      => $data['boilerplateText'],
                    'procedure' => $procedureId,
                ]);
                if (null !== $tag->getBoilerplate()) {
                    $this->tagService->detachBoilerplateFromTag($tag, $tag->getBoilerplate());
                }
                $this->tagService->attachBoilerplateToTag($tag, $boilerplate);
                break;
            case 'existing':
                if (null !== $data['boilerplateId']) {
                    $boilerplate = $this->procedureService->getBoilerplate($data['boilerplateId']);
                    $this->tagService->attachBoilerplateToTag($tag, $boilerplate);
                }
                break;
            default:
                break;
        }
        if (null != $data['tagTitle'] && $data['tagTitle'] != $tag->getTitle()) {
            $this->tagService->renameTag($tag->getId(), $data['tagTitle']);
        }
    }

    /**
     * Creates a Topic.
     *
     * @param string $name
     *
     * @throws DuplicatedTagTopicTitleException
     * @throws Exception
     */
    public function createTopic($name, string $procedureId): TagTopic
    {
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);

        return $this->tagService->createTagTopic($name, $procedure);
    }

    /**
     * Create a Tag.
     *
     * @param string $topicId     The topic these tags are being added to
     * @param string $tagstring   comma-separated tags
     * @param string $procedureId ID of the procedure used to check the permissions of the user.
     *                            Must be equal to the procedure ID of the topic corresponding to
     *                            the given topicId.
     *
     * @throws DuplicatedTagTitleException
     * @throws BadRequestException|TagTopicNotFoundException
     */
    public function createTagFromTopicId(string $topicId, string $tagstring, string $procedureId): Tag
    {
        $topic = $this->tagService->getTopic($topicId);
        if (null === $topic) {
            throw TagTopicNotFoundException::createFromTagTopicId($topicId);
        }

        $topicProcedureId = $topic->getProcedure()->getId();
        if ($topicProcedureId !== $procedureId) {
            throw new BadRequestException("Request tried to create a tag with a topic that is in a different procedure ({$topicProcedureId}) than the one currently accessed ({$procedureId})");
        }

        return $this->tagService->createTag($tagstring, $topic);
    }

    /**
     * Renames a topic.
     *
     * @param string $id
     * @param string $name
     *
     * @return TagTopic|false
     */
    public function renameTopic($id, $name)
    {
        return $this->tagService->renameTopic($id, $name);
    }

    /**
     * Renames a tag.
     *
     * @param string $id
     * @param string $name
     *
     * @return Tag|false
     */
    public function renameTag($id, $name)
    {
        return $this->tagService->renameTag($id, $name);
    }

    /**
     * Deletes a topic.
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function deleteTopic($id)
    {
        $topic = $this->tagService->getTopic($id);

        return $this->tagService->deleteTopic($topic);
    }

    /**
     * Deletes a Tag.
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function deleteTag($id)
    {
        $tag = $this->tagService->getTag($id);

        return $this->tagService->deleteTag($tag);
    }

    /**
     * Returns a Tag.
     *
     * @param string|null $id
     *
     * @return Tag|null
     */
    public function getTag($id)
    {
        return $this->tagService->getTag($id);
    }

    /**
     * @throws TagNotFoundException
     */
    public function getNonNullTag(string $id): Tag
    {
        $tag = $this->getTag($id);
        if (null === $tag) {
            throw TagNotFoundException::createFromId($id);
        }

        return $tag;
    }

    /**
     * Moves a Tag to another Topic.
     *
     * @param string $topicId
     * @param string $tagId
     *
     * @return bool
     */
    public function moveTagToTopic($topicId, $tagId)
    {
        $tag = $this->tagService->getTag($tagId);
        $newTopic = $this->tagService->getTopic($topicId);

        return $this->tagService->moveTagToTopic($tag, $newTopic);
    }

    /**
     * Returns all tags that belong to the given procedure.
     *
     * @param string $procedureId
     *
     * @return TagTopic[]
     *
     * @throws Exception
     */
    public function getTopicsByProcedure($procedureId)
    {
        return $this->procedureService->getTopics($procedureId);
    }

    /**
     * Returns the count of statements in different states (draft, released to orga,
     * releases by the orga e.t.c).
     *
     * @param string $procedureId
     * @param string $role
     * @param User   $user
     *
     * @throws Exception
     */
    public function getStatementCounts($procedureId, $role, $user): array
    {
        $this->profilerStart('StatementCounts');

        $ownScope = Role::CITIZEN === $role ? 'ownCitizen' : 'own';

        $userFilter = new StatementListUserFilter();
        $userFilter->setReleased(false)->setSubmitted(false);
        $drafts = $this->draftStatementHandler->statementListHandler(
            $procedureId,
            'own',
            $userFilter,
            null,
            null,
            $user,
            null,
            false);

        $userFilter = new StatementListUserFilter();
        $userFilter->setReleased(true)->setSubmitted(false);
        $releasedDrafts = $this->draftStatementHandler->statementListHandler(
            $procedureId,
            $ownScope,
            $userFilter,
            null,
            null,
            $user,
            null,
            false);

        $userFilter = new StatementListUserFilter();
        $userFilter->setReleased(true)->setSubmitted(false);
        $groupReleased = $this->draftStatementHandler->statementListHandler(
            $procedureId,
            'group',
            $userFilter,
            null,
            null,
            $user,
            null,
            false);

        $userFilter = new StatementListUserFilter();
        $userFilter->setReleased(true)->setSubmitted(true);
        $ownSubmitted = $this->draftStatementHandler->statementListHandler(
            $procedureId,
            $ownScope,
            $userFilter,
            null,
            null,
            $user,
            null,
            false);

        $userFilter = new StatementListUserFilter();
        $userFilter->setReleased(true)->setSubmitted(true);
        $groupSubmitted = $this->draftStatementHandler->statementListHandler(
            $procedureId,
            'group',
            $userFilter,
            null,
            null,
            $user,
            null,
            false);

        if (Role::CITIZEN === $role) {
            $public = $this->statementService->getStatementsByProcedureId(
                $procedureId,
                ['publicVerified' => Statement::PUBLICATION_APPROVED],
                null,
                null,
                1_000_000
            );
            $publicTotal = $public->getTotal();
        } else {
            $statementListHandlerResult = $this->draftStatementHandler->statementOtherCompaniesListHandler(
                $procedureId,
                null,
                new StatementListUserFilter(),
                null
            );
            $publicTotal = count($statementListHandlerResult->getStatementList());
        }

        $this->profilerStop('StatementCounts');

        return [
            'drafts'         => count($drafts->getStatementList()),
            'ownDrafts'      => count($drafts->getStatementList()),
            'ownReleased'    => count($releasedDrafts->getStatementList()),
            'groupReleased'  => count($groupReleased->getStatementList()),
            'ownSubmitted'   => count($ownSubmitted->getStatementList()),
            'groupSubmitted' => count($groupSubmitted->getStatementList()),
            'public'         => $publicTotal,
            'voted'          => count($this->determineVotedStatements($procedureId)),
        ];
    }

    /**
     * @param DraftStatementHandler $storage
     */
    public function setDraftStatementHandler($storage)
    {
        $this->draftStatementHandler = $storage;
    }

    protected function getOrgaService(): OrgaService
    {
        return $this->orgaService;
    }

    /**
     * Definition der incoming Data.
     */
    protected function incomingDataDefinition()
    {
        return [
            'statementpublicnew' => [
                'action',
                'url',
                'r_element_id',
                'r_element_title',
                'r_loadtime',
                'r_text',
                'r_firstname',
                'r_lastname',
                'r_email',
                'r_email2',
                'r_uploaddocument',
                'r_privacy',
                'r_makePublic',
                'r_street',
                'r_houseNumber',
                'r_postalCode',
                'r_paragraph_id',
                'r_paragraph_title',
                'r_location',
                'r_useName',
                'r_city',
                'r_represents',
                'r_getEvaluation',
                'r_notLocated',
                'r_getFeedback',
                'r_notLocated',
                'r_location',
                'r_county',
                'r_location_priority_area_key',
                'r_location_priority_area_type',
                'r_location_point',
                'r_location_geometry',
                'r_userGroup',
                'r_userOrganisation',
                'r_userPosition',
                'r_userState',
                'r_gdpr_consent',
                'r_document_id',
                'r_document_title',
                'r_phone',
                'r_submitter_role',
            ],
        ];
    }

    /**
     * Move a fragment to a reviewer and handle the resulting side effects.
     *
     * If no change of the reviewer was requested or if no permissions are given to change the
     * reviewer of a fragment then this method does nothing.
     *
     * Moving a fragment to a reviewer is only allowed if no vote advice (set by the planning
     * agency) was set. This is because fragments can only be moved to reviewers once and never
     * again. The vote advice can be used to check this as it is set after the fragment was moved
     * back from the planning agency to the planner.
     *
     * If the vote advice was set or is to be set this method will do nothing and issue a warning
     * instead visible to the user.
     *
     * The field used to store the reviewer the fragment is moved to is 'departmentId'.
     *
     * When a fragment is moved to a reviewer the 'consideration' (visible to and written by
     * planners only) is copied into the field 'considerationAdvice' (visible and editable by
     * planning agencies only).
     *
     * @param array             $data                  includes the request data
     * @param StatementFragment $fragmentToUpdate      the original data of the fragment
     * @param array             $statementFragmentData the data that will be used to update the
     *                                                 fragment
     *
     * @throws MessageBagException
     */
    protected function handleSetReviewerAndSideEffects(array $data, StatementFragment $fragmentToUpdate, array $statementFragmentData): array
    {
        if ((!array_key_exists('r_vote_advice', $data) || '' === $data['r_vote_advice'])
            && null === $fragmentToUpdate->getVoteAdvice()) {
            $statementFragmentData['departmentId'] = $data['r_reviewer'];
            $statementFragmentData['considerationAdvice'] = $statementFragmentData['consideration'];
        } else {
            $this->getMessageBag()->add('warning', 'fragment.assign.reviewer.voteAdvice.pending');
        }

        return $statementFragmentData;
    }

    protected function handleUnsetReviewerAndSideEffects(array $statementFragmentData): array
    {
        // T5510 it should be possible for planner to redraw reviewer even if voteAdvice is set
        // and statement not reassigned to planner yet
        $statementFragmentData['departmentId'] = null;
        // reset voteAdvice and considerationAdvice, so that a newly assigned orga
        // could not see the old values
        $statementFragmentData['voteAdvice'] = null;
        $statementFragmentData['considerationAdvice'] = null;

        return $statementFragmentData;
    }

    /**
     * @return array The adjusted $statementFragmentData input
     *
     * @throws MessageBagException
     */
    protected function handleReviewerChangeAndSideEffects(array $data, StatementFragment $fragmentToUpdate, array $statementFragmentData): array
    {
        // If voteAdvice already set, its not longer possible to edit the reviewer
        // T5920 deny set of reviewer and voteAdvice at the same time, but not if voteAdvice set to "" (empty)
        if (array_key_exists('r_reviewer', $data)
            && $this->permissions->hasPermission('feature_statements_fragment_add_reviewer')) {
            if ('' !== $data['r_reviewer']) { // department is to be set
                $statementFragmentData = $this->handleSetReviewerAndSideEffects($data, $fragmentToUpdate, $statementFragmentData);
            } else { // department is to be unset
                $statementFragmentData = $this->handleUnsetReviewerAndSideEffects($statementFragmentData);
            }
        }

        return $statementFragmentData;
    }

    /**
     * @throws MessageBagException
     */
    protected function handleMovingFragmentBackToPlanner(array $data, StatementFragment $fragmentToUpdate, array $statementFragmentData): array
    {
        $statementFragmentData['departmentId'] = null;
        // reset assignment when reviewer sends fragment back
        $statementFragmentData['assignee'] = null;
        $this->getMessageBag()->add(
            'confirm',
            'confirm.fragment.update.complete',
            [
                'id' => $fragmentToUpdate->getDisplayId(),
            ]
        );

        // update field consideration as planners only use this field
        $statementFragmentData['consideration'] = $statementFragmentData['considerationAdvice'];
        // if VoteAdvice is set
        if (array_key_exists('r_departmentName', $data) && 0 < strlen((string) $data['r_departmentName'])) {
            $statementFragmentData['archivedDepartmentName'] = $data['r_departmentName'];
        }
        if (array_key_exists('r_orgaName', $data) && 0 < strlen((string) $data['r_orgaName'])) {
            $statementFragmentData['archivedOrgaName'] = $data['r_orgaName'];
        }

        return $statementFragmentData;
    }

    /**
     * For an overview of the things this method does see below.
     *
     * - copy 'considerationAdvice' depending on permissions
     * - copy 'consideration' depending on permissions
     * - copy 'counties' or __use original data__ depending on permissions
     * - copy 'municipalities' __or use original data__ depending on permissions
     * - copy 'priorityAreas' __or use original data__ depending on permissions
     * - handle reviewer change and resulting side effects
     * - handle vote change and store user who changed it
     * - copy vote advice
     * - handle moving the fragment from the planning agency back to the planner which will clear
     * the departmentId and the assignee from the fragment, copies the 'considerationAdvice' text
     * back into the 'consideration' text field and (may) archive the department name and orga name.
     * - handle tags, meaning that the boilerplate texts of new tags are added to the consideration
     * text
     * - update the status of the fragment
     * - handle changing elements/elementIds
     * - handle changing paragraphs including versioning
     * - handle changing documents including versioning which somehow affects the paragraphs
     * - determine new state/status of fragment
     *
     * @param array $data
     * @param bool  $isReviewer
     *
     * @throws AccessDeniedException if permission 'feature_statements_fragment_edit' is not
     *                               present
     * @throws Exception
     */
    protected function createStatementFragmentArrayFromPostData($data, StatementFragment $fragmentToUpdate, $isReviewer = false): array
    {
        $statementFragmentData = [];
        // reviewers with multiple roles may not change anything, even if the other role
        // is allowed to change this field
        $mayChangeMetaData = !(array_key_exists('mayChangeMetaData', $data) && false === $data['mayChangeMetaData']);

        if (!$this->permissions->hasPermission('feature_statements_fragment_edit')) {
            throw new AccessDeniedException('warning.access.denied');
        }

        if ($this->permissions->hasPermission('feature_statements_fragment_consideration_advice')) {
            $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'considerationAdvice');
        }

        if ($this->permissions->hasPermission('feature_statements_fragment_consideration')) {
            $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'consideration');
        }

        if ($mayChangeMetaData && $this->permissions->hasPermission('field_statement_county')) {
            $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'counties');
        } else {
            // if no permission, reset data
            $statementFragmentData['counties'] = $fragmentToUpdate->getCountyIds();
        }

        if ($mayChangeMetaData && $this->permissions->hasPermission('field_statement_municipality')) {
            $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'municipalities');
        } else {
            // if no permission, reset data
            $statementFragmentData['municipalities'] = $fragmentToUpdate->getMunicipalityIds();
        }

        if ($mayChangeMetaData && $this->permissions->hasPermission('field_statement_priority_area')) {
            $statementFragmentData = $this->arrayHelper->addToArrayIfKeyExists($statementFragmentData, $data, 'priorityAreas');
        } else {
            // if no permission, reset data
            $statementFragmentData['priorityAreas'] = $fragmentToUpdate->getPriorityAreaIds();
        }

        $statementFragmentData = $this->handleReviewerChangeAndSideEffects($data, $fragmentToUpdate, $statementFragmentData);

        if (array_key_exists('r_vote', $data)
            && $this->permissions->hasPermission('feature_statements_fragment_vote')) {
            $statementFragmentData['vote'] = $data['r_vote'];
            if ('' === $data['r_vote']) {
                $statementFragmentData['vote'] = null;
            }
            if (array_key_exists('r_currentUserName', $data)) {
                $statementFragmentData['archivedVoteUserName'] = $data['r_currentUserName'];
            }
        }

        if (array_key_exists('r_vote_advice', $data)) {
            $statementFragmentData['voteAdvice'] = $data['r_vote_advice'];
        }

        // Bearbeitung des Datensatzes abschliessen:
        // r_notify is an action that the user invokes. the field is not needed
        // for actual data of the statementFragment object.
        // we need to unassign the organisation when it is trying to push the
        // fragment back to the planner after posting an advice
        // this is the case when r_notify is set and $isReviewer is true
        if (array_key_exists('r_notify', $data) && $isReviewer) {
            $statementFragmentData = $this->handleMovingFragmentBackToPlanner($data, $fragmentToUpdate, $statementFragmentData);
        }

        if ($this->permissions->hasPermission('feature_statements_fragment_add')) {
            if (array_key_exists('r_tags', $data)) {
                $statementFragmentData['tags'] = $data['r_tags'];
                // get diff of tags, which already used by the fragment
                $newTags = $this->getNewAttachedTags($fragmentToUpdate, $data['r_tags']);
                if (array_key_exists('consideration', $statementFragmentData)) {
                    $statementFragmentData['consideration'] = $this->addBoilerplatesOfTags($newTags, $statementFragmentData['consideration']);
                }
            }
            if (!$mayChangeMetaData) {
                $statementFragmentData['tags'] = $fragmentToUpdate->getTagIds();
            }
        } else {
            // if no permission, reset data to avoid deletion of all tags
            $statementFragmentData['tags'] = $fragmentToUpdate->getTagIds();
        }

        // thought it has prefix 'r_' but i don't want to break anything.
        if ($this->permissions->hasPermission('field_fragment_status')
            && array_key_exists('status', $data)) {
            $statementFragmentData['status'] = $data['status'];
        }

        // thats why...
        if ($this->permissions->hasPermission('field_fragment_status')
            && array_key_exists('r_status', $data) && '' != $data['r_status']) {
            $statementFragmentData['status'] = $data['r_status'];
        }

        if (array_key_exists('r_element', $data)) {
            if ($data['r_element'] instanceof Elements) {
                $statementFragmentData['element'] = $data['r_element'];
            } else {
                $statementFragmentData['elementId'] = $data['r_element'];
            }
        }

        if (array_key_exists('r_paragraph', $data)) {
            // If another paragraph is selected we create a new version later.
            // If it is the same we don't assign it
            if ($data['r_paragraph'] != $fragmentToUpdate->getParagraphParentId()) {
                if ($data['r_paragraph'] instanceof Paragraph) {
                    $statementFragmentData['paragraph'] = $data['r_paragraph'];
                } else {
                    $statementFragmentData['paragraphId'] = $data['r_paragraph'];
                }
            }
        }

        if ($this->permissions->hasPermission('feature_single_document_fragment')
            && array_key_exists('r_document', $data)) {
            // Check if the incoming document is already set. Version stuff here
            if ($data['r_document'] != $fragmentToUpdate->getDocumentParentId()) {
                if ('' == $data['r_document']) {
                    $statementFragmentData['document'] = null;
                } else {
                    $singleDocumentRepository = $this->entityManager->getRepository(SingleDocument::class);
                    /** @var SingleDocumentVersionRepository $singleDocumentVersionRepository */
                    $singleDocumentVersionRepository = $this->entityManager->getRepository(SingleDocumentVersion::class);
                    $document = $singleDocumentRepository->findOneBy(['id' => $data['r_document']]);
                    $documentVersion = $singleDocumentVersionRepository->createVersion($document);
                    $statementFragmentData['document'] = $documentVersion;
                }
                $statementFragmentData['paragraph'] = '';
                $statementFragmentData['paragraphId'] = '';
            }
        }

        $unchangedStatus = !array_key_exists('r_status', $data)
            || $data['r_status'] === $fragmentToUpdate->getStatus();
        $changedDepartment = array_key_exists('r_reviewer', $data)
            && '' !== $data['r_reviewer']
            && $data['r_reviewer'] !== $fragmentToUpdate->getDepartmentId();
        if ($unchangedStatus || $changedDepartment) {
            $statementFragmentData = $this->determineStateOfFragment(
                $fragmentToUpdate,
                $statementFragmentData
            );
        }

        return $statementFragmentData;
    }

    /**
     * Import a CSV-file with tags in it and create the according tag- and topic-
     * entities associated to the given procedure.
     *
     * @param resource $fileResource
     *
     * @throws Exception
     */
    public function importTags(string $procedureId, $fileResource): void
    {
        $reader = Reader::createFromStream($fileResource);
        $reader->setEscape('');
        $reader->setDelimiter(';');
        $reader->setEnclosure('"');
        $records = $reader->getRecords();
        $records->rewind();

        $columnTitles = [];
        if ($records->valid()) {
            $columnTitles = $records->current();
            $records->next();
        }

        $event = $this->eventDispatcher->dispatch(
            new ExcelImporterHandleImportedTagsRecordsEvent($records, $columnTitles),
            ExcelImporterHandleImportedTagsRecordsEventInterface::class
        );

        $newTags = $event->getTags();

        if (empty($newTags)) {
            while ($records->valid()) {
                $dataset = $records->current();
                // Do not use line if all fields are empty
                if (array_reduce($dataset, static fn ($carry, $item) => $carry && '' === $item, true)) {
                    continue;
                }
                $newTagData = [
                    'topic'          => $dataset[0] ?? '',
                    'tag'            => $dataset[1] ?? '',
                    'useBoilerplate' => isset($dataset[2]) && 'ja' === $dataset[2],
                    'boilerplate'    => $dataset[3] ?? '',
                ];
                $newTags[] = $newTagData;
                $records->next();
            }
        }

        // Create objects
        $lastTopic = null;

        // Will be filled with the tag topics needed to create the imported tags
        // with the tag topic title as key and the object as value.
        $topics = [];
        $persistedTag = [];

        foreach ($newTags as $tagData) {
            $currentTopicTitle = $tagData['topic'];
            $currentTagTitle = $tagData['tag'];
            // Create topic if not already present
            if ($currentTopicTitle !== $lastTopic) {
                try {
                    Assert::stringNotEmpty($currentTopicTitle);
                    Assert::stringNotEmpty($currentTagTitle);
                    $topics[$currentTopicTitle] = $this->createTopic($currentTopicTitle, $procedureId);
                } catch (InvalidArgumentException) {
                    $this->getMessageBag()->add('warning', 'tag.or.topic.name.empty.error');
                    continue;
                } catch (DuplicatedTagTopicTitleException) {
                    $alreadyCreatedTopic = $this->tagTopicRepository->findOneByTitle($currentTopicTitle, $procedureId);
                    Assert::notNull($alreadyCreatedTopic);
                    $topics[$currentTopicTitle] = $alreadyCreatedTopic;
                    // better do not try to heal import as it may have unforeseen
                    // consequences?
                    $this->getMessageBag()->add('warning', 'topic.create.duplicated.title');
                }
                $lastTopic = $currentTopicTitle;
            }

            // Create the tag
            $tag = $this->tagService->createTag(
                $currentTagTitle,
                $topics[$currentTopicTitle]
            );

            $persistedTag[] = $tag;

            // Create and attach a boilerplate object if required
            if ($tagData['useBoilerplate']) {
                $boilerplateData = [
                    'title' => $currentTagTitle,
                    'text'  => $tagData['boilerplate'],
                ];
                $boilerplate = $this->procedureService->addBoilerplate(
                    $procedureId,
                    $boilerplateData
                );
                $this->tagService->attachBoilerplateToTag($tag, $boilerplate);
            }
        }

        $this->eventDispatcher->dispatch(
            new ExcelImporterPrePersistTagsEvent(tags: $persistedTag),
            ExcelImporterPrePersistTagsEventInterface::class
        );
    }

    /**
     * Add Municipalities, Counties, tags and/or PriorityArea if not already exists on
     * the related Statement of the given StatementFragment.
     *
     * @param Collection<Tag> $tagsToAdd
     *
     * @return bool Has related statement been updated?
     */
    protected function addAdditionalAreaInformationToStatement(StatementFragment $fragment, Collection $tagsToAdd): bool
    {
        $relatedStatement = $fragment->getStatement();

        $addedPriorityAreas = collect($fragment->getPriorityAreas())->filter(
            fn (PriorityArea $priorityArea) => $relatedStatement->addPriorityArea($priorityArea)
        )->map(
            fn (PriorityArea $priorityArea) => $priorityArea->getName()
        );

        $this->consolidateAdditionalAreaInformationMessages(
            $addedPriorityAreas,
            $relatedStatement,
            'info.statement.priorityArea.added',
            'info.statement.priorityAreas.added'
        );

        $addedMunicipalities = collect($fragment->getMunicipalities())->filter(
            fn (Municipality $municipality) => $relatedStatement->addMunicipality($municipality)
        )->map(
            fn (Municipality $municipality) => $municipality->getName()
        );

        $this->consolidateAdditionalAreaInformationMessages(
            $addedMunicipalities,
            $relatedStatement,
            'info.statement.municipality.added',
            'info.statement.municipalities.added'
        );

        $addedCounties = collect($fragment->getCounties())->filter(
            fn (County $county) => $relatedStatement->addCounty($county)
        )->map(
            fn (County $county) => $county->getName()
        );

        $this->consolidateAdditionalAreaInformationMessages(
            $addedCounties,
            $relatedStatement,
            'info.statement.county.added',
            'info.statement.counties.added'
        );

        $addedTags = $tagsToAdd->filter(
            static fn (Tag $tag) => $relatedStatement->addTag($tag)
        )->map(
            static fn (Tag $tag) => $tag->getName()
        );
        $this->consolidateAdditionalAreaInformationMessages(
            $addedTags,
            $relatedStatement,
            'info.statement.tag.added',
            'info.statement.tags.added'
        );

        // only update Statement if needed
        if (
            0 < $addedCounties->count()
            || 0 < $addedMunicipalities->count()
            || 0 < $addedPriorityAreas->count()
            || 0 < $addedTags->count()
        ) {
            $this->updateStatementObject($relatedStatement);

            // statement was updated
            return true;
        }

        return false;
    }

    /**
     * Consolidate multiple updated area information items into one flash message.
     *
     * @param Collection $messages         Area information item string values
     * @param Statement  $relatedStatement Statement to which the messages are related
     * @param string     $singularKey      Translation key for a single area information item
     * @param string     $pluralKey        Translation key for mutliple area information items
     */
    public function consolidateAdditionalAreaInformationMessages(Collection $messages, Statement $relatedStatement, $singularKey, $pluralKey)
    {
        $externalStatementId = ($relatedStatement->isCopy() ? $this->translator->trans('copyof').' ' : '').$relatedStatement->getExternId();
        if (1 === $messages->count()) {
            $this->getMessageBag()->add(
                'info',
                $singularKey,
                [
                    'name'                => $messages->first(),
                    'externalStatementId' => $externalStatementId,
                ]
            );
        } elseif (1 < $messages->count()) {
            $this->getMessageBag()->add(
                'info',
                $pluralKey,
                [
                    'names'               => $messages->implode(', '),
                    'externalStatementId' => $externalStatementId,
                ]
            );
        }
    }

    /**
     * @param string[] $incomingFragmentData
     *
     * @return array string[] - format: ['priorityAreaIds'[], 'countyIds'[], 'municipalityIds'[]]
     */
    public function calculateParentPropertyIdsToDelete($incomingFragmentData, StatementFragment $fragment): array
    {
        /* The following code uses array_diff. From the PHP documentation:
         *
         * Two elements are considered equal if and only if (string) $elem1 === (string) $elem2.
         * In other words: when the string representation is the same.
         */
        $priorityAreaIdsToDelete =
            collect($fragment->getPriorityAreaIds())
                ->diff(
                    $incomingFragmentData['priorityAreas'] ?? []
                )->toArray();

        $municipalityIdsToDelete =
            collect($fragment->getMunicipalityIds())
                ->diff(
                    $incomingFragmentData['municipalities'] ?? []
                )->toArray();

        $countyIdsToDelete =
            collect($fragment->getCountyIds())
                ->diff(
                    $incomingFragmentData['counties'] ?? []
                )->toArray();

        $tagIdsToDelete =
            collect($fragment->getTagIds())
                ->diff(
                    $incomingFragmentData['tags'] ?? []
                )->toArray();

        return [
            'priorityAreaIds' => $priorityAreaIdsToDelete,
            'countyIds'       => $countyIdsToDelete,
            'municipalityIds' => $municipalityIdsToDelete,
            'tagIds'          => $tagIdsToDelete,
        ];
    }

    /**
     * Get all information which is not related to any other fragment of the statement.
     *
     * @param string[][]          $areaInformationIdsToCheck
     *                                                       'priorityAreas' => $isolatedPriorityAreaIds,
     *                                                       'municipalities' => $isolatedMunicipalityIds,
     *                                                       'counties' => $isolatedCountyIds,
     *                                                       'tags' => $isolatedTagIds
     * @param StatementFragment[] $fragments
     *
     * @return Collection
     */
    public function getIsolatedInformationIds($areaInformationIdsToCheck, $fragments)
    {
        if (!array_key_exists('countyIds', $areaInformationIdsToCheck)) {
            $areaInformationIdsToCheck['countyIds'] = [];
        }

        if (!array_key_exists('priorityAreaIds', $areaInformationIdsToCheck)) {
            $areaInformationIdsToCheck['priorityAreaIds'] = [];
        }

        if (!array_key_exists('municipalityIds', $areaInformationIdsToCheck)) {
            $areaInformationIdsToCheck['municipalityIds'] = [];
        }
        if (!array_key_exists('tagIds', $areaInformationIdsToCheck)) {
            $areaInformationIdsToCheck['tagIds'] = [];
        }

        // remove every element which is not "isolated":
        // if(isPriorityAreaInFragments()) {do nothing}
        $isolatedPriorityAreaIds = collect($areaInformationIdsToCheck['priorityAreaIds'])
            ->filter(fn ($id) => !$this->isPriorityAreaInFragments($id, $fragments));

        // remove every element which is not "isolated":
        $isolatedMunicipalityIds = collect($areaInformationIdsToCheck['municipalityIds'])
            ->filter(fn ($id) => !$this->isMunicipalityInFragments($id, $fragments));

        // remove every element which is not "isolated":
        $isolatedCountyIds = collect($areaInformationIdsToCheck['countyIds'])
            ->filter(fn ($id) => !$this->isCountyInFragments($id, $fragments));

        // remove every element which is not "isolated":
        $isolatedTagIds = collect($areaInformationIdsToCheck['tagIds'])
            ->filter(fn ($id) => !$this->isTagInFragments($id, $fragments));

        return collect([
            'priorityAreas'  => $isolatedPriorityAreaIds,
            'municipalities' => $isolatedMunicipalityIds,
            'counties'       => $isolatedCountyIds,
            'tags'           => $isolatedTagIds,
        ]);
    }

    /**
     * Determines if the given countyId is in one of the given fragments.
     *
     * @param string              $countyId
     * @param StatementFragment[] $fragments
     *
     * @return bool
     */
    public function isCountyInFragments($countyId, array $fragments)
    {
        foreach ($fragments as $relatedFragment) {
            if (collect($relatedFragment->getCountyIds())->contains($countyId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if the given tagId is in one of the given fragments.
     *
     * @param string              $tagId
     * @param StatementFragment[] $fragments
     *
     * @return bool
     */
    public function isTagInFragments($tagId, array $fragments)
    {
        foreach ($fragments as $relatedFragment) {
            if (collect($relatedFragment->getTagIds())->contains($tagId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if the given priorityAreaId is in one of the given fragments.
     *
     * @param string              $priorityAreaId
     * @param StatementFragment[] $fragments
     *
     * @return bool
     */
    public function isPriorityAreaInFragments($priorityAreaId, array $fragments)
    {
        foreach ($fragments as $relatedFragment) {
            if (collect($relatedFragment->getPriorityAreaIds())->contains($priorityAreaId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if the given municipalityId is in one of the given fragments.
     *
     * @param string              $municipalityId
     * @param StatementFragment[] $fragments
     *
     * @return bool
     */
    public function isMunicipalityInFragments($municipalityId, array $fragments)
    {
        foreach ($fragments as $relatedFragment) {
            if (collect($relatedFragment->getMunicipalityIds())->contains($municipalityId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, Statement>
     *
     * @throws UserNotFoundException
     */
    public function determineVotedStatements(string $procedure): array
    {
        $currentUser = $this->currentUser->getUser();
        $userOrga = $this->orgaService->getOrga($currentUser->getOrganisationId());
        // do not display every votes from other citizens if user is citizen
        if ($userOrga instanceof Orga && User::ANONYMOUS_USER_ORGA_ID !== $userOrga->getId()) {
            $allOrgaUsers = $userOrga->getUsers();
            $userIds = [];

            /** @var User $orgaUser */
            foreach ($allOrgaUsers as $orgaUser) {
                $userIds[] = $orgaUser->getId();
            }
            // get unique statements voted by anybody from my orga
            $votedStatements = $this->getStatementsByUserVotesUnique($userIds);
        } else {
            // else might be used in this case, as operation is quite expensive
            // could not use scope 'group' because user may filter own Statements
            $votedStatements = $this->getStatementsByUserVotes($currentUser->getId());
        }

        // sort Statements by external Id
        return $votedStatements
            // display only votes from current procedure
            ->filter(static fn (Statement $statement) => $statement->getProcedureId() === $procedure)
            ->sortBy(
                static fn (Statement $statement) => $statement->getExternId()
            )
            ->toArray();
    }

    /**
     * @throws MessageBagException
     */
    private function generateIsolatedCountyMessage(Collection $isolatedAreaInformationIds, Statement $parentStatement)
    {
        $isolatedAreaInformationIds->each(function ($id) use ($parentStatement) {
            $name = '';
            if ($this->countyService->getCounty($id) instanceof County) {
                $name = $this->countyService->getCounty($id)->getName();
            }

            $externalId = $parentStatement->getExternId();

            if ($parentStatement->getParentId() !== $parentStatement->getOriginalId()) {
                $externalId = 'Kopie von '.$externalId;
            }

            $this->getMessageBag()->add('warning', 'warning.isolated.county',
                ['name' => $name, 'parentStatementExternalId' => $externalId]
            );
        });
    }

    /**
     * @throws MessageBagException
     */
    private function generateIsolatedPriorityAreaMessage(Collection $isolatedPriorityAreasIds, Statement $parentStatement)
    {
        $isolatedPriorityAreasIds->each(function ($id) use ($parentStatement) {
            $name = '';
            $externalId = '';
            $priorityArea = $this->getPriorityAreaService()->getPriorityArea($id);
            if ($priorityArea instanceof PriorityArea) {
                $name = $priorityArea->getName();

                $externalId = $parentStatement->getExternId();

                if ($parentStatement->getParentId() !== $parentStatement->getOriginalId()) {
                    $externalId = 'Kopie von '.$externalId;
                }
            }
            $this->getMessageBag()->add('warning', 'warning.isolated.priorityArea',
                ['name' => $name, 'parentStatementExternalId' => $externalId]
            );
        });
    }

    private function generateIsolatedMunicipalityMessage(Collection $isolatedMunicipalityIds, Statement $parentStatement)
    {
        $isolatedMunicipalityIds->each(function ($id) use ($parentStatement) {
            $name = '';
            $municipality = $this->getMunicipalityService()->getMunicipality($id);
            if ($municipality instanceof Municipality) {
                $name = $municipality->getName();
            }
            $externalId = $parentStatement->getExternId();

            if ($parentStatement->getParentId() !== $parentStatement->getOriginalId()) {
                $externalId = 'Kopie von '.$externalId;
            }

            $this->getMessageBag()->add('warning', 'warning.isolated.municipality',
                ['name' => $name, 'parentStatementExternalId' => $externalId]
            );
        });
    }

    private function generateIsolatedTagMessage(Collection $isolatedTagIds, Statement $parentStatement)
    {
        $isolatedTagIds->each(function ($id) use ($parentStatement) {
            $tag = $this->tagService->getTag($id);
            // do not create a isolated tag warning if the tag is not present in the parent statement
            if (in_array($id, $parentStatement->getTagIds(), true)) {
                $name = $tag instanceof Tag ? $tag->getName() : '';

                $externalId = $parentStatement->getExternId();

                if ($parentStatement->getParentId() !== $parentStatement->getOriginalId()) {
                    $externalId = 'Kopie von '.$externalId;
                }

                $this->getMessageBag()->add(
                    'warning',
                    'warning.isolated.tag',
                    ['name' => $name, 'parentStatementExternalId' => $externalId]
                );
            }
        });
    }

    /**
     * Collect varaiables for displaying new manual statement form.
     *
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function generateTemplateVarsForNewStatementForm($procedureId): array
    {
        $templateVars = [];
        $procedureHandler = $this->procedureHandler;
        $statementService = $this->statementService;

        $templateVars['table']['newestInternalId'] = $statementService->getNewestInternId(
            $procedureId
        );

        $templateVars['table']['procedure'] = $procedureHandler->getProcedure($procedureId, false);
        $templateVars['table']['usedInternIds'] = $statementService->getInternIdsFromProcedure($procedureId);

        $resElements = $this->getElementBlock($procedureId);

        if (isset($resElements['paragraph'])) {
            $templateVars['table']['paragraph'] = $resElements['paragraph'];
        }
        if (isset($resElements['documents'])) {
            $templateVars['table']['documents'] = $resElements['documents'];
        }
        if (isset($resElements['elements'])) {
            $templateVars['table']['elements'] = $resElements['elements'];
        }

        // Verfahrensschritte
        $templateVars['internalPhases'] = $this->getDemosplanConfig()->getInternalPhases();
        $templateVars['externalPhases'] = $this->getDemosplanConfig()->getExternalPhases();

        // add vars for location fields
        $procedureService = $this->procedureService;
        $countyService = $this->getCountyService();
        $municipalityService = $this->getMunicipalityService();
        if ($this->permissions->hasPermission('field_statement_county')) {
            $templateVars['availableCounties'] = $countyService->getAllCounties();
        }
        if ($this->permissions->hasPermission('field_statement_municipality')) {
            $templateVars['availableMunicipalities'] = $municipalityService->getAllMunicipalities();
        }
        if ($this->permissions->hasPermission('field_statement_priority_area')) {
            $templateVars['availablePriorityAreas'] = $this->getPriorityAreaService()->getAllPriorityAreas();
        }
        if ($this->permissions->hasPermission('feature_statements_tag')) {
            $templateVars['availableTopics'] = $procedureService->getTopics($procedureId);
        }

        return $templateVars;
    }

    /**
     * Check if public participation publication procedure setting was respected, we don't want the
     * <code>r_publicVerified</code> key to be used if no publishing is allowed.
     *
     * @param array       $data
     * @param string|null $procedureId
     *
     * @throws ProcedurePublicationException
     * @throws Exception
     */
    public function checkProcedurePublicationSetting($data, $procedureId)
    {
        if (array_key_exists('r_publicVerified', $data)) {
            $procedure = $this->procedureService->getProcedure($procedureId);

            if (!($procedure instanceof Procedure)) {
                throw ProcedurePublicationException::procedureNotFound($procedureId);
            }

            if (!$procedure->getPublicParticipationPublicationEnabled()) {
                throw ProcedurePublicationException::publicationNotAllowed($procedureId);
            }
        }
    }

    /**
     * Used on create a manual statement.
     */
    public function newStatement(array $data, bool $isDataInput = false)
    {
        // tackle legacy structure
        if (array_key_exists('request', $data)) {
            $data = $data['request'];
        }

        $newOriginalStatement = null;
        try {
            $statementService = $this->statementService;
            // create original Statement
            $originalStatement = $statementService->fillNewStatementArray($data, true);
            $newOriginalStatement = $statementService->newStatement($originalStatement);

            // create (non original) statement
            // Some given Statement Data should not be on original Statement:
            if ($newOriginalStatement instanceof Statement) {
                $assessableStatement = $this->createNonOriginalStatement($originalStatement, $newOriginalStatement);

                if ($this->permissions->hasPermission('feature_similar_statement_submitter')) {
                    $this->attachSimilarStatementSubmitters($assessableStatement, $data);
                    $this->statementService->updateStatementObject($assessableStatement);
                }

                /** @var ManualStatementCreatedEvent $assessableStatementEvent */
                $assessableStatementEvent = $this->eventDispatcher->dispatch(
                    new ManualStatementCreatedEvent($assessableStatement),
                    ManualStatementCreatedEventInterface::class
                );
                $assessableStatement = $assessableStatementEvent->getStatement();

                $routeName = 'dm_plan_assessment_single_view';
                $routeParameters = ['procedureId' => $newOriginalStatement->getProcedureId(), 'statement' => $assessableStatement->getId()];

                if ('' != ($originalStatement['headStatementId'] ?? '')) {
                    $routeName = 'DemosPlan_cluster_single_statement_view';
                    $routeParameters = ['procedure' => $newOriginalStatement->getProcedureId(), 'statementId' => $assessableStatement->getId()];
                }

                if ($isDataInput) {
                    $routeName = 'DemosPlan_statement_single_view';
                    $routeParameters = ['procedureId' => $newOriginalStatement->getProcedureId(), 'statementId' => $newOriginalStatement->getId()];
                }

                if ($this->permissions->hasPermission('feature_segments_of_statement_list')) {
                    $routeName = 'dplan_statement_segments_list';
                    $routeParameters = ['procedureId' => $newOriginalStatement->getProcedureId(), 'statementId' => $assessableStatement->getId(), 'action' => 'editText'];
                }

                // check for permission to avoid link to an unreachable area
                if ($this->permissions->hasPermission('area_admin_assessmenttable')
                    || $this->permissions->hasPermission('feature_segments_of_statement_list')
                    || $this->permissions->hasPermission('feature_statement_data_input_orga')) {
                    // success messages with link to created statement
                    $this->getMessageBag()->addObject(LinkMessageSerializable::createLinkMessage(
                        'confirm',
                        'confirm.statement.new',
                        ['externId' => $assessableStatement->getExternId()],
                        $routeName,
                        $routeParameters,
                        $assessableStatement->getExternId())
                    );
                } else {
                    $this->messageBag->add(
                        'confirm',
                        'confirm.statement.new',
                        ['externId' => $assessableStatement->getExternId()]
                    );
                }
            } else {
                $this->getMessageBag()->add('error', 'error.save');
            }
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.save');
            $this->logger->warning('Error on creating new statement', [$e]);
        }

        return $newOriginalStatement;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws MessageBagException
     */
    private function attachSimilarStatementSubmitters(Statement $statementToAttachTo, array $data): Statement
    {
        if (!array_key_exists('r_similarStatementSubmitters', $data)
            || !is_array($data['r_similarStatementSubmitters'])
            || 0 === count($data['r_similarStatementSubmitters'])) {
            $statementToAttachTo->setSimilarStatementSubmitters(new ArrayCollection());

            return $statementToAttachTo;
        }

        foreach ($data['r_similarStatementSubmitters'] as $similarStatementSubmitter) {
            $procedure = $statementToAttachTo->getProcedure();
            $similarStatementSubmitter = $this->replaceEmptyWithNull(
                $similarStatementSubmitter,
                ['city', 'streetName', 'streetNumber', 'postalCode', 'emailAddress']
            );
            $submitter = new ProcedurePerson($similarStatementSubmitter['fullName'], $procedure);
            $updater = new PropertiesUpdater($similarStatementSubmitter);
            $this->statementService->updatePersonEditableProperties($updater, $submitter);
            $statementToAttachTo->getSimilarStatementSubmitters()->add($submitter);
        }
        // Validate similarSubmitter on statement
        $violations = $this->validator->validate($statementToAttachTo, null, 'manual_create');

        // using a more concise Collection::map/forAll approach is not possible because
        // Symfony's TraceableValidator is not able to handle Closures properly
        foreach ($statementToAttachTo->getSimilarStatementSubmitters() as $similarStatementSubmitter) {
            $additionalViolations = $this->validator->validate($similarStatementSubmitter);
            $violations->addAll($additionalViolations);
        }

        if (0 < count($violations)) {
            $statementToAttachTo->setSimilarStatementSubmitters(new ArrayCollection());
            $this->getMessageBag()->add('error', 'error.statement.similar_submitter.invalid');
            $this->logger->warning('Error on validating the new statement for similarStatementSubmitter.', [$violations]);
        }

        return $statementToAttachTo;
    }

    /**
     * @return Statement the copy of the given original statement
     *
     * @throws CopyException
     */
    public function createNonOriginalStatement(array $originalStatementData, Statement $newOriginalStatement): Statement
    {
        $fieldsForUpdateStatement = $this->extractFieldsForUpdateStatement($originalStatementData);
        if ($newOriginalStatement->getFiles()) {
            $copyOfStatement = $this->statementCopier->copyStatementObjectWithinProcedureWithRelatedFiles($newOriginalStatement, false, true);
        } else {
            $copyOfStatement = $this->statementCopier->copyStatementObjectWithinProcedure($newOriginalStatement, false, true);
        }
        // Some values should only be set on copied statement instead of OriginalStatement itself:
        $this->createVotesOnCreateStatement(
            $copyOfStatement,
            $fieldsForUpdateStatement['votes'],
            $fieldsForUpdateStatement['numberOfAnonymVotes']
        );

        if (null !== $fieldsForUpdateStatement['headStatementId']) {
            $headStatement = $this->getStatement($fieldsForUpdateStatement['headStatementId']);
            // ignore assignment because of new created Statement is not assigned to anyone
            $this->addStatementToCluster($headStatement, $copyOfStatement, true, true);
        }

        return $copyOfStatement;
    }

    /**
     * @return array
     */
    protected function extractFieldsForUpdateStatement(array &$statementArray)
    {
        $votes = [];
        if (array_key_exists('votes', $statementArray)) {
            $votes = $statementArray['votes'];
            unset($statementArray['votes']);
        }

        $numberOfAnonymVotes = null;
        if (array_key_exists('numberOfAnonymVotes', $statementArray) && '' != $statementArray['numberOfAnonymVotes']) {
            $numberOfAnonymVotes = $statementArray['numberOfAnonymVotes'];
            unset($statementArray['numberOfAnonymVotes']);
        }

        $headStatementId = null;
        if (array_key_exists('headStatementId', $statementArray) && '' != $statementArray['headStatementId']) {
            $headStatementId = $statementArray['headStatementId'];
            unset($statementArray['headStatementId']);
        }

        return [
            'votes'               => $votes,
            'numberOfAnonymVotes' => $numberOfAnonymVotes,
            'headStatementId'     => $headStatementId,
        ];
    }

    /**
     * Create Variables for Elements to be parsed in Template.
     *
     * @param string $procedureId
     *
     * @return array
     *
     * @throws Exception
     */
    public function getElementBlock($procedureId)
    {
        $templateVars = [];
        // Kategorien abrufen
        $organisationId = $this->currentUser->getUser()->getOrganisationId();
        $elements = $this->elementsService->getElementsListObjects($procedureId, $organisationId, false, true);

        // Textliche Festsetzung abrufen
        $paragraphDocumentList = $this->paragraphService->getParaDocumentAdminListAll($procedureId);

        // Planunterlagen abrufen
        $singleDocumentList = $this->singleDocumentService->getSingleDocumentAdminListAll($procedureId);

        foreach ($paragraphDocumentList['result'] as $value) {
            $ptitle = $value['title'];
            $templateVars['paragraph'][$value['elementId']][] = [
                'ident'     => $value['ident'],
                'id'        => $value['id'],
                'title'     => $ptitle,
                'elementId' => $value['elementId'],
            ];
        }
        foreach ($singleDocumentList['result'] as $value) {
            // exclude documents where no statements are allowed
            if (false === $value['statementEnabled'] || false === $value['visible']) {
                continue;
            }
            $templateVars['documents'][$value['elementId']][] =
                collect($value)
                    ->only('id', 'ident', 'title')
                    ->toArray();
        }

        $templateVars['elements'] = array_map(static fn (Elements $element) => [
            'id'       => $element->getId(),
            'ident'    => $element->getIdent(),
            'title'    => $element->getTitle(),
            'category' => $element->getCategory(),
        ], $elements);

        return $templateVars;
    }

    /**
     * Determines if the tag of the given id in use.
     *
     * @param string $tagId - Id of the tag to check
     *
     * @return bool - true if the tag is not null and there are related statements
     *              or related statement-fragments on the tag, otherwise false
     */
    public function isTagInUse($tagId): bool
    {
        $tag = $this->getTag($tagId);

        if (null === $tag) {
            return false;
        }

        return $this->isTagObjectInUse($tag);
    }

    protected function isTagObjectInUse(Tag $tag): bool
    {
        if ($tag->getStatements()->isEmpty()) {
            $fragments = $this->statementFragmentService->getStatementFragmentsTag($tag->getId());
            if (0 === count((array) $fragments)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines if one of the tags of the given topic of the id in use.
     * Returns all fragments related to the tags of the given topic-ID.
     *
     * @param string $topicId - Id of the topic whose tags are to check
     *
     * @return bool - true if one of the tags of the topic have related statements or related statement fragments, otherwise false
     */
    public function isTopicInUse($topicId): bool
    {
        $topic = $this->tagService->getTopic($topicId);

        if (null === $topic) {
            return false;
        }

        $relatedTags = $topic->getTags();
        foreach ($relatedTags as $relatedTag) {
            if ($this->isTagObjectInUse($relatedTag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns all tags that belong to the given procedure in a special format.
     *
     * @param string $procedureId
     *
     * @return array
     *
     * @throws Exception
     */
    public function getTopicsAndTagsOfProcedureAsArray($procedureId)
    {
        $topics = $this->getTopicsByProcedure($procedureId);
        $result = [];

        foreach ($topics as $topic) {
            $resolved = [];
            $resolved['tags'] = array_map(fn (Tag $tag) => ['id' => $tag->getId(), 'name' => $tag->getTitle()], $topic->getTags()->toArray());
            $resolved['name'] = $topic->getTitle();
            $resolved['id'] = $topic->getId();
            $result[] = $resolved;
        }

        // return result as JSON
        return $result;
    }

    public function updateStatementObject($statement, $ignoreAssignment = false, $ignoreCluster = false, $ignoreOriginal = false)
    {
        return $this->statementService->updateStatement($statement, $ignoreAssignment, $ignoreCluster, $ignoreOriginal);
    }

    /**
     * This method only assign a fragment to a user.
     * No other data will be updated, whereby no special checks are needed.
     * This will not creating a report entry!
     *
     * @param StatementFragment $fragment - fragment, which will be assigned
     * @param User              $user     - User to assign to. If the user is null, the fragment will be freed
     *
     * @throws Exception
     */
    public function setAssigneeOfStatementFragment(StatementFragment $fragment, ?User $user = null)
    {
        $fragment->setAssignee($user);
        $this->statementFragmentService->updateStatementFragment($fragment, true);
    }

    /**
     * This method assign a statement to a user.
     * If the given Statement is a cluster, all statements of the cluster will be assigned,
     * including the headStatement itself.
     * No other data will be updated, whereby no special checks are needed.
     * This will not creating a report entry!
     *
     * @param Statement $statement     - statement, which will be assigned
     * @param User      $user          - User to assign to. If the user is null, the statement will be freed
     * @param bool      $ignoreCluster -
     *
     * @return bool|string - true if the given statement was successfully assigned, otherwise the Extern-ID of the statement
     */
    public function setAssigneeOfStatement(Statement $statement, ?User $user = null, $ignoreCluster = false)
    {
        $assignedStatementOfCluster = 0;
        $cluster = $statement->getCluster();
        $elementsInCluster = is_countable($cluster) ? count($cluster) : 0;

        // if the given Statement is a headStatement, there will be a cluster:
        foreach ($cluster as $clusterElement) {
            $result = $this->setAssigneeOfStatement($clusterElement, $user, true);

            if (true === $result) {
                ++$assignedStatementOfCluster;
            } else {
                // break and return externId of statement for message
                return $result;
            }
        }

        // update only if all statements of cluster are successfully assigned:
        if ($assignedStatementOfCluster === $elementsInCluster) {
            $statement->setAssignee($user);
            $updatedStatement = $this->statementService->updateStatementFromObject($statement, true, $ignoreCluster);

            if ($updatedStatement instanceof Statement) {
                return true;
            } else {
                $this->getLogger()->error('Set assignee of Statement '.$statement->getId().' failed.');
            }
        }

        return $statement->getExternId();
    }

    /**
     * @return array<int, Statement>
     */
    public function getStatementsOfProcedureAndOrganisation(string $procedureId, string $organisationId): array
    {
        return $this->statementService->getStatementsOfProcedureAndOrganisation($procedureId, $organisationId);
    }

    /**
     * @return ServiceOutput
     */
    protected function getProcedureOutput()
    {
        return $this->procedureOutput;
    }

    /**
     * @deprecated use DI instead
     *
     * @return Permissions
     *
     * @throws Exception
     */
    public function getPermissions()
    {
        if (!$this->permissions instanceof Permissions) {
            throw new Exception('Inject permissions object into StatementHandler first');
        }

        return $this->permissions;
    }

    /**
     * @deprecated use DI instead
     *
     * @param Permissions $permissions
     */
    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * Check whether Statement and Fragments are assigned to current user and is
     * not assigned to reviewer.
     *
     * @throws Exception
     * @throws MessageBagException
     */
    protected function hasValidStatementAssignments(Statement $statementToCheck): bool
    {
        // this function are only used in cluster actions atm ...
        // check for assigment of statement and his fragments
        if ($this->permissions->hasPermission('feature_statement_assignment')) {
            if (!$this->areAllFragmentsClaimedByCurrentUser($statementToCheck->getId())) {
                $this->getMessageBag()->add(
                    'warning',
                    'statement.cluster.fragments.not.claimed.by.current.user',
                    ['ids' => $statementToCheck->getExternId()]
                );

                return false;
            }

            if (!$this->assignService->isStatementObjectAssignedToCurrentUser($statementToCheck)) {
                $this->getMessageBag()->add('warning', 'confirm.consolidation.not.assigned');

                return false;
            }
        }

        // T5505 do not copy fragment if assigned ro reviewer
        if (!$this->isNoFragmentAssignedToReviewer($statementToCheck->getId())) {
            $this->getMessageBag()->add(
                'warning',
                'warning.statement.cluster.copy.fragment.assigned.to.reviewer',
                ['statementId' => $statementToCheck->getExternId()]
            );

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>   $keys
     *
     * @return array<string, mixed>
     */
    protected function replaceEmptyWithNull(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (is_string($value) && '' === trim($value)) {
                    $data[$key] = null;
                }
            }
        }

        return $data;
    }

    /**
     * Validates that all Statements in $statements belong to Procedure $procedureId.
     *
     * @param Statement[] $statements
     *
     * @throws MessageBagException
     */
    protected function validateStatementsProcedure(string $procedureId, array $statements): bool
    {
        if (0 < count($statements)) {
            $firstStatementProcedureId = $statements[0]->getProcedureId();
            if ($firstStatementProcedureId != $procedureId) {
                $this->getLogger()->info('Statements does not belong to procedure '.$procedureId);
                $this->getMessageBag()->add(
                    'error', 'warning.statements.wrong.procedure',
                    ['procedureId' => $procedureId]);

                return false;
            }
            foreach ($statements as $statement) {
                if ($statement instanceof Statement) {
                    if (!($procedureId === $statement->getProcedureId())) {
                        $this->getLogger()->info('Statements do not belong to same procedure');
                        $this->getMessageBag()->add('error', 'warning.statement.cluster.removed.placeholder');

                        return false;
                    }
                } else {
                    $this->getLogger()->warning('$statement is not an object of Statement');
                }
            }
        }

        return true;
    }

    /**
     * Determines if every Fragment are assigned to the current user.
     *
     * @param string $statementId
     *
     * @return bool - true if every Fragment of the given Statement is assigned to the current user, otherwise false
     */
    protected function areAllFragmentsClaimedByCurrentUser($statementId): bool
    {
        return $this->statementFragmentService->areAllFragmentsClaimedByCurrentUser($statementId);
    }

    /**
     * Determines if no Fragment is assigned reviewer.
     *
     * @param string $statementId
     *
     * @return bool - true if no Fragment of the given Statement is assigned to any reviewer, otherwise false
     */
    protected function isNoFragmentAssignedToReviewer($statementId): bool
    {
        return $this->statementFragmentService->isNoFragmentAssignedToReviewer($statementId);
    }

    /**
     * Create new Statement which will copy values of the following attributes of the given $statement:.
     *
     * @param Statement   $representativeStatement - Statement, whose attributes will be copied
     * @param string|null $name                    - custom name of cluster-statement
     *
     * @return Statement - new created Statement which can be used to be HeadStatement of a Cluster
     *
     * @throws StatementNameTooLongException
     */
    protected function generateHeadStatement(Statement $representativeStatement, ?string $name = null): Statement
    {
        $headStatement = new Statement();
        try {
            // do not check for instance of Statement because of Proxy Object in Unit tests (will fail)
            if (null === $headStatement) {
                $this->getLogger()->error('Could not choose Statement to create Cluster');
                throw new InvalidArgumentException('Could not choose Statement to create Cluster');
            }

            $headStatement->setClusterStatement(true);
            $headStatement->setAssignee($representativeStatement->getAssignee());
            $headStatement->setCounties($representativeStatement->getCounties());
            $headStatement->setDocument($representativeStatement->getDocument());
            $headStatement->setElement($representativeStatement->getElement());
            $headStatement = $this->statementService->setPublicVerified(
                $headStatement,
                Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED
            );

            $clusterPrefix = $this->globalConfig->getClusterPrefix();
            $headStatement->setExternId($clusterPrefix.$representativeStatement->getExternId());

            $headStatement->setMapFile($representativeStatement->getMapFile());
            $headStatement->setMemo($representativeStatement->getMemo());

            $emptyMetaData = new StatementMeta();
            $emptyMetaData->setStatement($headStatement);
            $headStatement->setMeta($emptyMetaData);

            $headStatement->setMunicipalities($representativeStatement->getMunicipalities());
            $headStatement->setParagraph($representativeStatement->getParagraph());
            $headStatement->setPhase($representativeStatement->getPhase());
            $headStatement->setPolygon($representativeStatement->getPolygon());
            $headStatement->setPriority($representativeStatement->getPriority());
            $headStatement->setPriorityAreas($representativeStatement->getPriorityAreas()->toArray());
            $headStatement->setProcedure($representativeStatement->getProcedure());
            $headStatement->setRecommendation($representativeStatement->getRecommendation());
            $headStatement->setStatus($representativeStatement->getStatus());
            $headStatement->setSubmit($representativeStatement->getSubmitObject());
            $headStatement->setTags($representativeStatement->getTags()->toArray());
            $headStatement->setText($representativeStatement->getText());

            $headStatement->setRepresentationCheck($representativeStatement->getRepresentationCheck());
            $headStatement->setRepresents($representativeStatement->getRepresents());

            // To enable the Email-textfield in the detailClusterView for the planer.
            // Set Feedback to email, to ensure there is a field to save a emailText in the headStatement.
            // Otherwise in the end of the the procedure there is no emailtext to set for each statement in the cluster
            // which has actually set feedback to 'email'.
            $headStatement->setFeedback('email');

            // not nullable but initialized with null:
            $votePla = $representativeStatement->getVotePla();
            if (null !== $votePla) {
                $headStatement->setVotePla($votePla);
            }
            $voteStk = $representativeStatement->getVoteStk();
            if (null !== $voteStk) {
                // not nullable but initialized with null:
                $headStatement->setVoteStk($voteStk);
            }

            if (null !== $name) {
                $maxLength = 200;
                $actualLength = strlen($name);
                if ($maxLength < $actualLength) {
                    throw StatementNameTooLongException::create($actualLength, $maxLength);
                }
                $headStatement->setName($name);
            }

            // persist headstatement as it is needed as parent statement later on
            $this->entityManager->persist($headStatement);
            $this->entityManager->flush();

            $this->getLogger()->info('Cluster headstatement generated');

            $fileService = $this->fileService;
            collect($representativeStatement->getFiles())
                ->map(function ($fileString) use ($fileService, $headStatement) {
                    $fileService->addStatementFileContainer(
                        $headStatement->getId(),
                        $fileService->getInfoFromFileString($fileString, 'hash'),
                        $fileString
                    );
                })->toArray();
            // Update Statement with attached files
        } catch (StatementNameTooLongException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->getLogger()->error('genereate headStatement failed: ', [$e]);
        }

        return $this->getStatement($headStatement->getId());
    }

    /**
     * Add the given Statement to the  given headStatement (cluster).
     *
     * @param bool $ignoreAssignmentOfStatement     - Determines if a assignment statement will be updated regardless
     * @param bool $ignoreAssignmentOfHeadStatement - Determines if a assignment headStatement will be updated regardless
     *
     * @return Statement|bool - false the given statementToAdd was not added to the given headStatement,
     *                        otherwise the headStatement
     */
    public function addStatementToCluster(
        Statement $headStatement,
        Statement $statementToAdd,
        $ignoreAssignmentOfStatement = false,
        $ignoreAssignmentOfHeadStatement = false,
    ) {
        try {
            if (!$headStatement->isClusterStatement()) {
                // easy possible solution would be to use createStatementCluster instead
                $this->getLogger()->error('Given Statement is not a Cluster/HeadStatement');

                return false;
            }

            if ($statementToAdd->isInCluster()) {
                $this->getLogger()->error('Given StatementToAdd ('.$statementToAdd->getExternId().') is already in a cluster ('.$statementToAdd->getHeadStatement()->getExternId().')');

                return false;
            }

            // check for assignment of statement and its fragments
            if (false === $ignoreAssignmentOfStatement
                && false === $this->hasValidStatementAssignments($statementToAdd)) {
                return false;
            }

            // check for assignment of statement and its fragments
            if (false === $ignoreAssignmentOfHeadStatement
                && false === $this->hasValidStatementAssignments($headStatement)) {
                return false;
            }

            // check for placeholderStatement
            if ($statementToAdd->isPlaceholder()) {
                $this->getLogger()->warning('On create statement cluster: removed Statement '.$statementToAdd->getId().' because it is a placeholder statement.');
                $this->getMessageBag()->add('warning',
                    'warning.statement.cluster.removed.placeholder',
                    ['%externId' => $statementToAdd->getExternId()]);

                return false;
            }

            $headStatement->addStatement($statementToAdd);

            // T12692: first update statement object to ensure version entry will be created:
            $successfullyUpdatedHeadStatement = $this->updateStatementObject($headStatement);
            if ($successfullyUpdatedHeadStatement instanceof Statement) {
                // will also check 'feature_statement_assignment':
                $successfulAddedStatement =
                    $this->statementService->updateStatementFromObject($statementToAdd, $ignoreAssignmentOfStatement, true);
                if ($successfulAddedStatement instanceof Statement) {
                    return $successfulAddedStatement->getHeadStatement();
                }
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Add Statement to Clusterfailed: ', [$e]);
        }

        return false;
    }

    /**
     * @param Statement[] $statementsToAdd
     *
     * @throws MessageBagException
     */
    public function addStatementsToCluster(Statement $headStatement, array $statementsToAdd)
    {
        $successfulAddedElements = collect([]);
        $notSuccessfulAddedElements = collect([]);

        foreach ($statementsToAdd as $statementToAdd) {
            $result = $this->addStatementToCluster($headStatement, $statementToAdd);

            if ($result instanceof Statement) {
                $successfulAddedElements->push($statementToAdd->getExternId());
            } else {
                $notSuccessfulAddedElements->push($statementToAdd->getExternId());
            }
        }

        if (0 < $successfulAddedElements->count()) {
            $this->getMessageBag()->add('confirm', 'confirm.statements.cluster.added',
                [
                    'statementIds' => $successfulAddedElements->implode(', '),
                    'clusterId'    => $headStatement->getExternId(),
                ]);

            $this->getLogger()->info('addStatementsToCluster pushed '.$successfulAddedElements->implode(', '));
        }

        if (0 < $notSuccessfulAddedElements->count()) {
            $this->getMessageBag()->addChoice(
                'warning',
                'warning.statements.cluster.not.added',
                [
                    'statementIds' => $notSuccessfulAddedElements->implode(', '),
                    'clusterId'    => $headStatement->getExternId(),
                    'count'        => $notSuccessfulAddedElements->count(),
                ]);

            $this->getLogger()->info('addStatementsToCluster could not push '.$notSuccessfulAddedElements->implode(', '));
        }
    }

    /**
     * Detaches the given Statement from his cluster.
     * Will also delete the cluster if there are no remaining Statements.
     *
     * @return bool - true, if the given Statement was successfully detached form his cluster, otherwise false
     */
    public function detachStatementFromCluster(Statement $statementToDetach)
    {
        try {
            $statementService = $this->statementService;
            $removedStatement = null;
            $headStatement = $statementToDetach->getHeadStatement();

            if (null === $headStatement) {
                $this->getLogger()->warning('Given Statement to detach '.$statementToDetach->getId().' is not member of a cluster.');
            }

            $successfulRemoved = $headStatement->removeClusterElement($statementToDetach);
            if (!$successfulRemoved) {
                $this->getMessageBag()->add(
                    'error', 'error.statement.detach.cluster.element',
                    ['statementId' => $statementToDetach->getExternId()]
                );

                return false;
            }

            // will check for assignment and cluster:
            $headStatement = $statementService->updateStatementFromObject($headStatement, false, false);

            // todo: workaround to solve versioning problem for cluster<->headstatement
            $statementToDetach->setHeadStatement(null);

            // only on success:
            if ($headStatement instanceof Statement) {
                // disable check for assignment and cluster:
                $removedStatement = $statementService->updateStatementFromObject($statementToDetach, true, true);
            } else {
                // failed detach $statementToDetach from $headStatement:
                $this->getMessageBag()->add(
                    'error', 'error.statement.detach.cluster.element',
                    ['statementId' => $statementToDetach->getExternId()]
                );

                return false;
            }

            if ($headStatement instanceof Statement && $removedStatement instanceof Statement) {
                $this->getMessageBag()->add(
                    'confirm', 'confirm.statement.detach.cluster.element',
                    [
                        'statementId' => $statementToDetach->getExternId(),
                        'clusterId'   => $headStatement->getExternId(),
                    ]
                );

                if (0 === $headStatement->getCluster()->count()) {
                    $headStatementId = $headStatement->getExternId();

                    // will also check for 'feature_statement_assignment' & 'feature_statement_cluster':
                    $status = $this->statementDeleter->deleteStatementObject($headStatement);

                    if ($status) {
                        $this->getMessageBag()->add(
                            'confirm', 'confirm.statement.cluster.resolved',
                            ['clusterId' => $headStatementId]
                        );
                    } else {
                        $this->getLogger()->warning(
                            'Delete empty Cluster (Statement) failed, ID: ', [$headStatementId]);
                        $this->getMessageBag()->add(
                            'error', 'error.statement.cluster.deleted',
                            ['clusterId' => $headStatementId]
                        );
                    }

                    return $status;
                }

                return true;
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Detach Statement from Cluster failed: ', [$e]);
        }

        return false;
    }

    /**
     * Detaches all Statements of the custer and deletes the headStatement.
     *
     * If there are Statements in the cluster, which can not be detached from the cluster,
     * the cluster will not be deleted.
     *
     * @return bool
     */
    public function resolveCluster(Statement $headStatement)
    {
        $successful = false;
        if (!$headStatement->isClusterStatement()) {
            $this->getMessageBag()->add(
                'error',
                'error.no.cluster.given',
                ['statementId' => $headStatement->getExternId()]
            );

            return $successful;
        }

        $statementService = $this->statementService;

        if ($statementService->isStatementObjectLockedByAssignment($headStatement)) {
            $statementService->addMessageLockedByAssignment($headStatement);

            return false;
        }

        $notDetachedStatements = collect([]);
        $statementsOfCluster = $headStatement->getCluster();

        foreach ($statementsOfCluster as $statement) {
            $statement->setHeadStatement(null);
            // will also check for 'feature_statement_assignment':
            $removedStatement = $statementService->updateStatementFromObject($statement, true, true);

            if (!$removedStatement instanceof Statement) {
                $notDetachedStatements->push($statement);

                $this->getMessageBag()->add(
                    'error', 'error.statement.cluster.resolve',
                    ['clusterId' => $headStatement->getExternId()]
                );
            }
        }

        if (0 === $notDetachedStatements->count()) {
            $this->getLogger()->info("All statements of Cluster {$headStatement->getId()} are successfully detached.");
            // will also check for assignment but not for clustered!
            $successful = $this->statementDeleter->deleteStatementObject($headStatement, true);
        } else {
            $this->getLogger()->error("Some statements of Cluster {$headStatement->getId()} are not detached.");
            $this->getMessageBag()->add(
                'warning', 'warning.statement.cluster.resolve.incomplete',
                ['clusterId' => $headStatement->getExternId()]
            );
            $headStatement->setCluster($notDetachedStatements->toArray());
            $statementService->updateStatementFromObject($headStatement, true, true);

            return false;
        }

        if ($successful) {
            $this->getMessageBag()->add(
                'confirm', 'confirm.statement.cluster.resolved',
                ['clusterId' => $headStatement->getExternId()]
            );
        }

        return $successful;
    }

    /**
     * Returns StatementFragments, related to a specific Statement.
     *
     * @param string|string[] $statementId
     * @param array           $filters
     * @param string          $search
     * @param int             $limit
     * @param int             $page
     */
    public function getStatementFragmentsStatementES($statementId, $filters, $search = '', $limit = 10000, $page = 1): ElasticsearchResultSet
    {
        $elasticsearchResultSet = $this->statementFragmentService->getStatementFragmentsStatementES($statementId, $filters, $search, $limit, $page);

        return $this->cleanFragments($elasticsearchResultSet);
    }

    /**
     * @param string $statementId
     * @param string $fragmentId
     *
     * @return array|null;
     */
    public function getFragmentOfStatementES($statementId, $fragmentId)
    {
        $fragments = $this->getStatementFragmentsStatementES($statementId, []);

        return collect($fragments->getResult())
            ->filter(fn ($fragment) => $fragment['id'] === $fragmentId)->first(null, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFragmentOfStatement(string $fragmentId): array
    {
        return $this->entityManager->getRepository(StatementFragment::class)->getAsArray($fragmentId);
    }

    /**
     * @param array $statementIds
     *
     * @return Collection
     */
    public function getHeadStatementIdsOfStatementIds($statementIds)
    {
        return $this->statementService->getHeadStatementIdsOfStatements($statementIds);
    }

    /**
     * Exclude Fields from Fragments depending on permissions and/or state of fragment.
     */
    public function cleanFragments(ElasticsearchResultSet $elasticsearchResultSet): ElasticsearchResultSet
    {
        $fragmentsToReturn = [];

        foreach ($elasticsearchResultSet->getResult() as $key => $fragment) {
            $keysToRemove = [];

            if (!$this->permissions->hasPermission('feature_statements_fragment_consideration')) {
                $keysToRemove[0] = 'consideration';
            }

            if (!$this->permissions->hasPermission('feature_statements_fragment_consideration_advice')) {
                $keysToRemove[1] = 'considerationAdvice';
            }

            if (!$this->permissions->hasPermission('feature_statements_fragment_vote')) {
                $keysToRemove[2] = 'vote';
            }

            // only if voteAdvice is set and has a value, it has to be removed, to ensure data security
            if (!$this->permissions->hasPermission('feature_statements_fragment_advice')
                && array_key_exists('voteAdvice', $fragment)
                && null != $fragment['voteAdvice']) {
                $keysToRemove[3] = 'voteAdvice';
            }

            // fragment is currently not assigned to a department (to set advice)
            // and current user has permission to set vote -> voteAdvice needed
            // (the one who set set vote, shall not see voteAdvice until it is completed)
            if (null === $fragment['departmentId']
                && $this->permissions->hasPermission('feature_statements_fragment_vote')
            ) {
                unset($keysToRemove[3]);
            }

            $fragmentsToReturn[$key] = collect($fragment)->except($keysToRemove)->toArray();
        }

        // as ValueObjects are immutable, we need to copy VO values
        $cleanedFragmentsResultSet = new ElasticsearchResultSet();
        $cleanedFragmentsResultSet->setFilterSet($elasticsearchResultSet->getFilterSet());
        $cleanedFragmentsResultSet->setPager($elasticsearchResultSet->getPager());
        $cleanedFragmentsResultSet->setSearch($elasticsearchResultSet->getSearch());
        $cleanedFragmentsResultSet->setSearchFields($elasticsearchResultSet->getSearchFields());
        $cleanedFragmentsResultSet->setSortingSet($elasticsearchResultSet->getSortingSet());
        $cleanedFragmentsResultSet->setTotal($elasticsearchResultSet->getTotal());
        $cleanedFragmentsResultSet->setResult($fragmentsToReturn);

        return $cleanedFragmentsResultSet->lock();
    }

    /**
     * T6289.
     */
    protected function determineStateOfFragment(StatementFragment $statementFragmentToUpdate, array $updateData): array
    {
        // for definition of states: StatementHandlerTests.php

        /* StatementFragment States:
         *
         * fragment.status.noStatus: "Kein Status"
         * fragment.status.new: "Neu"
         * fragment.status.assignedToFB: "Zugewiesen"
         * fragment.status.assignedBackFromFB: "Zurückgewiesen"
         * fragment.status.verified: "Verifiziert"
         *
         * The status holds translation-keys, which are defined in the messages.de.yml
         */

        $currentState = $statementFragmentToUpdate->getStatus();
        $currentArchivedOrgaName = $statementFragmentToUpdate->getArchivedOrgaName();
        $setVerified = false;
        if (array_key_exists('status', $updateData) && 'on' === $updateData['status']) {
            $setVerified = true;
        }

        // set Automatic, if not verified manually yet
        if ('fragment.status.verified' !== $currentState) {
            // If Department set => 'assigned'
            if (array_key_exists('departmentId', $updateData)) {
                $updateData['status'] = null === $updateData['departmentId'] ? 'fragment.status.new' : 'fragment.status.assignedToFB';
            }

            // When department finished working the archivedOrgaName is set and departmentId is null
            if (array_key_exists('archivedOrgaName', $updateData) && null != $updateData['archivedOrgaName']
                && array_key_exists('departmentId', $updateData)
                && null == $updateData['departmentId']) {
                $updateData['status'] = 'fragment.status.assignedBackFromFB';
                $updateData['archivedDepartment'] = $statementFragmentToUpdate->getDepartment();
            }

            // manually unset State?
            if (array_key_exists('status', $updateData) && null == $updateData['status']) {
                $updateData['status'] = 'fragment.status.new';
                if (null != $currentArchivedOrgaName) {
                    $updateData['status'] = 'fragment.status.assignedBackFromFB';
                    $updateData['archivedDepartment'] = $statementFragmentToUpdate->getDepartment();
                }
            }
        } else {
            // unverify manually:

            if (array_key_exists('departmentId', $updateData) && null !== $updateData['departmentId']) {
                $updateData['status'] = 'fragment.status.assignedToFB';
            } elseif (null !== $currentArchivedOrgaName) {
                $updateData['status'] = 'fragment.status.assignedBackFromFB';
                $updateData['archivedDepartment'] = $statementFragmentToUpdate->getDepartmentId();
            } else {
                $updateData['status'] = 'fragment.status.new';
            }
        }
        if ($setVerified) {
            $updateData['status'] = 'fragment.status.verified';
        }

        return $updateData;
    }

    /**
     * Returns all statementVotes of the user with the given id.
     *
     * You may specify which statementvote-states to exclude/include by
     * modifying the $deleted and $active flags
     *
     * @param string $userId
     * @param bool   $deleted
     * @param bool   $active
     */
    public function getStatementVotes($userId, $deleted = false, $active = true)
    {
        try {
            /** @var StatementVoteRepository $statementVoteRepository */
            $statementVoteRepository = $this->entityManager
                ->getRepository(StatementVote::class);

            return $statementVoteRepository
                ->getByUserId($userId, $deleted, $active);
        } catch (Exception $e) {
            $this->getLogger()->warning('exception while fetching statement votes', [$e]);
        }

        return [];
    }

    /**
     * Returns all statements that are affected by the given list of StatementVotes.
     *
     * @param bool $unique get only Unique statements
     */
    public function getStatementsByVotes(array $statementVotes, $unique = false): Collection
    {
        $statements = collect([]);
        /** @var StatementVote $statementVote */
        foreach ($statementVotes as $statementVote) {
            if ($unique && $statements->contains($statementVote->getStatement())) {
                continue;
            }
            $statements->push($statementVote->getStatement());
        }

        return $statements;
    }

    /**
     * Returns all satements that are affected by votes made by the given user.
     *
     * getVotedStatementsByUser
     *
     * @param string $userId
     *
     * @return Collection<int, Statement>
     */
    public function getStatementsByUserVotes($userId)
    {
        $statementVotes = $this->getStatementVotes($userId);

        return $this->getStatementsByVotes($statementVotes);
    }

    /**
     * Will create the given votes and set the number of anonym votes if given.
     *
     * @param int|null $numberOfAnonymVotes
     */
    protected function createVotesOnCreateStatement(Statement $copyOfStatement, array $votes, $numberOfAnonymVotes = null)
    {
        try {
            if (null !== $numberOfAnonymVotes) {
                $copyOfStatement->setNumberOfAnonymVotes($numberOfAnonymVotes);
            }

            /** @var StatementRepository $statementRepository */
            $statementRepository = $this->statementService
                ->getDoctrine()
                ->getManager()
                ->getRepository(Statement::class);
            $voteObjects = $statementRepository
                ->handleVotesOnStatement($copyOfStatement, $votes);

            $copyOfStatement->setVotes($voteObjects->toArray());

            // use this update method to enable ignoring assignment
            $this->statementService->updateStatementFromObject($copyOfStatement, true);
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not create votes on create statement', [$e]);
        }
    }

    /**
     * Returns all unique staements that are affected by votes made by the given user.
     *
     * getVotedStatementsByUser
     *
     * @param string $userId
     *
     * @return Collection
     */
    public function getStatementsByUserVotesUnique($userId)
    {
        $statementVotes = $this->getStatementVotes($userId);

        return $this->getStatementsByVotes($statementVotes, true);
    }

    /**
     * Returns all ClusterStatements/HeadStatements of the given Procedure.
     *
     * @param string $procedureId
     *
     * @return Statement[]
     */
    public function getClustersOfProcedure($procedureId)
    {
        return $this->statementClusterService->getClustersOfProcedure($procedureId);
    }

    /**
     * refs: T12990:
     * Copy a specific Statement to a specific procedure.
     *
     * The statement and its associated original statement will be copied and set into the target procedure.
     * The most associated Entities will be disassociated!
     *
     * ClusterStatement cant be moved.
     *
     * All Tags, attached to the statement will be detached and the relation to the statement will be lost.
     *
     * @return Statement|false
     *
     * @throws CopyException
     * @throws InvalidDataException
     * @throws MessageBagException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     */
    public function copyStatementToProcedure(Statement $statementToCopy, Procedure $targetProcedure)
    {
        // In case of copy statement to current procedure, simply using already existing "copy statement" logic
        if ($statementToCopy->getProcedureId() === $targetProcedure->getId()) {
            return $this->statementCopier->copyStatementObjectWithinProcedure($statementToCopy);
        }

        // isCopyStatementToProcedureAllowed will create messages on its own:
        if ($this->statementCopier->isCopyStatementToProcedureAllowed($statementToCopy, $targetProcedure, true, true)) {
            if ($statementToCopy->isClusterStatement()) {
                return $this->statementClusterService->copyClusterToProcedure($statementToCopy, $targetProcedure);
            }

            return $this->statementCopier->copyStatementToProcedure($statementToCopy, $targetProcedure);
        }

        return false;
    }

    /**
     * @param string $internId
     * @param string $procedureId
     *
     * @return Statement|object|null
     */
    public function getStatementByInternIdAndProcedureId($internId, $procedureId)
    {
        $statementRepository = $this->entityManager
            ->getRepository(Statement::class);

        return $statementRepository->findOneBy(['internId' => $internId, 'procedure' => $procedureId]);
    }

    /**
     * @return QueryFragment
     */
    public function getEsQueryFragment()
    {
        return $this->esQueryFragment;
    }

    protected function getMunicipalityService(): MunicipalityService
    {
        return $this->municipalityService;
    }

    public function isVoteStkReadOnly(Statement $statement, string $userId): bool
    {
        $readOnly = false;
        // If Feature to lock and claim statements for editing is enabled, do the checks
        if ($this->permissions->hasPermission('feature_statement_assignment')) {
            $assignee = $this->statementService->getAssigneeOfStatement($statement);
            $assigned = null !== $assignee;
            $assignedToCurrentUser = null === $assignee ? false : $assignee->getId() === $userId;
            $isCopyOfOriginal = !$statement->isOriginal();
            $editable = ($assigned && $assignedToCurrentUser && $isCopyOfOriginal);
            $readOnly = false === $editable;
        }

        // do not overwrite existing readOnly if true
        if (false === $readOnly && $this->permissions->hasPermission('feature_statement_move_to_procedure')) {
            $statementObject = $this->getStatement($statement);
            $readOnly = $statementObject->isPlaceholder();
        }

        return $readOnly;
    }

    /**
     * The received list of Statements can mix single and grouped Statements. This method returns a list with the single Statements
     * plus those on the groups.
     *
     * @param Statement[] $statements
     *
     * @return Statement[]
     */
    public function getSingleStatements(array $statements): array
    {
        $singleStatements = [];
        $groupedStatements = [];
        foreach ($statements as $statement) {
            if ($statement->isClusterStatement()) {
                $groupedStatements[] = $statement->getCluster()->toArray();
            } else {
                $singleStatements[] = $statement;
            }
        }

        return array_merge($singleStatements, ...$groupedStatements);
    }

    /**
     * Checks whether the Statement $stmtNeedle exists in $statements.
     *
     * @param Statement[] $statements
     */
    private function isStatementInArray(array $statements, Statement $stmtNeedle): bool
    {
        foreach ($statements as $statement) {
            if ($statement->getId() === $stmtNeedle->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Given a list of Statements checks if it they fulfill all necessary conditions to be grouped
     * in a cluster. If there is some placeholder Statement it just gets excluded.
     *
     * @param Statement[] $statements
     *
     * @throws MessageBagException
     * @throws InvalidArgumentException
     * @throws NotAllStatementsGroupableException
     * @throws Exception
     */
    public function assertAllStatementsGroupable(string $procedureId, array $statements)
    {
        $statements = $this->excludePlaceholderStatements($statements);

        if (0 === count($statements)) {
            throw new InvalidArgumentException('Create statement failed: No items in the given clusterStatementArray');
        }

        if (!$this->validateStatementsProcedure($procedureId, $statements)) {
            throw NotAllStatementsGroupableException::create();
        }

        foreach ($statements as $statement) {
            if (!$this->hasValidStatementAssignments($statement)) {
                throw NotAllStatementsGroupableException::createFromStatementId($statement->getId());
            }
        }

        if (!$this->areStatementsAndFragmentsClaimedByUser($statements)
            && $this->permissions->hasPermission('feature_statement_assignment')) {
            throw NotAllStatementsGroupableException::createForUnclaimed();
        }
    }

    /**
     * Returns the received list of statements without the Placeholder Statements that might include.
     *
     * @param Statement[] $statements
     *
     * @return Statement[]
     *
     * @throws MessageBagException
     */
    public function excludePlaceholderStatements(array $statements): array
    {
        /** @var Statement $statement */
        foreach ($statements as $key => $statement) {
            if ($statement->isPlaceholder()) {
                unset($statements[$key]);
                $this->getLogger()->warning('On create statement cluster: removed Statement '.$statement->getId().' because it is a placeholder statement.');
                $this->getMessageBag()->add('warning',
                    'warning.statement.cluster.removed.placeholder',
                    ['%externId' => $statement->getExternId()]);
            }
        }

        return $statements;
    }

    /**
     * Given a list of statements ids, returns the array with the Statement objects.
     * If any of the ids is not a statement id, throws an InvalidArgumentException.
     *
     * @param string[] $statementIds
     *
     * @return Statement[]
     *
     * @throws InvalidArgumentException
     */
    public function getStatementsByIds(array $statementIds): array
    {
        $statements = $this->statementService->getStatementsByIds($statementIds);
        if (count($statements) !== count($statementIds)) {
            throw new InvalidArgumentException('Not all statement ids '.Json::encode($statementIds).' could be resolved');
        }

        return $statements;
    }

    /**
     * If all clusters in statements are resolvable, they get resolved, otherwise throws an InvalidArgumentException.
     *
     * @param Statement[] $statements - Array of Statements. They can be both single or group statements. Single statements will be ignored.
     *
     * @throws MessageBagException
     */
    public function resolveClusters(array $statements)
    {
        $this->validateClustersResolvable($statements);
        foreach ($statements as $statement) {
            if ($statement->isClusterStatement()) {
                $this->resolveCluster($statement);
            }
        }
    }

    /**
     * Validates that all clusters in $statements are resolvable. Otehrwise throws an InvalidArgumentException.
     *
     * @param Statement[] $statements - Array of Statements. They can be both single or group statements. Single statements will be ignored.
     *
     * @throws MessageBagException
     */
    private function validateClustersResolvable(array $statements)
    {
        $statementService = $this->statementService;

        foreach ($statements as $statement) {
            if ($statement->isClusterStatement() && $statementService->isStatementObjectLockedByAssignment($statement)) {
                $statementService->addMessageLockedByAssignment($statement);
                throw new InvalidArgumentException('There are clusters which are not resolvable.');
            }
        }
    }

    /**
     * Returns an array with the ids of the given statements.
     */
    public function getStatementIds(array $statements): array
    {
        return array_map(fn (Statement $statement) => $statement->getId(), $statements);
    }

    /**
     * Creates an array with all cluster and single statements in $statements plus $clusterStatement (not its children).
     *
     * @param Statement[] $statements
     * @param Statement   $clusterStatement
     *
     * @return Statement[]
     */
    private function mergeAllStatements($statements, $clusterStatement = null): array
    {
        $allStatements = [];
        if (null !== $clusterStatement) {
            $allStatements = [$clusterStatement];
        }
        $childrenStatements = [];
        /** @var Statement $statement */
        foreach ($statements as $statement) {
            $allStatements[] = $statement;
            if ($statement->isClusterStatement()) {
                $childrenStatements[] = $statement->getCluster()->toArray();
            }
        }

        return array_merge($allStatements, ...$childrenStatements);
    }

    /**
     * Used to create a new cluster of Statements.
     *
     * The new cluster will be based on an existing Statement ($headStatementId) and it will group the given statements.
     * and only a few selected attributes of one of the statementIdsToCluster.
     *
     * If claiming of Statements are enabled, only the claimed of the giving statements to cluster will actually added
     * to the created cluster.
     *
     * @param string[] $statementIds
     *
     * @return bool|Statement
     *
     * @throws StatementNameTooLongException
     * @throws NotAllStatementsGroupableException
     * @throws Exception
     */
    public function createStatementCluster(string $procedureId, array $statementIds, string $headStatementId, ?string $headStatementName = null)
    {
        if (!in_array($headStatementId, $statementIds)) {
            throw new InvalidArgumentException('Create statement cluster canceled: RepresentativeStatement have to be member of cluster');
        }

        try {
            /** @var Statement $headStatement, $clusterStatement, $newHeadOfCluster */
            $headStatement = $this->getStatement($headStatementId);
            if ($headStatement->isClusterStatement()) {
                throw new Exception('A Cluster cannot be used as HeadStatement');
            }

            $statements = $this->getStatementsByIds($statementIds);
            $singleStatementsIds = $this->getStatementIds($this->getSingleStatements($statements));
            $allStatements = $this->mergeAllStatements($statements);

            $this->assertAllStatementsGroupable($procedureId, $allStatements);

            $this->resolveClusters($statements);
            $clusterStatement = $this->generateHeadStatement($headStatement, $headStatementName);
            $newClusterStatement = $this->statementClusterService->newStatementCluster($clusterStatement, $singleStatementsIds);
            // Because creating a NEW cluster, there are will be never statements given here.
            // Therefore empty array can be given as $preUpdateValues.

            $this->entityContentChangeService->convertArraysAndAddVersion(
                $newClusterStatement, [], 'cluster');

            // copy fragments in the end, to avoid fragments get copied in newStatementCluster()
            if (0 < $headStatement->getFragments()->count()) {
                $this->statementFragmentService->copyStatementFragments(
                    $headStatement->getFragments(),
                    $newClusterStatement
                );

                $this->getLogger()->info('Cluster Fragments copied');
            }

            $this->getMessageBag()->addObject(
                LinkMessageSerializable::createLinkMessage(
                    'confirm',
                    'confirm.statement.cluster.created',
                    ['clusterId'   => $newClusterStatement->getExternId()],
                    'DemosPlan_cluster_view',
                    ['procedureId' => $newClusterStatement->getProcedureId(), 'statement' => $newClusterStatement->getId()],
                    $newClusterStatement->getExternId()
                )
            );

            return $newClusterStatement;
        } catch (Exception $e) {
            if (isset($newClusterStatement) && $newClusterStatement instanceof Statement) {
                $this->statementDeleter->deleteStatementObject($newClusterStatement);
            }
            throw $e;
        }
    }

    /**
     * Updates an existing cluster ($clusterStatementId) by adding the recieved statements ($statementIds).
     *
     * @return bool|Statement
     *
     * @throws MessageBagException
     * @throws NotAllStatementsGroupableException
     */
    public function updateStatementCluster(string $procedureId, array $statementIds, string $clusterStatementId)
    {
        if (in_array($clusterStatementId, $statementIds)) {
            throw new Exception('A group can\'t be used to update itself (id:'.$clusterStatementId.' )');
        }
        /** @var Statement $clusterStatement */
        $clusterStatement = $this->getStatement($clusterStatementId);
        $statements = $this->getStatementsByIds($statementIds);
        $singleStatements = $this->getSingleStatements($statements);
        $allStatements = $this->mergeAllStatements($statements, $clusterStatement);

        $this->assertAllStatementsGroupable($procedureId, $allStatements);

        $this->resolveClusters($statements);
        $this->addStatementsToCluster($clusterStatement, $singleStatements);

        return $clusterStatement;
    }

    /**
     * Returns true if all Statements and their Fragments are claimed by current user and false otherwise.
     *
     * @param Statement[] $statements
     *
     * @throws MessageBagException
     */
    protected function areStatementsAndFragmentsClaimedByUser(array $statements): bool
    {
        $areAllElementsClaimedByUser = true;
        // get all statements, assigned to the current this->user
        if ($this->permissions->hasPermission('feature_statement_assignment')) {
            $userStatements = $this->statementService->getAssignedStatements($this->currentUser->getUser());
            $statementsNotClaimedByUser = [];
            $stmtFragmentsNotClaimedByUser = [];
            foreach ($statements as $statement) {
                if (!$this->isStatementInArray($userStatements, $statement)) {
                    $statementsNotClaimedByUser[] = $statement->getExternId();
                }
                if (!$this->areAllFragmentsClaimedByCurrentUser($statement->getId())) {
                    $stmtFragmentsNotClaimedByUser[] = $statement->getExternId();
                }
            }

            if (0 < count($statementsNotClaimedByUser)) {
                $this->getMessageBag()->add(
                    'warning', 'statement.cluster.not.assigned',
                    ['ids' => implode(', ', $statementsNotClaimedByUser)]
                );

                $this->getLogger()->info('Folowing statements are not claimed by current user: '.implode(', ', $statementsNotClaimedByUser));
                $areAllElementsClaimedByUser = false;
            }
            if (0 < count($stmtFragmentsNotClaimedByUser)) {
                $this->getMessageBag()->add(
                    'warning', 'statement.cluster.fragments.not.claimed.by.current.user',
                    ['ids' => implode(', ', $stmtFragmentsNotClaimedByUser)]
                );
                $this->getLogger()->info('Folowing fragments are not claimed by current user: '.implode(', ', $stmtFragmentsNotClaimedByUser));
                $areAllElementsClaimedByUser = false;
            }
        }

        return $areAllElementsClaimedByUser;
    }

    /**
     * @deprecated Used in Test only. Find better way
     *
     * @param FileService $fileService
     */
    public function setFileService($fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Generiere einen Eintrag für die notwendigen Felder.
     *
     * @param string $translatorLabel
     */
    public function createMandatoryErrorMessage($translatorLabel): array
    {
        return [
            'type'    => 'error',
            'message' => $this->translator->trans(
                'error.mandatoryfield',
                [
                    'name' => $translatorLabel,
                ]
            ),
        ];
    }

    /**
     * @param string|null $filterHash
     *
     * @throws AsynchronousStateException
     * @throws ErroneousDoctrineResult
     */
    public function getResultsByFilterSetHash($filterHash, string $procedureId): array
    {
        return $this->statementService->getResultsByFilterSetHash($filterHash, $procedureId);
    }

    /**
     * @param array<int,class-string> $entityClassesToInclude the classes for which entites should be returned
     */
    public function getStatementsAndTheirFragmentsInOneFlatList(array $statements, array $entityClassesToInclude): array
    {
        return $this->statementService->getStatementsAndTheirFragmentsInOneFlatList($statements, $entityClassesToInclude);
    }

    /**
     * @throws QueryException
     */
    public function getSegmentableStatement(string $procedureId, User $user): ?Statement
    {
        return $this->statementService->getSegmentableStatement($procedureId, $user);
    }

    /**
     * @throws QueryException
     */
    public function getSegmentableStatementsCount(string $procedureId, User $user): int
    {
        return $this->statementService->getSegmentableStatementsCount($procedureId, $user);
    }

    /**
     * Returns the File entity, the Statement was created from if any, otherwise returns null.
     *
     * If the Statement was created using the Pdf Importer Workflow, the original File will
     * be taken from the AnnnotatedStatementPdf.
     *
     * If the Statement was created manually it may have a specific attachment for the
     * original file.
     */
    public function getOriginalFile(Statement $statement): ?File
    {
        /** @var GetOriginalFileFromAnnotatedStatementEvent $event * */
        $event = $this->eventDispatcher->dispatch(new GetOriginalFileFromAnnotatedStatementEvent($statement));
        if (null !== $event->getFile()) {
            return $event->getFile();
        }

        return $statement->getOriginalFile();
    }

    /**
     * @return array<int, ?File>
     */
    public function getAdditionalFiles(Statement $statement): array
    {
        $additionalFiles = [];
        foreach ($statement->getFiles() as $file) {
            try {
                $additionalFiles[] = $this->fileService->getFileInfoFromFileString($file);
            } catch (Exception $e) {
                $this->getLogger()->error('Could not find file based on file string: ', [$e]);
            }
        }

        return $additionalFiles;
    }
}
