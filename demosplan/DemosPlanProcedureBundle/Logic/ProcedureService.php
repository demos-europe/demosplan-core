<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use function array_key_exists;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\ProcedureServiceInterface;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Location;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\InstitutionMail;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSubscription;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Event\Procedure\NewProcedureAdditionalDataEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PostNewProcedureCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PostProcedureDeletedEvent;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\CriticalConcernException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Export\EntityPreparator;
use demosplan\DemosPlanCoreBundle\Logic\Export\FieldConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\LocationService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\Plis;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\EntityContentChangeRepository;
use demosplan\DemosPlanCoreBundle\Repository\NewsRepository;
use demosplan\DemosPlanCoreBundle\Repository\SettingRepository;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryProcedure;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use demosplan\DemosPlanDocumentBundle\Logic\ElementsService;
use demosplan\DemosPlanDocumentBundle\Repository\ElementsRepository;
use demosplan\DemosPlanDocumentBundle\Repository\ParagraphRepository;
use demosplan\DemosPlanDocumentBundle\Repository\SingleDocumentRepository;
use demosplan\DemosPlanMapBundle\Repository\GisLayerCategoryRepository;
use demosplan\DemosPlanProcedureBundle\Form\AbstractProcedureFormType;
use demosplan\DemosPlanProcedureBundle\Repository\BoilerplateCategoryRepository;
use demosplan\DemosPlanProcedureBundle\Repository\BoilerplateGroupRepository;
use demosplan\DemosPlanProcedureBundle\Repository\BoilerplateRepository;
use demosplan\DemosPlanProcedureBundle\Repository\InstitutionMailRepository;
use demosplan\DemosPlanProcedureBundle\Repository\NotificationReceiverRepository;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureElasticsearchRepository;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureSubscriptionRepository;
use demosplan\DemosPlanProcedureBundle\ValueObject\BoilerplateCategoryVO;
use demosplan\DemosPlanProcedureBundle\ValueObject\BoilerplateGroupVO;
use demosplan\DemosPlanProcedureBundle\ValueObject\BoilerplateVO;
use demosplan\DemosPlanProcedureBundle\ValueObject\ProcedureFormData;
use demosplan\DemosPlanStatementBundle\Exception\InvalidDataException;
use demosplan\DemosPlanStatementBundle\Repository\StatementRepository;
use demosplan\DemosPlanStatementBundle\Repository\TagTopicRepository;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use demosplan\DemosPlanUserBundle\Logic\OrgaService;
use demosplan\DemosPlanUserBundle\Logic\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use EDT\Querying\Contracts\SortMethodInterface;
use Exception;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use ReflectionException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Tightenco\Collect\Support\Collection;
use TypeError;

class ProcedureService extends CoreService implements ProcedureServiceInterface
{
    /**
     * @var ObjectPersisterInterface
     */
    protected $esProcedurePersister;

    /**
     * @var LocationService
     */
    protected $locationService;

    /**
     * @var ElementsService
     */
    protected $elementsService;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var UserService
     */
    protected $userService;

    /** @var ContentService */
    protected $contentService;

    /** @var Permissions */
    protected $permissions;

    /** @var EntityContentChangeService */
    protected $entityContentChangeService;

    /**
     * @var string
     */
    private $environment;

    /**
     * @var OrgaService
     */
    private $orgaService;

    /** @var CustomerService */
    private $customerService;

    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var ProcedureElasticsearchRepository
     */
    private $procedureElasticsearchRepository;

    /**
     * @var PrepareReportFromProcedureService
     */
    private $prepareReportFromProcedureService;

    /**
     * @var ProcedureTypeService
     */
    private $procedureTypeService;
    /**
     * @var PhasePermissionsetLoader
     */
    private $phasePermissionsetLoader;
    /**
     * @var ConditionFactoryInterface
     */
    private $conditionFactory;
    /**
     * @var SortMethodFactoryInterface
     */
    private $sortMethodFactory;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var EntityPreparator
     */
    protected $entityPreparator;

    /**
     * @var FieldConfigurator
     */
    protected $fieldConfigurator;

    /**
     * @var MasterTemplateService
     */
    private $masterTemplateService;

    /**
     * @var ProcedureAccessEvaluator
     */
    private $procedureAccessEvaluator;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var ProcedureRepository
     */
    private $procedureRepository;

    /**
     * @var BoilerplateRepository
     */
    private $boilerplateRepository;

    /**
     * @var BoilerplateGroupRepository
     */
    private $boilerplateGroupRepository;

    /**
     * @var InstitutionMailRepository
     */
    private $institutionMailRepository;

    /**
     * @var ProcedureSubscriptionRepository
     */
    private $procedureSubscriptionRepository;

    /**
     * @var TagTopicRepository
     */
    private $tagTopicRepository;

    /**
     * @var GisLayerCategoryRepository
     */
    private $gisLayerCategoryRepository;

    /**
     * @var NewsRepository
     */
    private $newsRepository;

    /**
     * @var ElementsRepository
     */
    private $elementsRepository;

    /**
     * @var NotificationReceiverRepository
     */
    private $notificationReceiverRepository;

    /**
     * @var SingleDocumentRepository
     */
    private $singleDocumentRepository;

    /**
     * @var ParagraphRepository
     */
    private $paragraphRepository;

    /**
     * @var SettingRepository
     */
    private $settingRepository;

    /**
     * @var BoilerplateCategoryRepository
     */
    private $boilerplateCategoryRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProcedureToLegacyConverter
     */
    private $procedureToLegacyConverter;

    /**
     * @var PlaceRepository
     */
    private $placeRepository;
    /**
     * @var EntityHelper
     */
    private $entityHelper;
    /**
     * @var DateHelper
     */
    private $dateHelper;
    /**
     * @var EntityContentChangeRepository
     */
    private $entityContentChangeRepository;
    /**
     * @var StatementRepository
     */
    private $statementRepository;

    /**
     * @var Plis
     */
    private $plis;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        BoilerplateCategoryRepository $boilerplateCategoryRepository,
        BoilerplateGroupRepository $boilerplateGroupRepository,
        BoilerplateRepository $boilerplateRepository,
        ContentService $contentService,
        CurrentUserInterface $currentUser,
        CustomerService $customerService,
        DateHelper $dateHelper,
        DqlConditionFactory $conditionFactory,
        ElementsRepository $elementsRepository,
        ElementsService $elementsService,
        EntityContentChangeRepository $entityContentChangeRepository,
        EntityContentChangeService $entityContentChangeService,
        EntityFetcher $entityFetcher,
        EntityHelper $entityHelper,
        EntityManagerInterface $entityManager,
        EntityPreparator $entityPreparator,
        EventDispatcherInterface $eventDispatcher,
        FieldConfigurator $fieldConfigurator,
        FileService $fileService,
        GisLayerCategoryRepository $gisLayerCategoryRepository,
        GlobalConfigInterface $globalConfig,
        LocationService $locationService,
        MasterTemplateService $masterTemplate,
        MessageBagInterface $messageBag,
        NewsRepository $newsRepository,
        NotificationReceiverRepository $notificationReceiverRepository,
        ObjectPersisterInterface $esProcedurePersister,
        OrgaService $orgaService,
        ParagraphRepository $paragraphRepository,
        Permissions $permissions,
        PhasePermissionsetLoader $phasePermissionsetLoader,
        PlaceRepository $placeRepository,
        Plis $plis,
        PrepareReportFromProcedureService $prepareReportFromProcedureService,
        ProcedureAccessEvaluator $procedureAccessEvaluator,
        ProcedureElasticsearchRepository $procedureElasticsearchRepository,
        ProcedureRepository $procedureRepository,
        ProcedureSubscriptionRepository $procedureSubscriptionRepository,
        ProcedureToLegacyConverter $procedureToLegacyConverter,
        ProcedureTypeService $procedureTypeService,
        SettingRepository $settingRepository,
        SingleDocumentRepository $singleDocumentRepository,
        SortMethodFactory $sortMethodFactory,
        StatementRepository $statementRepository,
        TagTopicRepository $tagTopicRepository,
        InstitutionMailRepository $institutionMailRepository,
        TranslatorInterface $translator,
        UserService $userService,
        ValidatorInterface $validator,
        string $environment
    ) {
        $this->boilerplateCategoryRepository = $boilerplateCategoryRepository;
        $this->boilerplateGroupRepository = $boilerplateGroupRepository;
        $this->boilerplateRepository = $boilerplateRepository;
        $this->conditionFactory = $conditionFactory;
        $this->contentService = $contentService;
        $this->currentUser = $currentUser;
        $this->customerService = $customerService;
        $this->dateHelper = $dateHelper;
        $this->elementsRepository = $elementsRepository;
        $this->elementsService = $elementsService;
        $this->entityContentChangeRepository = $entityContentChangeRepository;
        $this->entityContentChangeService = $entityContentChangeService;
        $this->entityFetcher = $entityFetcher;
        $this->entityHelper = $entityHelper;
        $this->entityManager = $entityManager;
        $this->entityPreparator = $entityPreparator;
        $this->environment = $environment;
        $this->esProcedurePersister = $esProcedurePersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->fieldConfigurator = $fieldConfigurator;
        $this->fileService = $fileService;
        $this->gisLayerCategoryRepository = $gisLayerCategoryRepository;
        $this->locationService = $locationService;
        $this->masterTemplateService = $masterTemplate;
        $this->newsRepository = $newsRepository;
        $this->notificationReceiverRepository = $notificationReceiverRepository;
        $this->orgaService = $orgaService;
        $this->paragraphRepository = $paragraphRepository;
        $this->permissions = $permissions;
        $this->phasePermissionsetLoader = $phasePermissionsetLoader;
        $this->placeRepository = $placeRepository;
        $this->prepareReportFromProcedureService = $prepareReportFromProcedureService;
        $this->procedureAccessEvaluator = $procedureAccessEvaluator;
        $this->procedureElasticsearchRepository = $procedureElasticsearchRepository;
        $this->procedureRepository = $procedureRepository;
        $this->procedureSubscriptionRepository = $procedureSubscriptionRepository;
        $this->procedureToLegacyConverter = $procedureToLegacyConverter;
        $this->procedureTypeService = $procedureTypeService;
        $this->settingRepository = $settingRepository;
        $this->singleDocumentRepository = $singleDocumentRepository;
        $this->sortMethodFactory = $sortMethodFactory;
        $this->tagTopicRepository = $tagTopicRepository;
        $this->institutionMailRepository = $institutionMailRepository;
        $this->translator = $translator;
        $this->userService = $userService;
        $this->validator = $validator;
        $this->statementRepository = $statementRepository;

        $this->plis = $plis;
        $this->messageBag = $messageBag;
        $this->globalConfig = $globalConfig;
    }

    public function getPermissions(): Permissions
    {
        return $this->permissions;
    }

    public function getRecommendationProcedureIds(User $user, string $procedureId)
    {
        $proceduresToCheck = $this->getProcedure($procedureId)
            ->getSettings()
            ->getAllowedSegmentAccessProcedures()
            ->getValues();

        // remove procedures for which no segments should be shown
        $allowedProcedureIds = $this->procedureAccessEvaluator
            ->filterNonOwnedProcedureIds($user, ...$proceduresToCheck);
        $allowedProcedureIds[] = $procedureId;

        return $allowedProcedureIds;
    }

    /**
     * Switch the current external/internal phase of all procedures, which are "prepared" to switch phase today.
     *
     * @retrun array<int,int>
     *
     * @throws Exception
     */
    public function switchPhasesOfProceduresUntilNow(): array
    {
        $proceduresToSwitch = $this->getProceduresToSwitchUntilNow();

        $externalProcedureCounter = 0;
        $internalProcedureCounter = 0;
        $entitiesToPersist = [];

        foreach ($proceduresToSwitch as $procedure) {
            // determine user; needs to be done before the phase change as it is lost afterwards
            $changeUserInternal = $procedure->getSettings()->getDesignatedPhaseChangeUser();
            $changeUserExternal = $procedure->getSettings()->getDesignatedPublicPhaseChangeUser();
            $changeUserInternalId = $this->getUserIdOrNull($changeUserInternal);
            $changeUserExternalId = $this->getUserIdOrNull($changeUserExternal);
            $equalUser = null !== $changeUserExternalId
                && null !== $changeUserInternalId
                && $changeUserInternalId === $changeUserExternalId;

            // execute phase change
            $originalProcedure = $this->cloneProcedure($procedure);
            $externalPhaseSwitched = $this->switchToDesignatedPublicPhase($procedure);
            $procedureAfterExternalChange = $this->cloneProcedure($procedure);
            $internalPhaseSwitched = $this->switchToDesignatedPhase($procedure);
            $procedureAfterExternalAndInternalChange = $this->cloneProcedure($procedure);

            // create either a single report entry if the same user did both changes or two separate
            // changes for separate users
            if ($equalUser) {
                if ($internalPhaseSwitched || $externalPhaseSwitched) {
                    // at this point $changeUserExternal is equal to $changeUserInternal and never null
                    $entitiesToPersist[] = $this->prepareReportFromProcedureService->createPhaseChangeReportEntryIfChangesOccurred(
                        $originalProcedure,
                        $procedureAfterExternalAndInternalChange,
                        $changeUserExternal,
                        true
                    );
                }
            } else {
                if ($externalPhaseSwitched) {
                    if (null === $changeUserExternal) {
                        $this->logger->warning('Could not determine user for external phase change report, maybe they was deleted after they configured the designated switch');
                    }
                    $entitiesToPersist[] = $this->prepareReportFromProcedureService->createPhaseChangeReportEntryIfChangesOccurred(
                        $originalProcedure,
                        $procedureAfterExternalChange,
                        $changeUserExternal,
                        true
                    );
                }

                if ($internalPhaseSwitched) {
                    if (null === $changeUserInternal) {
                        $this->logger->warning('Could not determine user for internal phase change report, maybe they was deleted after they configured the designated switch');
                    }
                    $entitiesToPersist[] = $this->prepareReportFromProcedureService->createPhaseChangeReportEntryIfChangesOccurred(
                        $procedureAfterExternalChange,
                        $procedureAfterExternalAndInternalChange,
                        $changeUserInternal,
                        true
                    );
                }
            }

            if ($externalPhaseSwitched) {
                ++$externalProcedureCounter;
            }

            if ($internalPhaseSwitched) {
                ++$internalProcedureCounter;
            }

            if ($internalPhaseSwitched || $externalPhaseSwitched) {
                $entitiesToPersist[] = $procedure;
            }
        }

        $entitiesToPersist = array_filter(
            $entitiesToPersist,
            static function (?object $entityToPersist): bool {
                return null !== $entityToPersist;
            }
        );

        $this->procedureRepository->updateObjects($entitiesToPersist);

        return [$internalProcedureCounter, $externalProcedureCounter];
    }

    /**
     * @param array<int, string> $procedureIds
     *
     * @return array<int, Procedure>
     */
    public function getProceduresById(array $procedureIds)
    {
        return $this->procedureRepository->findBy(['id' => $procedureIds]);
    }

    /**
     * Processes the given `$form` and fills its information into the given `$inData` array.
     *
     * @param array<string, mixed> $inData
     *
     * @return array<string, mixed> the given `$inData` array, enriched with information from the given `$form`
     *
     * @throws CustomerNotFoundException
     * @throws UserNotFoundException
     */
    public function fillInData(array $inData, FormInterface $form): array
    {
        $inData['orgaId'] = $this->currentUser->getUser()->getOrganisationId();
        $inData['orgaName'] = $this->currentUser->getUser()->getOrganisationNameLegal();
        $inData['r_copymaster'] = $this->calculateCopyMasterId($inData['r_copymaster'] ?? null);

        /** @var ProcedureFormData $procedureFormData */
        $procedureFormData = $form->getData();
        // will be an empty string and an empty array in case of a non-blueprint submit for agencyExtraEmailAddresses
        // agencyMainEmailAddress will be an empty string in case of blueprint submits
        $inData[AbstractProcedureFormType::AGENCY_MAIN_EMAIL_ADDRESS] = $procedureFormData->getAgencyMainEmailAddressFullString();
        $inData[AbstractProcedureFormType::AGENCY_EXTRA_EMAIL_ADDRESSES] = $procedureFormData->getAgencyExtraEmailAddressesFullStrings();
        $inData[AbstractProcedureFormType::ALLOWED_SEGMENT_ACCESS_PROCEDURE_IDS] = $procedureFormData->getAllowedSegmentAccessProcedureIds();

        // T15664: set current customer as related customer of procedure to flag this new procedure as customer master blueprint
        if (\array_key_exists('r_customerMasterBlueprint', $inData) && 'on' === $inData['r_customerMasterBlueprint']) {
            $inData['customer'] = $this->customerService->getCurrentCustomer();
        }

        if ($this->currentUser->hasAllPermissions('feature_use_plis', 'feature_use_xplanbox')) {
            // bei nonJS ist r_name nicht vorhanden
            $hasName = \array_key_exists('r_name', $inData) && 0 < \strlen($inData['r_name']);

            // set publicProcedureParticipationEnabled flag to false
            $inData['r_publicParticipationPublicationEnabled'] = 0;

            try {
                if (!$hasName) {
                    $inData = $this->checkProcedureDataNoJS($inData);
                }
            } catch (Exception $e) {
                // Probleme beim LGV, Verfahren sollen auch ohne Startkartenausschnitt angelegt werden können
            }
        }

        return $inData;
    }

    /**
     * May add the `plisProcedures` field to the given array depending on the current permission.
     *
     * @param array<string, mixed> $templateVars
     *
     * @return array<string, mixed> the given array, potentially enriched with `plisProcedures`
     *
     * @throws UserNotFoundException
     */
    public function setPlisInTemplateVars(array $templateVars): array
    {
        if ($this->currentUser->hasPermission('feature_use_plis')) {
            // Frage die LGV PLIS-Datenbank ab, was für Verfahren angelegt sind
            try {
                $templateVars['plisProcedures'] = $this->plis->getLgvPlisProcedureList();
            } catch (Exception $e) {
                $templateVars['plisProcedures'] = [];
            }
        }

        return $templateVars;
    }

    /**
     * Überprüfe, ob die notwendigen Infos Planungsanlass und Startkartenausschnitt gesetzt sind
     * und rufe sie ggf ab.
     *
     * @param array<string, mixed> $inData
     *
     * @throws Exception
     */
    protected function checkProcedureDataNoJS(array $inData): array
    {
        if (\array_key_exists('r_name', $inData) && '' === $inData['r_name']) {
            $planungsanlass = $this->plis->getLgvPlisPlanningcause(
                $inData['r_plisId']
            );
            if (isset($planungsanlass['planungsanlass'])) {
                $inData['r_externalDesc'] = $planungsanlass['planungsanlass'];
            }
            $procedureList = $this->plis->getLgvPlisProcedureList();
            $inData['r_name'] = $this->getPlisProcedureName($procedureList, $inData);
        }

        return $inData;
    }

    /**
     * @param array<int, array{procedureName: string, uuid: string}> $procedureList
     * @param array<string, mixed>                                   $inData
     *
     * @throws MessageBagException
     */
    protected function getPlisProcedureName(array $procedureList, array $inData): string
    {
        foreach ($procedureList as $procedure) {
            if ($procedure['uuid'] == $inData['r_plisId']) {
                return $procedure['procedureName'];
            }
        }

        $this->messageBag->add('error', 'error.plis.no.procedure');

        throw new Exception('Kein Verfahren zur PlisId '.$inData['r_plisId'].' gefunden');
    }

    protected function getElementsService(): ElementsService
    {
        return $this->elementsService;
    }

    protected function getFileService(): FileService
    {
        return $this->fileService;
    }

    /**
     * @deprecated Use UserService directly instead
     */
    public function getPublicUserService(): UserService
    {
        return $this->userService;
    }

    /**
     * @param bool $asArray
     *
     * @return Procedure[]|array[]
     *
     * @throws Exception
     */
    public function getProceduresWithEndedParticipation(array $writePhaseKeys, bool $internal = true, $asArray = false): array
    {
        return $this->procedureRepository->getProceduresWithEndedParticipation($writePhaseKeys, $internal, $asArray);
    }

    public function getAmountOfProcedures(): int
    {
        return $this->procedureRepository->getNumberOfProcedures();
    }

    protected function getOrgaService(): OrgaService
    {
        return $this->orgaService;
    }

    protected function getEntityContentChangeService(): EntityContentChangeService
    {
        return $this->entityContentChangeService;
    }

    /**
     * Ruft alle Verfahren ab
     * Funktion benötigt die Rolle Verfahrenssupport (RTSUPP).
     *
     * @return array
     *
     * @throws Exception
     */
    public function getProcedureFullList(string $search = '', bool $toLegacy = true)
    {
        try {
            $procedures = $this->procedureRepository->getFullList(false);

            if (!$toLegacy) {
                return $procedures;
            }
            $procedureList = [];
            foreach ($procedures as $procedure) {
                $procedureList[$procedure->getId()] = $this->procedureToLegacyConverter->convertToLegacy($procedure);
            }

            return $this->procedureToLegacyConverter->toLegacyResult($procedureList, $search)->toArray();
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der ProcedureFullList: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $slug
     *
     * @throws NonUniqueResultException
     */
    public function getProcedureBySlug($slug): ?Procedure
    {
        return $this->procedureRepository->getProcedureBySlug($slug);
    }

    /**
     * Get deleted Procedures.
     *
     * @param int $limit
     *
     * @return Procedure[]|null
     *
     * @throws Exception
     */
    public function getDeletedProcedures($limit = 100000000)
    {
        try {
            return $this->procedureRepository->findBy(['deleted' => true], null, $limit);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der getProcedureDeleted: ', [$e]);
            throw $e;
        }
    }

    /**
     * Ruft alle Verfahren ab (AdminListe)
     * Returns all accessible procedures of given user.
     * Will never return deleted procedures.
     *
     * Alle Verfahren einer Firma
     * inklusive eingetragener Planungsbüros
     *
     * @param array  $filters
     * @param string $search
     * @param array  $sort
     * @param User   $user            will be used to get the organisation ID, the user ID and the role name
     * @param bool   $template        should procedure templates be included in results
     * @param bool   $toLegacy        determines if return value will be array[] or Procedure[]
     * @param bool   $excludeArchived exclude internal and external phase closed
     *
     * @return array|Procedure[]
     *
     * @throws Exception
     *
     * @deprecated do not spread usage of this; see T21768
     */
    public function getProcedureAdminList($filters, $search, $sort = null, User $user, bool $template = false, $toLegacy = true, $excludeArchived = true)
    {
        try {
            $conditions = $this->convertFiltersToConditions($filters, $search, $user, $excludeArchived, $template);
            $sortMethods = $this->convertSortArrayToSortMethods($sort);

            $procedureList = $this->entityFetcher->listEntitiesUnrestricted(
                Procedure::class,
                $conditions,
                $sortMethods
            );

            if ($toLegacy) {
                $procedureList = \collect($procedureList)->map([$this->procedureToLegacyConverter, 'convertToLegacy'])->all();
            }

            return $toLegacy ? $this->procedureToLegacyConverter->toLegacyResult($procedureList, $search)->toArray() : $procedureList;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der ProcedureAdminList: ', [$e]);
            throw $e;
        }
    }

    /**
     * @return array<int, FunctionInterface<bool>>
     */
    public function getAdminProcedureConditions(bool $template, User $user): array
    {
        $conditions = [];
        $conditions[] = $this->conditionFactory->propertyHasValue(false, ['deleted']);

        $organisationId = $user->getOrganisationId();

        // Planning agencies ("Planungsbüro") get the list of procedures they are
        // authorized for (enabled via field_procedure_adjustments_planning_agency).
        $planningAgencyCondition = $this->conditionFactory->false();
        if ($user->hasRole(Role::PRIVATE_PLANNING_AGENCY)) {
            $planningAgencyCondition = $this->conditionFactory->propertyHasStringAsMember($organisationId, ['planningOffices']);
        }

        $orgaOwnsProcedureCondition = $this->conditionFactory->false();
        $authorizedUserCondition = $this->conditionFactory->true();
        // CUSTOMER_MASTER_USER is not able to see this whole list, but in
        // ProcedureAccessEvaluator::isOwningProcedure we also test for this role,
        // so it is included here just to be on-par with that method.
        if ($user->hasRole(Role::CUSTOMER_MASTER_USER)
            || $user->isHearingAuthority()
            || $user->isPlanningAgency()
        ) {
            // Planning agencies are allowed to see any procedure created by a user of their orga.
            $orgaOwnsProcedureCondition = $this->conditionFactory->propertyHasValue($organisationId, ['orga']);

            // If enabled via globalConfig, Planning agencies can be authorized user-wise.
            if ($this->globalConfig->hasProcedureUserRestrictedAccess()) {
                $authorizedUserCondition = $this->conditionFactory->propertyHasStringAsMember($user->getId(), ['authorizedUsers']);
            }
        }

        $conditions[] = $this->conditionFactory->anyConditionApplies(
            $planningAgencyCondition,
            $this->conditionFactory->allConditionsApply(
                $orgaOwnsProcedureCondition,
                $authorizedUserCondition
            )
        );

        $conditions[] = $this->conditionFactory->propertyHasValue($template, ['master']);

        return $conditions;
    }

    /**
     * Ruft ein einzelnes Verfahren auf.
     *
     * @param string $procedureId
     *
     * @return array|null {@link Procedure} converted to array
     *
     * @deprecated use {@link ProcedureService::getProcedure} instead
     */
    public function getSingleProcedure($procedureId): ?array
    {
        try {
            $procedure = $this->getProcedure($procedureId);

            return $this->procedureToLegacyConverter->convertToLegacy($procedure);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Procedures: ', [$e]);
        }

        return null;
    }

    /**
     * @throws ProcedureNotFoundException
     */
    public function getProcedureWithCertainty(string $procedureId): Procedure
    {
        $procedure = $this->getProcedure($procedureId);
        if (!$procedure instanceof Procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        return $procedure;
    }

    /**
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function getProcedure($procedureId): ?Procedure
    {
        try {
            /** @var Procedure|null $procedure */
            $procedure = $this->procedureRepository->get($procedureId);
            // set converted phase names for easier use in templates
            if ($procedure instanceof Procedure) {
                $procedure->setPhaseName(
                    $this->globalConfig->getPhaseNameWithPriorityInternal(
                        $procedure->getPhase()
                    )
                );
                $procedure->setPublicParticipationPhaseName(
                    $this->globalConfig->getPhaseNameWithPriorityExternal(
                        $procedure->getPublicParticipationPhase()
                    )
                );
            }

            return $procedure;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Procedures: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $procedureId
     *
     * @return array
     *
     * @throws Exception
     */
    public function getProcedureNames($procedureId)
    {
        try {
            return $this->procedureRepository->getNames($procedureId);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Procedures: ', [$e]);
            throw $e;
        }
    }

    /**
     * Current user can assign statements & datasets to authorized user. To do that, we need a list. This gets the list.
     *
     * @param string $procedureId
     * @param User   $user                            User is needed as s/he ony may see
     *                                                Members of same Organisation; if no user was given then the current user will be used
     * @param bool   $excludeUser                     exclude given user from list?
     * @param bool   $excludeProcedureAuthorizedUsers filter users who may not administer this Procedure
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getAuthorizedUsers(
        $procedureId,
        User $user = null,
        $excludeUser = false,
        $excludeProcedureAuthorizedUsers = true
    ): Collection {
        if (null === $user) {
            $user = $this->currentUser->getUser();
        }

        $procedure = $this->procedureRepository->get($procedureId);
        if (!$procedure instanceof Procedure) {
            return \collect();
        }

        $userOrga = $user->getOrga();
        if (!$userOrga instanceof Orga) {
            return \collect();
        }

        $usersOfOrganisation = $userOrga->getUsers();

        // remove current user, to avoid unselecting yourself:
        if ($excludeUser) {
            $usersOfOrganisation->forget($usersOfOrganisation->search($user));
        }
        // planning offices needs to get all Orga members that are planners
        if (\in_array($userOrga->getId(), $procedure->getPlanningOfficesIds(), true)) {
            return $usersOfOrganisation->filter(static function (User $user): bool {
                return $user->isPlanner();
            });
        }

        // T8901: filter users with false roles:
        $usersOfOrganisation = $usersOfOrganisation->filter(
            static function (User $user): bool {
                return $user->isPlanningAgency() || $user->isHearingAuthority();
            }
        );

        // filter users who may not administer this Procedure
        // aka "authorized users" in terms of procedure entity

        // planunngsbüros shoul be authorized, but may not in case of permission is disabled.
        // cover this by checking for flag AND for permission (to check)
        if ($excludeProcedureAuthorizedUsers && $this->globalConfig->hasProcedureUserRestrictedAccess()) {
            $authorizedUserIds = $procedure->getAuthorizedUserIds();
            $usersOfOrganisation = $usersOfOrganisation->filter(
                static function (User $user) use ($authorizedUserIds): bool {
                    return \in_array($user->getId(), $authorizedUserIds);
                }
            );
        }

        return $usersOfOrganisation;
    }

    /**
     * Fügt ein Verfahren hinzu.
     *
     * @param array<string, mixed> $data
     *
     * @throws CustomerNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws CriticalConcernException
     * @throws ReflectionException
     * @throws UserNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function addProcedureEntity(array $data, string $currentUserId): Procedure
    {
        try {
            // T15853 + T10976: default while allowing complete deletion of emailTitle by customer:
            $data['settings']['emailTitle'] = $data['settings']['emailTitle'] ?? '';
            if ('' === $data['settings']['emailTitle']) {
                $data['settings']['emailTitle'] = $this->translator->trans('participation.invitation').': '.($data['name'] ?? '');
            }

            // Wrap creation of procedure in a transaction to be able to validate the whole procedure including subentities
            $doctrineConnection = $this->entityManager->getConnection();
            $doctrineConnection->beginTransaction();

            $newProcedure = $this->procedureRepository->add($data);

            /** @var string|null $blueprintId */
            $blueprintId = $data['copymaster'] ?? null;
            $blueprintId = $blueprintId instanceof Procedure ? $blueprintId->getId() : $blueprintId;
            $newProcedure = $this->setAuthorizedUsersToProcedure($newProcedure, $blueprintId, $currentUserId);
            if (\array_key_exists('explanation', $data)) {
                // Create a Paragraph Element from the explanation and add it to the procedure
                $explanation = $data['explanation'];
                $newProcedure = $this->createElementFromExplanation($newProcedure, $explanation);
            }
            $newProcedure = $this->procedureRepository->updateObject($newProcedure);

            if (null !== $blueprintId) {
                $newProcedure = $this->copyFromBlueprint($data, $blueprintId, $newProcedure);
            }

            $violationList = $this->validator->validate($newProcedure, null, [Procedure::VALIDATION_GROUP_MANDATORY_PROCEDURE_ALL_INCLUDED]);
            if (0 !== $violationList->count()) {
                $doctrineConnection->rollBack();
                throw ViolationsException::fromConstraintViolationList($violationList);
            }

            try {
                $this->prepareReportFromProcedureService
                    ->addReportOnProcedureCreate($this->procedureToLegacyConverter->convertToLegacy($newProcedure), $newProcedure);
            } catch (Exception $e) {
                $doctrineConnection->rollBack();
                $this->logger->warning('Add Report in addProcedure() failed Message: ', [$e]);
                throw $e;
            }

            /** @var PostNewProcedureCreatedEvent $postNewProcedureCreatedEvent */
            $postNewProcedureCreatedEvent = $this->eventDispatcher->dispatch(
                new PostNewProcedureCreatedEvent($newProcedure, $data['procedureCoupleToken']));
            if ($postNewProcedureCreatedEvent->hasCriticalEventConcerns()) {
                $doctrineConnection->rollBack();
                throw new CriticalConcernException('Critical concerns occurs', $postNewProcedureCreatedEvent->getCriticalEventConcerns());
            }
            $newProcedure = $postNewProcedureCreatedEvent->getProcedure();
            $doctrineConnection->commit();

            return $newProcedure;
        } catch (Exception $e) {
            $this->logger->warning('Create Procedure failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Mark a procedure as deleted to be purged by maintenancetask.
     *
     * @param string|array $procedureIds
     *
     * @throws Exception
     */
    public function deleteProcedure($procedureIds): void
    {
        try {
            if (!\is_array($procedureIds)) {
                $procedureIds = [$procedureIds];
            }

            foreach ($procedureIds as $procedureId) {
                $data = [
                    'ident'    => $procedureId,
                    'customer' => null,
                    'deleted'  => true,
                ];

                try {
                    $this->updateProcedureWithoutReport($data);
                    $this->getLogger()->info('Procedure marked as deleted: '.\var_export($procedureId, true));
                } catch (Exception $e) {
                    $this->getLogger()->warning("Mark Procedure '$procedureId' as deleted failed Message: ", [$e]);
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Mark Procedure as deleted failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all ReportEntries of a specific procedure.
     *
     * @param Procedure $procedure procedure whose reports will be deleted
     *
     * @return int number of deleted Reports
     *
     * @throws Exception
     */
    protected function deleteReports(Procedure $procedure): int
    {
        if (!$procedure->isDeleted()) {
            throw new BadRequestException('Not allowed to delete Reports of undeleted procedure');
        }

        return $this->procedureRepository->deleteRelatedReports($procedure->getId());
    }

    /**
     * Deletes all EntityContentChanges of a specific procedure.
     *
     * @param Procedure $procedure procedure whose reports will be deleted
     *
     * @return int number of deleted EntityContentChanges
     *
     * @throws Exception
     */
    protected function deleteEntityContentChanges(Procedure $procedure): int
    {
        if (!$procedure->isDeleted()) {
            throw new BadRequestException('Not allowed to delete EntityContentChanges of undeleted procedure');
        }

        return $this->entityContentChangeRepository->deleteByProcedure($procedure->getId());
    }

    /**
     * Delete a procedure and its related entities from db.
     *
     * @throws Exception
     */
    public function purgeProcedure(string $procedureId): void
    {
        try {
            $fileService = $this->fileService;
            $repository = $this->procedureRepository;

            // Delete Entities manually as constraints are no option anymore because mysql truncates tables when
            // trying to add constraints afterwards

            // Deletes Statements, DraftStatements, DraftStatementsVersions, EntityContentChanges and Reports
            $procedure = $this->getProcedure($procedureId);
            if (!$procedure instanceof Procedure) {
                throw ProcedureNotFoundException::createFromId($procedureId);
            }
            $numberOfDeletedReports = $this->deleteReports($procedure);
            $this->getLogger()->info($numberOfDeletedReports.' Reports were deleted.');

            // @improve: T12924
            $numberOfDeletedEntityContentChanges = $this->deleteEntityContentChanges($procedure);
            $this->getLogger()->info($numberOfDeletedEntityContentChanges.' EntityContentChanges were deleted.');

            $numberOfDeletedStatements = $this->deleteStatements($procedure);
            $this->getLogger()->info($numberOfDeletedStatements.' Statements were deleted.');

            $numberOfDeletedDraftStatements = $this->deleteDraftStatements($procedure);
            $this->getLogger()->info($numberOfDeletedDraftStatements.' DraftStatements were deleted.');

            $numberOfDeletedDraftStatementVersions = $this->deleteDraftStatementVersions($procedure);
            $this->getLogger()->info($numberOfDeletedDraftStatementVersions.' DraftStatementVersions were deleted.');

            $filesToDelete = $repository->deleteRelatedEntitiesOfProcedure($procedureId);

            // delete pregenerated zips in filedirectory/procedure
            $filesPath = $fileService->getFilesPathAbsolute();
            if (\is_dir($filesPath.'/procedure/'.$procedureId)) {
                try {
                    $fs = new Filesystem();
                    $fs->remove($filesPath.'/procedure/'.$procedureId);
                    $fs = null;
                } catch (Exception $e) {
                    $this->getLogger()->error(
                        'Could not delete orphaned Directory  '.$filesPath.'/procedure/'.$procedureId.'. Reason: '.$e
                    );
                }
            } else {
                $this->getLogger()->info(
                    'Pregenerated zips folder not found. '.$filesPath.'/procedure/'.$procedureId
                );
            }

            // Necessary data for the PostProcedureDeletedEvent
            $procedureData = [
                'id' => $procedureId,
            ];

            // Necessary data for the PostProcedureDeletedEvent
            $procedureData = [
                'id'                => $procedureId,
                'maillaneAccountId' => $procedure->getMaillaneConnection()->getMaillaneAccountId(),
            ];

            // Necessary data for the PostProcedureDeletedEvent
            $procedureData = [
                'id'                => $procedureId,
                'maillaneAccountId' => $procedure->getMaillaneConnection()->getMaillaneAccountId(),
            ];

            // delete Procedure
            $repository->delete($procedureId);

            $this->eventDispatcher->dispatch(new PostProcedureDeletedEvent($procedureData));

            $this->logger->info('Procedure deleted: '.$procedureId);
        } finally {
            // free memory to avoid memory leaks in command
            $repository = null;
            $fileService = null;
        }
    }

    /**
     * Update eines Verfahren.
     *
     * @param array   $data
     * @param Session $session
     *
     * @return array
     *
     * @throws Exception
     */
    public function updateProcedure($data)
    {
        try {
            $data['ident'] = $data['ident'] ?? $data['id'];
            if (!isset($data['ident'])) {
                throw new \InvalidArgumentException('Ident is missing');
            }

            $origSourceRepos = $this->procedureRepository->get($data['ident']);
            if (null === $origSourceRepos) {
                throw new \InvalidArgumentException('Procedure for given Ident could not be found');
            }

            $sourceProcedure = $this->cloneProcedure($origSourceRepos);

            // Update exportSettings
            if (\array_key_exists('exportSettings', $data)) {
                $this->entityPreparator->prepareEntity($data['exportSettings'], $origSourceRepos->getDefaultExportFieldsConfiguration());
            }

            try {
                $data['currentUser'] = $this->currentUser->getUser();
            } catch (UserNotFoundException $e) {
                $this->logger->info('Procedure updated without known user');
            }
            $procedure = $this->procedureRepository->update($data['ident'], $data);

            $procedure = $this->phasePermissionsetLoader->loadPhasePermissionsets($procedure);
            // always update elasticsearch as changes that where made only in
            // ProcedureSettings not automatically trigger an ES update
            if (DemosPlanKernel::ENVIRONMENT_TEST !== $this->environment) {
                $this->getEsProcedurePersister()->replaceOne($procedure);
            }

            $destinationProcedure = $this->procedureRepository->get($data['ident']);

            $this->prepareReportFromProcedureService->createReportEntry(
                $sourceProcedure,
                $destinationProcedure,
            );

            // convert procedure to match legacy arraystructure
            return $this->procedureToLegacyConverter->convertToLegacy($procedure);
        } catch (Exception $e) {
            $this->logger->warning('Update Procedure failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update of a procedure-object.
     *
     * @param User|null $user
     *
     * @return array|Procedure
     *
     * @throws Exception
     */
    public function updateProcedureObject(Procedure $procedureToUpdate)
    {
        try {
            // this method cant create report entry, because doctrine cant get "un"updated procedure from DB:
            // therefore there will be no difference between sourceProcedure and updatedProcedure.

            // clone the source-procedure and the procedure-settings before update for report entry
            $sourceProcedure = clone $this->procedureRepository->get($procedureToUpdate->getId());
            $sourceProcedureSettings = clone $sourceProcedure->getSettings();

            // set the cloned settings into the source procedure
            $sourceProcedure->setSettings($sourceProcedureSettings);

            $procedure = $this->procedureRepository->updateObject($procedureToUpdate);

            // always update elasticsearch as changes that where made only in
            // ProcedureSettings not automatically trigger an ES update
            if (DemosPlanKernel::ENVIRONMENT_TEST !== $this->environment) {
                $this->getEsProcedurePersister()->replaceOne($procedure);
            }

            $destinationProcedure = $this->procedureRepository->get($procedure->getId());

            // create report with the sourceProcedure including the related settings
            $this->prepareReportFromProcedureService->createReportEntry(
                $sourceProcedure,
                $destinationProcedure,
            );

            return $procedure;
        } catch (Exception $e) {
            $this->getLogger()->warning('Update Procedure Object failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update eines Verfahren ohne einen Reporteintrag zu generieren & ohne eine Session zu benötigen
     * (z.B. aus einem ConsoleCommand).
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function updateProcedureWithoutReport($data)
    {
        try {
            if (!isset($data['ident'])) {
                throw new \InvalidArgumentException('Ident is missing');
            }

            $procedure = $this->procedureRepository->update($data['ident'], $data);
            // always update elasticsearch as changes that where made only in
            // ProcedureSettings not automatically trigger an ES update
            if (DemosPlanKernel::ENVIRONMENT_TEST !== $this->environment) {
                $this->getEsProcedurePersister()->replaceOne($procedure);
            }

            return $this->procedureToLegacyConverter->convertToLegacy($procedure);
        } catch (Exception $e) {
            $this->logger->warning('Update Procedure without Report failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Ruft alle Verfahren mit aktivierter öffentlicher Beteiligung ab (Liste).
     *
     * Alle Verfahren mit aktivierter öffentlicher Beteiligung
     *
     * @param QueryProcedure $esQuery
     *
     * @throws Exception
     */
    public function getPublicList($esQuery): array
    {
        try {
            $this->profilerStart('ES');
            $procedureList = $this->procedureElasticsearchRepository->searchProcedures($esQuery);
            $this->profilerStop('ES');

            return $procedureList;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der ProcedurePublicList: ', [$e]);
            throw $e;
        }
    }

    /**
     * Fetch all procedures, who are ending in a given time.
     *
     * @param bool $internal check for institution phases. false checks public phases
     *
     * @return Procedure[]
     *
     * @throws Exception
     */
    public function getListOfProceduresEndingSoon(int $exactlyDaysToGo, $internal = true): array
    {
        return $this->procedureRepository->getListOfSoonEnding($exactlyDaysToGo, $internal);
    }

    /**
     * Hinzufügen eines Verfahrenabonnements.
     *
     * @param string $postcode Postleitzahl
     * @param string $city
     * @param int    $distance Distanz
     *
     * @throws Exception
     */
    public function addSubscription($postcode, $city, $distance, User $user): ProcedureSubscription
    {
        try {
            $procedureSubscription = $this->createSubscription($postcode, $city, $distance, $user);
            $this->procedureSubscriptionRepository->updateObjects([$procedureSubscription]);

            return $procedureSubscription;
        } catch (Exception $e) {
            $this->logger->warning('Add ProcedureSubscription failed : ', [$e]);
            throw $e;
        }
    }

    /**
     * Abruf der Verfahrens-Abonnements zu einem User bzw. zu einer E-Mail-Adresse.
     *
     * @param array $filter
     * @param bool  $toLegacy
     *
     * @return array
     *
     * @throws Exception
     *
     * @deprecated do not spread usage of this; see T21768
     */
    public function getSubscriptionList($filter, $toLegacy = true)
    {
        try {
            $subscriptions = $this->procedureSubscriptionRepository->findBy($filter);

            if ($toLegacy) {
                $subscriptionArray = [];
                foreach ($subscriptions as $subscription) {
                    $subscriptionArray[] = $this->procedureToLegacyConverter->convertSubscriptionToLegacy($subscription);
                }
                $subscriptions = ['result' => $subscriptionArray, 'total' => count($subscriptionArray)];
            }

            return $subscriptions;
        } catch (Exception $e) {
            $this->logger->warning('getSubscriptionList failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * löschen einer Subscription.
     *
     * @param string $ident Id der zu löschenden Subscription
     *
     * @throws Exception
     */
    public function deleteSubscription($ident): bool
    {
        try {
            return $this->procedureSubscriptionRepository->delete($ident);
        } catch (Exception $e) {
            $this->logger->warning('delete ProcedureSubscription failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * Gib die Subscriptioneinträge aus, deren Auswahl von PLZ und Radius auf
     * das Verfahren zutrifft.
     *
     * @param string $procedureId
     * @param bool   $useDistance
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getProcedureSubscriptionList($procedureId, $useDistance = true)
    {
        try {
            /*
             Logik:
             - Get Postleitzahl vom Verfahren
             - Get loc_id der OpenGeoDb zur PLZ des Verfahrens
             - Postleitzahlen im Radius von 5, 10 & 50 km um die PLZ des Verfahrens einsammeln
             - Alle Subscriptions heraussuchen, die mit einem Radius von 5km IN() den gefundenen Postleitzahlen liegen
             - Subscriptions unique merken
             */
            // Get Postleitzahl vom Verfahren
            $locationService = $this->locationService;
            $procedure = $this->getProcedure($procedureId);
            if (null === $procedure) {
                throw new Exception('Procedure not found. '.$procedureId);
            }
            if (null === $procedure->getLocationPostCode() || '' === $procedure->getLocationPostCode()) {
                throw new Exception('Keine Postleitzahl angegeben, Benachrichtigung nicht möglich. '.$procedureId);
            }

            // Wenn nur eine Suche auf die genaue PLZ gewünscht ist, führe diese aus
            if (false === $useDistance) {
                $filter = ['postcode' => $procedure->getLocationPostCode()];

                return $this->getSubscriptionList($filter, false);
            }

            // Get loc_id der OpenGeoDb zur PLZ des Verfahrens
            $location = $locationService->getSingleLocationFromPostCode($procedure->getLocationPostCode());
            if (!$location instanceof Location) {
                throw new Exception('Keine Location zu PLZ gefunden. '.$procedure->getLocationPostCode());
            }

            // Postleitzahlen im Radius von 5, 10 & 50 km um die PLZ des Verfahrens einsammeln
            $radien = [5, 10, 50];
            $subscriptions = new ArrayCollection();
            foreach ($radien as $radius) {
                $postCodes = $locationService->getPostCodesByRadius($location->getId(), $radius);
                $filter = [
                    'postcode' => $postCodes,
                    'distance' => $radius,
                ];
                // Subscriptions, die zu der Postleitzahl mit dem Radius übereinstimmen
                $procedureSubscriptions = $this->getSubscriptionList($filter, false);
                foreach ($procedureSubscriptions as $subscription) {
                    // füge nur noch nicht vorhandene Subscriptions hinzu
                    if (!$subscriptions->contains($subscription)) {
                        $subscriptions->add($subscription);
                    }
                }
            }

            return ['result' => $subscriptions->toArray(), 'total' => $subscriptions->count()];
        } catch (Exception $e) {
            $this->logger->warning('getProcedureSubscriptionList failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * Fetch all boilerplates for one procedure.
     *
     * @param string $procedureId - Identifies procedure, which boilerplates will be returned
     *
     * @return Boilerplate[]
     */
    public function getBoilerplateList($procedureId): array
    {
        return $this->boilerplateRepository->getBoilerplates($procedureId);
    }

    /**
     * Load all BoilerplateGroups without a related Group, of the given Procedure.
     *
     * @param string $procedureId - Identifies the Procedure, whose Boilerplates will be loaded
     *
     * @return Boilerplate[]
     */
    public function getBoilerplatesWhithoutGroup($procedureId)
    {
        return $this->boilerplateRepository->getBoilerplatesWhithoutGroup($procedureId);
    }

    /**
     * Load a specific BoilerplateGroup from DB.
     *
     * @param string $groupId - Identifies the BoilerplateGroup to load
     *
     * @return BoilerplateGroup|null
     *
     * @throws Exception
     */
    public function getBoilerplateGroup(string $groupId)
    {
        return $this->boilerplateGroupRepository->get($groupId);
    }

    /**
     * Load all BoilerplateGroups of the given Procedure.
     *
     * @param string $procedureId - Identifies the Procedure, whose BoilerplateGroups will be loaded
     *
     * @return BoilerplateGroup[]
     */
    public function getBoilerplateGroups($procedureId, string $categoryTitle = ''): array
    {
        $groupsOfProcedure = $this->boilerplateGroupRepository->getBoilerplateGroups($procedureId);

        if ('' === $categoryTitle) {
            return $groupsOfProcedure;
        }

        return $this->getBoilerplateGroupsFilteredByCategory($groupsOfProcedure, $categoryTitle);
    }

    private function resetDesignatedPhaseSwitch(ProcedureSettings $procedureSettings): void
    {
        $procedureSettings->setDesignatedPhase(null);
        $procedureSettings->setDesignatedSwitchDate(null);
        $procedureSettings->setDesignatedEndDate(null);
        $procedureSettings->setDesignatedPhaseChangeUser(null);
    }

    private function resetDesignatedPublicPhaseSwitch(ProcedureSettings $procedureSettings): void
    {
        $procedureSettings->setDesignatedPublicPhase(null);
        $procedureSettings->setDesignatedPublicSwitchDate(null);
        $procedureSettings->setDesignatedPublicEndDate(null);
        $procedureSettings->setDesignatedPublicPhaseChangeUser(null);
    }

    private function getUserIdOrNull(?User $user): ?string
    {
        return null === $user ? null : $user->getId();
    }

    /**
     * @param string $postcode
     * @param string $city
     * @param int    $distance
     */
    private function createSubscription($postcode, $city, $distance, User $user): ProcedureSubscription
    {
        $procedureSubscription = new ProcedureSubscription();
        $procedureSubscription
            ->setUser($user)
            ->setPostcode($postcode)
            ->setCity($city)
            ->setDistance($distance);

        return $procedureSubscription;
    }

    /**
     * Returns an array of BoilerplateGroups which only contains Boilerplates, with given categoryTitle.
     *
     * @param BoilerplateGroup[] $groupsToFilter
     *
     * @return BoilerplateGroup[]
     */
    private function getBoilerplateGroupsFilteredByCategory(array $groupsToFilter, string $categoryTitle): array
    {
        $filteredGroups = [];
        // filter boilerplates from groups, where not have category.name === $categoryName
        foreach ($groupsToFilter as $group) {
            $filteredBoilerplates = $group->filterBoilerplatesByCategory($categoryTitle);

            if (count($filteredBoilerplates) > 0) {
                $newGroup = new BoilerplateGroupVO();
                $newGroup->setId($group->getId());
                $newGroup->setTitle($group->getTitle());
                $newGroup->setProcedure($group->getProcedure());
                $newGroup->setBoilerplates($filteredBoilerplates);
                $newGroup->lock();

                $filteredGroups[] = $newGroup;
            }
        }

        return $filteredGroups;
    }

    /**
     * Get all boilerplates that belong to a category.
     *
     * @param string $procedureId
     * @param string $category
     *
     * @return Boilerplate[]
     */
    public function getBoilerplatesOfCategory($procedureId, $category): array
    {
        try {
            $boilerplateCategory = $this->boilerplateCategoryRepository->findOneBy(['procedure' => $procedureId, 'title' => $category]);

            return $boilerplateCategory instanceof BoilerplateCategory ? $boilerplateCategory->getBoilerplates()->toArray() : [];
        } catch (Exception $e) {
            throw new HttpException($e->getCode());
        }
    }

    /**
     * Get a list of all boilerplate categories belonging to this procedure.
     *
     * @return BoilerplateCategory[]
     *
     * @throws Exception
     */
    public function getBoilerplateCategoryList(
        string $procedureId,
        bool $includeNewsCategory = true,
        bool $includeEmailCategory = true,
        bool $includeConsiderationCategory = true
    ): array {
        return $this->boilerplateCategoryRepository->getBoilerplateCategoryList(
            $procedureId,
            $includeNewsCategory,
            $includeEmailCategory,
            $includeConsiderationCategory
        );
    }

    /**
     * Fetch all info about certain boilerplate(Id).
     *
     * @param string $boilerplateId
     *
     * @return Boilerplate|null
     *
     * @throws Exception
     */
    public function getBoilerplate($boilerplateId)
    {
        return $this->boilerplateRepository->get($boilerplateId);
    }

    /**
     * Add an entry to a specific procedure into the DB.
     *
     * @param string $procedureId - ID of the related procedure
     * @param array  $data        - holds the content of the boilerplate, which is about to post
     *
     * @return Boilerplate|false - true if the object could be mapped to the DB, otherwise false
     */
    public function addBoilerplate($procedureId, $data)
    {
        try {
            $data['procedure'] = $this->getProcedure($procedureId);

            return $this->boilerplateRepository->add($data);
        } catch (Exception $e) {
            $this->logger->warning('Post boilerplate failed');
        }

        return false;
    }

    /**
     * Removes a boilerplate object from the database.
     *
     * @param string $boilerplateId
     *
     * @throws Exception
     */
    public function deleteBoilerplate($boilerplateId): bool
    {
        return $this->boilerplateRepository->delete($boilerplateId);
    }

    /**
     * Removes a boilerplateGroup object from the database.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteBoilerplateGroup(BoilerplateGroup $boilerplateGroup): bool
    {
        return $this->boilerplateGroupRepository->delete($boilerplateGroup);
    }

    /**
     * @param string[] $groupIdsToDelete
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteBoilerplateGroupsByIds(array $groupIdsToDelete)
    {
        $allDeleted = true;
        foreach ($groupIdsToDelete as $groupId) {
            try {
                $groupToDelete = $this->getBoilerplateGroup($groupId);
                $this->deleteBoilerplateGroup($groupToDelete);
            } catch (TypeError|Exception $e) {
                $allDeleted = false;
            }
        }

        return $allDeleted;
    }

    /**
     * Loads a specific boilerplateentry from the DB and edit this text and/or title.
     *
     * @param string $boilerplateId - Identify the boilerplate, which is to be updated
     * @param array  $data          - Contains the keys and values, which are to be updated
     *
     * @return bool - true, if the boilerpalte was updated, otherwise false
     */
    public function updateBoilerplate($boilerplateId, $data)
    {
        try {
            return $this->boilerplateRepository->update($boilerplateId, $data);
        } catch (Exception $e) {
            $this->logger->warning('Update boilerplate failed: ', [$e]);

            return false;
        }
    }

    /**
     * Einladung an institution wurde versendet.
     *
     * @param string $procedureId Verfahrens-ID
     * @param string $orga        Organisation
     * @param string $phase
     *
     * @throws Exception
     */
    public function addInstitutionMail($procedureId, $orga, $phase): void
    {
        $procedure = $this->getProcedure($procedureId);
        $data = [
            'procedure' => $procedure,
            'orga'      => $orga,
            'phase'     => $phase,
        ];

        $this->institutionMailRepository->add($data);
    }

    /**
     * Liefert Liste aller versendeten Einladungs-Emails der angegebenen Phase.
     *
     * @param string $procedureId Verfahrens-ID
     * @param string $phase       Phase des Verfahrens
     *
     * @return array
     *
     * @throws Exception
     */
    public function getInstitutionMailList($procedureId, $phase = null)
    {
        try {
            $data = [
                'procedure'      => $procedureId,
                'procedurePhase' => $phase,
            ];

            $institutionMailResult = $this->institutionMailRepository->findBy($data);
            // convert to legacy
            $institutionMailList = [];
            foreach ($institutionMailResult as $resultInstitutionMail) {
                $institutionMailList[] = $this->dateHelper->convertDatesToLegacy(
                    $this->entityHelper->toArray($resultInstitutionMail)
                );
            }

            return $this->procedureToLegacyConverter->toLegacyResult($institutionMailList)->toArray();
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der InstitutionMailList: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all institutionMail entries of an organisation.
     *
     * @param string $organisationId - identifies the organisation, whose institutionMail entries will be deleted
     *
     * @return bool - true if institutionMail entries was successfully deleted, otherwise false
     */
    public function deleteInstitutionMailOfOrga($organisationId)
    {
        try {
            $institutionMails = $this->getInstitutionMailsOfOrga($organisationId);
            $this->deleteInstitutionMails($institutionMails);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen des InstitutionMail Eintrages: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * Get procedures where Orga has been added as data input orga.
     *
     * @return array<int, Procedure>
     *
     * @throws Exception
     */
    public function getProceduresForDataInputOrga(string $orgaId): array
    {
        try {
            $allowedPhases = $this->globalConfig->getInternalPhaseKeys('read||write');

            return $this->procedureRepository->getProceduresForDataInputOrga($orgaId, $allowedPhases);
        } catch (Exception $e) {
            $this->getLogger()->warning('Fehler beim Abruf der getProceduresForDataInputOrga: ', [$e]);
            throw $e;
        }
    }

    /**
     * Adapter to make conversion of Userinput to DateTime testable.
     *
     * @internal
     *
     * @param string $input
     * @param string $time  Formatted Time to be generated
     *
     * @return DateTime|null
     */
    public function internalMakeUserInputDateTestable($input, $time = '02:00:00')
    {
        return $this->procedureRepository->convertUserInputDate($input, $time);
    }

    public function getEsProcedurePersister(): ObjectPersisterInterface
    {
        return $this->esProcedurePersister;
    }

    /**
     * @throws Exception
     */
    public function deleteStatements(Procedure $procedure): int
    {
        if (!$procedure->isDeleted()) {
            throw new BadRequestException('Not allowed to delete Statements of undeleted procedure');
        }

        return $this->procedureRepository->deleteStatements($procedure->getId());
    }

    /**
     * @throws Exception
     */
    public function deleteDraftStatements(Procedure $procedure): int
    {
        if (!$procedure->isDeleted()) {
            throw new BadRequestException('Not allowed to delete DraftStatements of undeleted procedure');
        }

        return $this->procedureRepository->deleteDraftStatements($procedure->getId());
    }

    /**
     * @throws Exception
     */
    public function deleteDraftStatementVersions(Procedure $procedure): int
    {
        if (!$procedure->isDeleted()) {
            throw new BadRequestException('Not allowed to delete DraftStatementVersions of undeleted procedure');
        }

        return $this->procedureRepository->deleteDraftStatementVersions($procedure->getId());
    }

    public function isCustomerMasterBlueprintExisting(string $customerId): bool
    {
        return $this->procedureRepository->findOneBy(['deleted' => false, 'customer' => $customerId]) instanceof Procedure;
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function calculateCopyMasterId(string $incomingCopyMasterId = null): string
    {
        // use global default blueprint as default anyway:
        $masterTemplateId = $this->masterTemplateService->getMasterTemplateId();
        $incomingCopyMasterId = $incomingCopyMasterId ?? $masterTemplateId;

        // T15664: in case of globalMasterBlueprint is set,
        // use customer master blueprint if existing, (instead of global masterblueprint)
        if ($masterTemplateId === $incomingCopyMasterId) {
            $customerBlueprint = $this->customerService->getCurrentCustomer()->getDefaultProcedureBlueprint();
            if ($customerBlueprint instanceof Procedure) {
                $incomingCopyMasterId = $customerBlueprint->getId();
            }
        }

        return $incomingCopyMasterId;
    }

    /**
     * Returns a list of all topics, related to a specific procedure.
     *
     * @param string $procedureId
     *
     * @return TagTopic[] List of all Topics of this procedure
     *
     * @throws Exception
     */
    public function getTopics($procedureId)
    {
        try {
            return $this->procedureRepository->getTopics($procedureId);
        } catch (Exception $e) {
            $this->logger->error('GetTopics of the procedure with ID: '.$procedureId.' failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Organisation to this Procedure.
     *
     * @param Orga[] $organisations - Organisations to add
     *
     * @throws Exception if adding the organisations failed
     */
    public function addOrganisations(Procedure $procedure, array $organisations)
    {
        try {
            foreach ($organisations as $organisation) {
                if (!$organisation instanceof Orga) {
                    $this->getLogger()->warning('Could not add Orga to Procedure Orga:'.DemosPlanTools::varExport($organisation, true));
                    continue;
                }
                $procedure->addOrganisation($organisation);
            }

            $this->procedureRepository->updateObject($procedure);
        } catch (Exception $e) {
            $this->getLogger()->warning('Add Orga to Procedure failed Reason: ', [$e]);

            throw $e;
        }
    }

    /**
     * Detach a Organisation from this Procedure.
     *
     * @return bool - False in case of exception, otherwise true
     */
    public function detachOrganisation(Procedure $procedure, Orga $organisation)
    {
        try {
            $procedure->removeOrganisation($organisation);
            $this->procedureRepository->updateObject($procedure);
        } catch (Exception $e) {
            $this->getLogger()->warning('Remove Orga from Procedure failed Reason: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * Detach given Organisations from this Procedure.
     *
     * @param Orga[] $organisations
     *
     * @return bool - False in case of exception, otherwise true
     */
    public function detachOrganisations(Procedure $procedure, array $organisations)
    {
        try {
            foreach ($organisations as $organisation) {
                $this->detachOrganisation($procedure, $organisation);
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Checks if given string is in procedurephases.yml listed as publicPhases and therefore a "valid" phasekey.
     * Null is also a "valid" phase as "designatedPhase".
     *
     * @param string $phaseName - name of the public phase
     *
     * @return bool - true if the given $phaseName is null or in the list of public procedurephases of this project
     */
    protected function isValidDesignatedPublicPhase($phaseName)
    {
        return \in_array($phaseName, $this->globalConfig->getExternalPhaseKeys()) || null === $phaseName;
    }

    /**
     * Check if the given procedure have a designated public date to switch on AND a public phase to switch to.
     *
     * @return bool true if designated phase and date are not null, otherwise false
     */
    public function isAutoSwitchOfPublicPhasePossible(Procedure $procedure): bool
    {
        $procedureSettings = $procedure->getSettings();

        return null !== $procedureSettings->getDesignatedPublicSwitchDate()
            && null !== $procedureSettings->getDesignatedPublicPhase()
            && null !== $procedureSettings->getDesignatedPublicEndDate();
    }

    /**
     * Check if the given procedure have a designated date to switch on AND a phase to switch to.
     *
     * @return bool true if designated phase and date are not null, otherwise false
     */
    public function isAutoSwitchOfPhasePossible(Procedure $procedure): bool
    {
        $procedureSettings = $procedure->getSettings();

        return null !== $procedureSettings->getDesignatedSwitchDate()
            && null !== $procedureSettings->getDesignatedPhase()
            && null !== $procedureSettings->getDesignatedEndDate();
    }

    /**
     * Switch the current phase of the given procedure to the designated phase,
     * if designated phase and designated date are set.
     *
     * @param Procedure $procedure procedure of which the {@link Procedure::$phase} will be switched
     *
     * @throws Exception
     */
    public function switchToDesignatedPhase(Procedure $procedure): bool
    {
        $procedureSettings = $procedure->getSettings();

        if (!$this->isAutoSwitchOfPhasePossible($procedure)) {
            $this->logger->info('Automatic phase switch prevented because of incomplete settings.', [$procedure->getId()]);

            $this->resetDesignatedPhaseSwitch($procedureSettings);

            return false;
        }

        $switchDate = $procedureSettings->getDesignatedSwitchDate();
        if (Carbon::now()->lessThan($switchDate)) {
            // do not reset phase switch as public and internal phase switch may
            // have different timestamps. In that case one of them would be deleted
            // silently

            return false;
        }

        try {
            $procedure->setStartDate($procedureSettings->getDesignatedSwitchDate());
            $procedure->setPhase($procedureSettings->getDesignatedPhase());
            $procedure->setEndDate($procedureSettings->getDesignatedEndDate());

            $this->resetDesignatedPhaseSwitch($procedureSettings);

            return true;
        } catch (Exception $e) {
            $this->logger->error('switchToDesignatedPhase of the procedure with ID: '.$procedure->getId().' failed: ', [$e]);

            $this->resetDesignatedPhaseSwitch($procedureSettings);

            throw $e;
        }
    }

    /**
     * Switch the current publicPhase of the given procedure to the designated publicPhase,
     * if designated publicPhase and designated publicDate are set.
     *
     * @param Procedure $procedure procedure of which the {@link Procedure::$publicPhase} will be switched
     *
     * @throws Exception
     */
    public function switchToDesignatedPublicPhase(Procedure $procedure): bool
    {
        $procedureSettings = $procedure->getSettings();

        if (!$this->isAutoSwitchOfPublicPhasePossible($procedure)) {
            return false;
        }

        $publicSwitchDate = $procedureSettings->getDesignatedPublicSwitchDate();
        if (Carbon::now()->lessThan($publicSwitchDate)) {
            // do not reset phase switch as public and internal phase switch may
            // have different timestamps. In that case one of them would be deleted
            // silently

            return false;
        }

        try {
            $procedure->setPublicParticipationStartDate($procedureSettings->getDesignatedPublicSwitchDate());
            $procedure->setPublicParticipationPhase($procedureSettings->getDesignatedPublicPhase());
            $procedure->setPublicParticipationEndDate($procedureSettings->getDesignatedPublicEndDate());

            $this->resetDesignatedPublicPhaseSwitch($procedureSettings);

            return true;
        } catch (Exception $e) {
            $this->logger->error('switchToDesignatedPublicPhase of the procedure with ID: '.$procedure->getId().' failed: ', [$e]);

            $this->resetDesignatedPublicPhaseSwitch($procedureSettings);

            throw $e;
        }
    }

    /**
     * Returns two list of procedures, which are prepared to switch phase on a specific date.
     * The current date will be used.
     *
     * The result will not contain deleted procedures.
     *
     * @return array<int, Procedure>
     *
     * @throws Exception
     */
    public function getProceduresToSwitchUntilNow(): array
    {
        return $this->procedureRepository->getProceduresReadyToSwitchPhases();
    }

    /**
     * Returns all institutionMail Entries, which belongs to the given organisation.
     *
     * @param string $organisationId - identifies the organisation
     *
     * @return InstitutionMail[]
     */
    public function getInstitutionMailsOfOrga($organisationId)
    {
        return $this->institutionMailRepository->findBy(['organisation' => $organisationId]);
    }

    /**
     * Deletes the given institutionMail Entries.
     *
     * @param institutionMail[] $institutionMails - list of institutionMail entries to delete
     *
     * @throws Exception
     */
    public function deleteInstitutionMails($institutionMails)
    {
        foreach ($institutionMails as $institutionMail) {
            $this->institutionMailRepository->delete($institutionMail->getId());
        }
    }

    /**
     * Get list of procedures without localization (postalcode, gemeindekennzahl etc).
     *
     * @return Setting[]|array|null
     *
     * @throws Exception
     */
    public function getProcedureLocalizationQueue()
    {
        return $this->contentService->getSettings('needLocalization', null, false);
    }

    /**
     * Removes procedure von localizationQueue.
     *
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function removeProcedureFromLocalizationQueue($procedureId)
    {
        $settings = $this->contentService->getSettings(
            'needLocalization',
            SettingsFilter::whereProcedureId($procedureId)->lock(),
            false
        );
        if (\is_array($settings) && $settings[0] instanceof Setting) {
            $this->contentService->deleteSetting($settings[0]->getIdent());
        }
    }

    /**
     * Set authorized users to given procedure.
     * Will set at least the current user as authorized user to ensure one user will be authorized.
     * Will set all users of organisation of the current user in case of given blueprint is the master blueprint.
     * Will set all authorized users of given blueprint.
     *
     * @param string|Procedure $blueprint
     * @param string           $currentUserId
     *
     * @return Procedure
     *
     * @throws Exception
     */
    protected function setAuthorizedUsersToProcedure(Procedure $newProcedure, $blueprint, $currentUserId)
    {
        $currentUser = $this->userService->getSingleUser($currentUserId);
        // at least the current user has to be set as User
        $newProcedure->setAuthorizedUsers([$currentUser]);

        if (null !== $blueprint) {
            $blueprint =
                \is_string($blueprint) ? $this->getProcedure($blueprint) : $blueprint;

            if (false === $blueprint->getAuthorizedUsers()->isEmpty()) {
                $newProcedure->setAuthorizedUsers($blueprint->getAuthorizedUsers());
            }

            // T15644: T23583:
            // overwrite authorized users in case of used blueprint is a master-blueprint,
            // to avoid authorizing creators of masterblueprint to this new procedure:
            if ($blueprint->isMasterTemplate() || $blueprint->isCustomerMasterBlueprint()) {
                $newProcedure->setAuthorizedUsers([$currentUser]);
            }
        }

        return $newProcedure;
    }

    /**
     * Will copy Boilerplates including related Boilerplatecategories and also copy emtpy Categories.
     *
     * @param string    $blueprintId  - The ID of the blueprint procedure
     * @param procedure $newProcedure - The new created procedure object
     *
     * @throws Exception
     */
    public function copyBoilerplates(string $blueprintId, $newProcedure)
    {
        $this->boilerplateRepository->copyBoilerplates($blueprintId, $newProcedure);

        // copy boilerplatecategories without boilerplates:
        $this->boilerplateCategoryRepository->copyEmptyCategories($blueprintId, $newProcedure);

        // copy boilerplateGroups without boilerplates:
        $this->boilerplateGroupRepository->copyEmptyGroups($blueprintId, $newProcedure);

        // ensure that we have at least our base Categories & Groups from Master blueprint
        $this->boilerplateCategoryRepository
            ->ensureBaseCategories($this->masterTemplateService->getMasterTemplateId(), $newProcedure);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateBoilerplateVO(BoilerplateVO $boilerplateVO): Boilerplate
    {
        $boilerplate = $this->boilerplateRepository->get($boilerplateVO->getId());

        if (null === $boilerplate) {
            throw new Exception('Boilerplate with id: '.$boilerplateVO->getId().' not found');
        }

        $boilerplate->setTitle($boilerplateVO->getTitle());
        $boilerplate->setText($boilerplateVO->getText());

        // resolve & set categories:
        $categories = [];
        /** @var BoilerplateCategoryVO $boilerplateCategoryVO */
        foreach ($boilerplateVO->getCategories() as $boilerplateCategoryVO) {
            $category = $this->boilerplateCategoryRepository->get($boilerplateCategoryVO->getId());
            $categories[] = $category;
        }
        $boilerplate->setCategories($categories);

        // resolve & set group:
        // enable unset of group, because in this case incoming group is null
        $boilerplate->detachGroup();
        if (null !== $boilerplateVO->getGroupId()) {
            $group = $this->boilerplateGroupRepository->get($boilerplateVO->getGroupId());
            $boilerplate->setGroup($group);
        }

        return $this->boilerplateRepository->updateObject($boilerplate);
    }

    /**
     * Add a new BoilerplateGroup to DB.
     *
     * @param string $procedureId - Identifies the Procedure, of the BoilerpalteGroup to create
     *
     * @return Boilerplate|void
     */
    public function addBoilerplateGroupVO($procedureId, BoilerplateGroupVO $groupVO)
    {
        try {
            $boilerplateGroupRepository = $this->boilerplateGroupRepository;
            $procedure = $this->getProcedure($procedureId);

            $group = new BoilerplateGroup($groupVO->getTitle(), $procedure);

            return $boilerplateGroupRepository->addObject($group);
        } catch (Exception $e) {
            $this->logger->error('Could not add Boilerplate: ', [$e]);
        }
    }

    /**
     * Updates a specific BoilerplateGroup.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateBoilerplateGroupVO(BoilerplateGroupVO $groupVO): BoilerplateGroup
    {
        $boilerplateGroupRepository = $this->boilerplateGroupRepository;
        $group = $boilerplateGroupRepository->get($groupVO->getId());
        if (null === $group) {
            throw new Exception('BoilerplateGroup with id: '.$groupVO->getId().' not found');
        }

        $group->setTitle($groupVO->getTitle());

        return $boilerplateGroupRepository->updateObject($group);
    }

    /**
     * @param string $procedureId
     */
    public function addBoilerplateVO($procedureId, BoilerplateVO $boilerplateVO)
    {
        try {
            $procedure = $this->getProcedure($procedureId);

            $boilerplate = new Boilerplate();

            $boilerplate->setProcedure($procedure);
            $boilerplate->setTitle($boilerplateVO->getTitle());
            $boilerplate->setText($boilerplateVO->getText());

            // resolve & set categories:
            $categories = [];
            /** @var BoilerplateCategoryVO $boilerplateCategoryVO */
            foreach ($boilerplateVO->getCategories() as $boilerplateCategoryVO) {
                $category = $this->boilerplateCategoryRepository->findOneBy(['id' => $boilerplateCategoryVO->getId()]);
                $categories[] = $category;
            }
            $boilerplate->setCategories($categories);

            // resolve & set group:
            if (null !== $boilerplateVO->getGroupId()) {
                $group = $this->boilerplateGroupRepository->get($boilerplateVO->getGroupId());
                $boilerplate->setGroup($group);
            }

            return $this->boilerplateRepository->addObject($boilerplate);
        } catch (Exception $e) {
            $this->logger->error('Could not add Boilerplate: ', [$e]);
        }
    }

    /**
     * @param string $boilerplateId
     *
     * @return Boilerplate|null
     */
    public function getBoilerplateById($boilerplateId)
    {
        return $this->boilerplateRepository->findOneBy(['ident' => $boilerplateId]);
    }

    /**
     * Returns all procedures, which can not be accessed by the given user.
     *
     * @param User        $user                 - user, whose access will be checked
     * @param string|null $procedureIdToExclude
     *
     * @return Procedure[] - procedures, where the given user has no access
     *
     * @throws Exception
     */
    public function getInaccessibleProcedures(User $user, $procedureIdToExclude = null)
    {
        $conditions = [
            $this->conditionFactory->propertyHasValue(false, ['deleted']),
            $this->conditionFactory->propertyHasValue(false, ['master']),
        ];

        if (null !== $procedureIdToExclude) {
            $conditions[] = $this->conditionFactory->propertyHasNotValue($procedureIdToExclude, ['id']);
        }

        // in case of planungsbüro foreign procedures means: procedures where orga is not assigned
        if (false !== \stripos(Role::PRIVATE_PLANNING_AGENCY, $user->getRole())) {
            $conditions[] = $this->conditionFactory->propertyHasNotStringAsMember($user->getOrganisationId(), ['planningOffices']);
        } else {
            $conditions[] = $this->conditionFactory->propertyHasNotValue($user->getOrganisationId(), ['orga']);
        }

        $sortMethod = $this->sortMethodFactory->propertyAscending(['name']);

        $foreignProcedures = $this->entityFetcher->listEntitiesUnrestricted(
            Procedure::class,
            $conditions,
            [$sortMethod]
        );

        $unauthorizedProcedures = [];
        if ($this->globalConfig->hasProcedureUserRestrictedAccess()) {
            // this is additional needed because of filter "p.orga != :oId" can not be combined with the logic of getUnauthorizedProcedures()
            // additional get procedures of orga of current user, but not accessible:
            $unauthorizedProcedures = $this->getUnauthorizedProcedures($user);
        }

        return \array_merge($foreignProcedures, $unauthorizedProcedures);
    }

    /**
     * Returns all procedure of organisation of the given user, which the given user is NOT authorized for.
     *
     * @return array|Collection
     *
     * @throws Exception
     */
    public function getUnauthorizedProcedures(User $user)
    {
        $conditions = [
            $this->conditionFactory->propertyHasValue(false, ['deleted']),
            $this->conditionFactory->propertyHasValue(false, ['master']),
            $this->conditionFactory->propertyHasValue($user->getOrganisationId(), ['orga']),
            $this->conditionFactory->propertyHasNotStringAsMember($user->getId(), ['authorizedUsers']),
        ];

        $sortMethod = $this->sortMethodFactory->propertyDescending(['createdDate']);

        return $this->entityFetcher->listEntitiesUnrestricted(
            Procedure::class,
            $conditions,
            [$sortMethod]
        );
    }

    /**
     * Return an array of ids and names of procedures, which are inaccessible for given user.
     * Format:
     * [procedureId => [procedureId, procedureName]].
     *
     * @param string|null $procedureIdToExclude
     *
     * @return array
     *
     * @throws Exception
     */
    public function getInaccessibleProcedureIds(User $user, $procedureIdToExclude = null)
    {
        $inAccessibleProcedures = $this->getInaccessibleProcedures($user, $procedureIdToExclude);
        $inAccessibleProcedures =
            \collect($inAccessibleProcedures)->mapWithKeys(function (Procedure $procedure) {
                return [$procedure->getId() => ['id' => $procedure->getId(), 'name' => $procedure->getName()]];
            });

        return $inAccessibleProcedures->toArray();
    }

    /**
     * Return an array of ids and names of procedures, which are accessible for given user.
     * Format:
     * [procedureId => [procedureId, procedureName]].
     *
     * @param null $procedureIdToExclude
     *
     * @return array
     *
     * @throws Exception
     */
    public function getAccessibleProcedureIds(User $user, $procedureIdToExclude = null)
    {
        $filters = null === $procedureIdToExclude ? [] : ['procedureIdToExclude' => $procedureIdToExclude];
        $accessibleProcedures = $this->getProcedureAdminList($filters, null, ['name' => 'ASC'], $user, false, false);

        $accessibleProcedures =
            \collect($accessibleProcedures)->mapWithKeys(function (Procedure $procedure) {
                return [$procedure->getId() => ['id' => $procedure->getId(), 'name' => $procedure->getName()]];
            });

        return $accessibleProcedures->toArray();
    }

    /**
     * @param User|null $user if no user was given then the current user will be used
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function isUserAuthorized(string $procedureId, User $user = null): bool
    {
        if (null === $user) {
            $user = $this->currentUser->getUser();
        }
        $authorizedUsers = $this->getAuthorizedUsers($procedureId, $user);
        $authorizedUserIds = $authorizedUsers->transform(static function (User $user) {
            return $user->getId();
        });
        if ($authorizedUserIds->contains($user->getId())) {
            return true;
        }

        return false;
    }

    /**
     * Creates a new Boilerpaltegroup.
     *
     * @param string $title       - Title of BoilerplateGroup to create
     * @param string $procedureId - Procedure which BoilerplateGroup to create belongs to
     *
     * @return boilerplateGroup - Created BoilerplateGroup
     *
     * @throws Exception
     */
    public function createBoilerplateGroup($title, $procedureId): BoilerplateGroup
    {
        $procedure = $this->getProcedure($procedureId);
        $boilerplateGroup = new BoilerplateGroup($title, $procedure);

        $this->boilerplateGroupRepository->addObject($boilerplateGroup);

        return $boilerplateGroup;
    }

    /**
     * @param array<int, string> $procedureIds
     *
     * @return array<string, int>
     */
    public function getOriginalStatementsCounts(array $procedureIds): array
    {
        return $this->statementRepository->getOriginalStatementsCounts($procedureIds);
    }

    /**
     * @param array<int, string> $procedureIds
     *
     * @return array<string, int>
     */
    public function getStatementsCounts(array $procedureIds): array
    {
        return $this->statementRepository->getStatementsCounts($procedureIds);
    }

    /**
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    public function resetMapHint(string $procedureId): void
    {
        $procedure = $this->getProcedure($procedureId);
        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }
        $defaultMapHintText = $procedure->getProcedureUiDefinition()->getMapHintDefault();
        $procedure->getSettings()->setMapHint($defaultMapHintText);
        $this->updateProcedureObject($procedure);
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function isProcedureInCustomer(string $procedureId, string $subdomain): bool
    {
        $procedure = $this->getProcedure($procedureId);
        if (null === $procedure) {
            $this->logger->warning('Could not find Procedure', ['procedureId' => $procedure]);

            return false;
        }

        $isInCustomer = $subdomain === $procedure->getSubdomain();
        if (!$isInCustomer) {
            $this->logger->warning('Procedure not valid for customer. OrgaType for owning orga correctly set?',
                ['procedureId' => $procedure, 'customer' => $subdomain]
            );
        }

        return $isInCustomer;
    }

    private function cloneProcedure(Procedure $procedure): Procedure
    {
        $procedureClone = clone $procedure;
        $settingsClone = clone $procedureClone->getSettings();
        $procedureClone->setSettings($settingsClone);

        return $procedureClone;
    }

    /**
     * @throws Exception
     */
    private function createElementFromExplanation(
        Procedure $procedure,
        string $explanation): Procedure
    {
        $elementsService = $this->getElementsService();

        $element = new Elements();
        $element->setTitle($this->translator->trans('explanations'));
        $element->setText($explanation);
        $element->setCategory('paragraph');
        $element->setProcedure($procedure);
        $nextOrderIndex = $elementsService->getNextFreeOrderIndex($procedure);
        $element->setOrder($nextOrderIndex);
        $element->setEnabled(true);
        $elementsService->addEntity($element);

        $elements = $procedure->getElements();
        $elements->add($element);
        $procedure->setElements($elements);

        return $procedure;
    }

    /**
     * Accepts a set of hardcoded legacy filters and converts them into conditions.
     *
     * @param string $search
     * @param bool   $excludeArchived
     *
     * @return array<int, FunctionInterface<bool>>
     *
     * @throws PathException
     */
    private function convertFiltersToConditions(
        array $filters,
        $search,
        User $user,
        $excludeArchived,
        bool $template
    ): array {
        $conditions = $this->getAdminProcedureConditions($template, $user);

        if (\is_string($search) && 0 < \strlen($search)) {
            $conditions[] = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue(
                $search,
                ['name']
            );
        }

        if (isset($filters['municipalCode']) && \is_array($filters['municipalCode'])) {
            $conditions[] = $this->conditionFactory->propertyHasAnyOfValues($filters['municipalCode'], ['municipalCode']);
        }

        // use array_key_exists, because value of key 'customer' is null
        if (\array_key_exists('customer', $filters)) {
            // T15644 customer master procedure has customer set
            if ($template &&
                $this->permissions->hasPermission('feature_admin_customer_master_procedure_template')
            ) {
                $conditions[] = $this->conditionFactory->anyConditionApplies(
                    $this->conditionFactory->propertyIsNull(['customer']),
                    $this->conditionFactory->propertyHasValue($this->customerService->getCurrentCustomer()->getId(), ['customer'])
                );
            } elseif (null === $filters['customer']) {
                $conditions[] = $this->conditionFactory->propertyIsNull(['customer']);
            } else {
                $conditions[] = $this->conditionFactory->propertyHasValue($filters['customer'], ['customer']);
            }
        }

        if (\array_key_exists('orgaCustomerId', $filters)) {
            $conditions[] = $this->conditionFactory->propertyHasValue(
                $filters['orgaCustomerId'],
                ['orga', 'statusInCustomers', 'customer', 'id']
            );
        }

        if (isset($filters['procedureIdToExclude'])) {
            $conditions[] = $this->conditionFactory->propertyHasNotValue($filters['procedureIdToExclude'], ['id']);
        }

        if ($excludeArchived) {
            $conditions[] = $this->conditionFactory->anyConditionApplies(
                $this->conditionFactory->propertyHasNotValue('closed', ['phase']),
                $this->conditionFactory->propertyHasNotValue('closed', ['publicParticipationPhase'])
            );
        }

        // may be simplified
        $hiddenPhases = \array_unique(
            \array_merge(
                $this->globalConfig->getInternalPhaseKeys('hidden'),
                $this->globalConfig->getExternalPhaseKeys('hidden')
            )
        );

        if (isset($filters['excludeHiddenPhases'])) {
            // Include only procedures where at least one phase is not hidden
            $conditions[] = $this->conditionFactory->anyConditionApplies(
                $this->conditionFactory->propertyHasNotAnyOfValues($hiddenPhases, ['phase']),
                $this->conditionFactory->propertyHasNotAnyOfValues($hiddenPhases, ['publicParticipationPhase']),
            );
        }

        return $conditions;
    }

    /**
     * Takes an array with keys and values in a legacy format and converts them into
     * {@link SortMethodInterface} instances. If `null` is given a default will be returned.
     *
     * @param array|null $sort
     *
     * @return array<int, SortMethodInterface>
     *
     * @throws PathException
     */
    private function convertSortArrayToSortMethods($sort): array
    {
        $sortMethods = [];
        if (null === $sort) {
            $sortMethods[] = $this->sortMethodFactory->propertyDescending(['createdDate']);
            $sortMethods[] = $this->sortMethodFactory->propertyAscending(['name']);
        } else {
            foreach ($sort as $key => $value) {
                if ('ASC' === $value) {
                    $sortMethods[] = $this->sortMethodFactory->propertyAscending([$key]);
                } else {
                    $sortMethods[] = $this->sortMethodFactory->propertyDescending([$key]);
                }
            }
        }

        return $sortMethods;
    }

    /**
     * @throws InvalidDataException|Throwable
     */
    private function copyProcedureRelatedFilesFromBlueprint(string $blueprintId, Procedure $newProcedure): void
    {
        $blueprint = $this->procedureRepository->findOneBy(['id' => $blueprintId]);
        $newFiles = [];

        foreach ($blueprint->getFiles() as $procedureFile) {
            $newFile = $this->fileService->createCopyOfFile($procedureFile->getFileString(), $newProcedure->getId());
            if (null !== $newFile) {
                $newFiles[] = $newFile;
            }
        }

        $newFileCollection = new ArrayCollection($newFiles);
        $newProcedure->setFiles($newFileCollection);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ORMException
     */
    private function copyFromBlueprint(array $data, string $blueprintId, Procedure $newProcedure): Procedure
    {
        if (!isset($data['master']) || !is_bool($data['master'])) {
            throw new InvalidArgumentException('Cannot copy from blueprint without (boolean) value for "master" in $data');
        }

        // do not copy email if $data is a blueprint
        if (!$data['master']) {
            // copy agencyExtraEmailAddresses from blueprint to new procedure
            $newProcedure = $this->procedureRepository->copyAgencyExtraEmailAddresses($blueprintId, $newProcedure);
            // set the procedure type and copy its definitions into the procedure
            $newProcedure->setProcedureType($data['procedureType']);
            $newProcedure = $this->procedureTypeService->copyProcedureTypeContent(
                $newProcedure->getProcedureType(),
                $newProcedure
            );
            $this->procedureRepository->updateObject($newProcedure);
        }

        // copy ExportFieldsConfiguration
        $this->fieldConfigurator->copy($blueprintId, $newProcedure);

        // copy Topics:
        $this->tagTopicRepository->copy($blueprintId, $newProcedure);

        $this->copyBoilerplates($blueprintId, $newProcedure);

        // copy gisCategories (incl. gis):
        $this->gisLayerCategoryRepository->copy($blueprintId, $newProcedure->getId());

        // copy news:
        $this->newsRepository->copy($blueprintId, $newProcedure->getId());

        // copy elements
        $this->elementsService->copy($blueprintId, $newProcedure);

        // copy procedure related files
        $this->copyProcedureRelatedFilesFromBlueprint($blueprintId, $newProcedure);

        // copy NotificationReceiver (Email to counties T433)
        $this->notificationReceiverRepository->copy($blueprintId, $newProcedure->getId());

        // copy demosplan\DemosPlanCoreBundle\Entity\Setting.php: (not procedure.settings)
        $this->settingRepository->copy($blueprintId, $newProcedure);

        $this->copyPlaces($blueprintId, $newProcedure);

        /** @var NewProcedureAdditionalDataEvent $additionalDataEvent */
        $additionalDataEvent = $this->eventDispatcher->dispatch(new NewProcedureAdditionalDataEvent($newProcedure));

        return $additionalDataEvent->getProcedure();
    }

    private function copyPlaces(string $sourceProcedureTemplateId, Procedure $targetProcedure): void
    {
        $sourcePlaces = $this->placeRepository->findBy(['procedure' => $sourceProcedureTemplateId]);

        $newPlaces = array_map(function (Place $sourcePlace) use ($targetProcedure): Place {
            $newPlace = new Place(
                $targetProcedure,
                $sourcePlace->getName(),
                $sourcePlace->getSortIndex()
            );
            $newPlace->setDescription($sourcePlace->getDescription());
            $violations = $this->validator->validate($newPlace);
            if (0 !== $violations->count()) {
                throw ViolationsException::fromConstraintViolationList($violations);
            }

            return $newPlace;
        }, $sourcePlaces);

        $this->placeRepository->persistEntities($newPlaces);
    }
}
