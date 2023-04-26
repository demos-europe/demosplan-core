<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\PreNewProcedureCreatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Form\Procedure\AbstractProcedureFormTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\ServiceStorageInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PreNewProcedureCreatedEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\ContentMandatoryFieldsException;
use demosplan\DemosPlanCoreBundle\Exception\CriticalConcernException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ArrayHelper;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\LegacyFlashMessageCreator;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedureReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanProcedureBundle\Exception\PreNewProcedureCreatedEventConcernException;
use demosplan\DemosPlanProcedureBundle\Repository\NotificationReceiverRepository;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use demosplan\DemosPlanUserBundle\Logic\OrgaService;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function is_string;

/**
 * Speicherung von Planverfahren.
 */
class ServiceStorage implements ServiceStorageInterface
{
    /**
     * @var ContentService
     */
    protected $contentService;

    /**
     * @var ProcedureHandler
     */
    protected $procedureHandler;

    /**
     * @var CustomerService
     */
    protected $customerService;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ProcedureCategoryService
     */
    protected $procedureCategoryService;
    /**
     * @var OrgaService
     */
    private $orgaService;

    /**
     * @var ProcedureTypeService
     */
    private $procedureTypeService;

    /**
     * @var string
     */
    protected $masterProcedurePhase;
    /**
     * @var ReportService
     */
    private $reportService;
    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var MasterTemplateService
     */
    private $masterTemplateService;

    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    /**
     * @var ProcedureService
     */
    private $procedureService;

    /**
     * @var ProcedureReportEntryFactory
     */
    private $procedureReportEntryFactory;

    /**
     * @var NotificationReceiverRepository
     */
    private $notificationReceiverRepository;

    /**
     * @var MessageBag
     */
    private $messageBag;
    /**
     * @var PermissionsInterface
     */
    private $permissions;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var LegacyFlashMessageCreator
     */
    private $legacyFlashMessageCreator;
    /**
     * @var ArrayHelper
     */
    private $arrayHelper;

    public function __construct(
        ArrayHelper $arrayHelper,
        ContentService $contentService,
        CurrentUserInterface $currentUser,
        CustomerService $customerService,
        EventDispatcherInterface $eventDispatcher,
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        LegacyFlashMessageCreator $legacyFlashMessageCreator,
        MasterTemplateService $masterTemplateService,
        MessageBag $messageBag,
        NotificationReceiverRepository $notificationReceiverRepository,
        OrgaService $orgaService,
        PermissionsInterface $permissions,
        ProcedureCategoryService $procedureCategoryService,
        ProcedureHandler $procedureHandler,
        ProcedureReportEntryFactory $procedureReportEntryFactory,
        ProcedureService $procedureService,
        ProcedureTypeService $procedureTypeService,
        ParameterBagInterface $parameterBag,
        ReportService $reportService,
        TranslatorInterface $translator
    ) {
        $this->arrayHelper = $arrayHelper;
        $this->contentService = $contentService;
        $this->currentUser = $currentUser;
        $this->customerService = $customerService;
        $this->eventDispatcher = $eventDispatcher;
        $this->globalConfig = $globalConfig;
        $this->legacyFlashMessageCreator = $legacyFlashMessageCreator;
        $this->logger = $logger;
        $this->masterProcedurePhase = $parameterBag->get('master_procedure_phase');
        $this->masterTemplateService = $masterTemplateService;
        $this->messageBag = $messageBag;
        $this->notificationReceiverRepository = $notificationReceiverRepository;
        $this->orgaService = $orgaService;
        $this->permissions = $permissions;
        $this->procedureCategoryService = $procedureCategoryService;
        $this->procedureHandler = $procedureHandler;
        $this->procedureReportEntryFactory = $procedureReportEntryFactory;
        $this->procedureService = $procedureService;
        $this->procedureTypeService = $procedureTypeService;
        $this->reportService = $reportService;
        $this->translator = $translator;
    }

    /**
     * Create new Procedure.
     *
     * @param array<int, mixed> $data
     *
     * @throws ContentMandatoryFieldsException
     * @throws PreNewProcedureCreatedEventConcernException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws ReflectionException
     * @throws \demosplan\DemosPlanCoreBundle\Exception\MessageBagException
     * @throws \demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException
     * @throws \demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException
     * @throws CriticalConcernException
     */
    public function administrationNewHandler(array $data, string $currentUserId): Procedure
    {
        /** @var PreNewProcedureCreatedEvent $procedureFileSubmitEvent */
        $procedureFileSubmitEvent = $this->eventDispatcher->dispatch(
            new PreNewProcedureCreatedEvent($data),
            PreNewProcedureCreatedEventInterface::class
        );
        $criticalEventConcernMessages = $procedureFileSubmitEvent->getCriticalEventConcernMessages();
        if ([] !== $criticalEventConcernMessages) {
            $preNewProcedureCreatedEventConcernException = new PreNewProcedureCreatedEventConcernException();
            $preNewProcedureCreatedEventConcernException->setMessages($criticalEventConcernMessages);
            throw $preNewProcedureCreatedEventConcernException;
        }
        $eventData = $procedureFileSubmitEvent->getProcedureData();
        if (null !== $eventData) {
            $data = $eventData;
        }
        $procedureData = [];

        if (!array_key_exists('action', $data) || 'new' !== $data['action']) {
            throw new InvalidArgumentException('Wrong or missing action.');
        }

        // check for mandatory fields which should be programmatically added
        $mandatoryFields = ['orgaId', 'orgaName', 'r_copymaster'];
        if (array_key_exists('r_copymaster', $data) &&
            $this->masterTemplateService->getMasterTemplateId() !== $data['r_copymaster']) {
            // r_procedure_type is only required if an actual procedure is created,
            // procedure blueprints do not need a procedure type.
            if (!array_key_exists('r_master', $data) || 'true' !== $data['r_master']) {
                $mandatoryFields[] = 'r_procedure_type';
            }
        }
        foreach ($mandatoryFields as $mandatoryField) {
            if (!array_key_exists($mandatoryField, $data) || '' === trim($data[$mandatoryField])) {
                throw new InvalidArgumentException(sprintf('Field %s must be set', $mandatoryField));
            }
        }

        // Prüfe Pflichtfelder
        $mandatoryErrors = [];
        if (!array_key_exists('r_name', $data) || '' === trim($data['r_name'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('name'),
                    ]
                ),
            ];
        }

        if (!array_key_exists(AbstractProcedureFormTypeInterface::AGENCY_MAIN_EMAIL_ADDRESS, $data)) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('email.procedure.agency'),
                    ]
                ),
            ];
        }

        // check if an MMPB is already existing for customer in case of customer is set
        if (array_key_exists('customer', $data)
            && $data['customer'] instanceof Customer
            && $this->procedureService->isCustomerMasterBlueprintExisting($data['customer']->getId())
        ) {
            $this->messageBag->add(
                'warning', 'customer.master.blueprint.already.exists',
                ['customerName' => $data['customer']->getName()]);
        }

        if (array_key_exists('r_name', $data)) {
            $procedureData['name'] = $data['r_name'];
            $procedureData['externalName'] = $data['r_name'];
        }

        if (array_key_exists('r_master', $data)) {
            if ('true' == $data['r_master']) {
                $procedureData['master'] = true;
                $procedureData['settings']['sendMailsToCounties'] = false;
            } else {
                $procedureData['master'] = false;
            }
        }

        if (array_key_exists('r_procedure_type', $data)) {
            $procedureTypeId = $data['r_procedure_type'];
            if (!is_string($procedureTypeId)) {
                throw new InvalidArgumentException('invalid procedureTypeId value');
            }
            $procedureType = $this->procedureTypeService->getProcedureType($procedureTypeId);
            $procedureData['procedureType'] = $procedureType;
            $procedureData['settings']['mapHint'] = $procedureType->getProcedureUiDefinition()->getMapHintDefault();
        }

        if (array_key_exists('r_startdate', $data) && '----' != $data['r_startdate']) {
            $procedureData['startDate'] = $data['r_startdate'];
            $procedureData['publicParticipationStartDate'] = $data['r_startdate'];
        }

        if (array_key_exists('r_enddate', $data) && '----' != $data['r_enddate']) {
            $procedureData['endDate'] = $data['r_enddate'];
            $procedureData['publicParticipationEndDate'] = $data['r_enddate'];
        }

        if (isset($procedureData['endDate'])
            && '' !== $procedureData['endDate']
            && strtotime($procedureData['endDate']) < strtotime($procedureData['startDate'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.date.endbeforestart'),
            ];
        }

        if (0 < count($mandatoryErrors)) {
            $this->legacyFlashMessageCreator->setFlashMessages($mandatoryErrors);

            $messages = collect($mandatoryErrors)->map(
                function ($array) {
                    return collect($array)->only('message');
                })->flatten()->toArray();

            throw new ContentMandatoryFieldsException($messages, 'Mandatory fields are missing');
        }

        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, 'desc');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, 'externalDesc');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, 'copymaster');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, 'plisId');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, 'publicParticipationContact');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, 'publicParticipationPublicationEnabled');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, AbstractProcedureFormTypeInterface::AGENCY_MAIN_EMAIL_ADDRESS, '');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, AbstractProcedureFormTypeInterface::AGENCY_EXTRA_EMAIL_ADDRESSES, '');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, AbstractProcedureFormTypeInterface::ALLOWED_SEGMENT_ACCESS_PROCEDURE_IDS, '');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, 'customer', '');
        $procedureData = $this->arrayHelper->addToArrayIfKeyExists($procedureData, $data, 'xtaPlanId', '');
        $procedureData['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedureData['settings'] ?? [], $data, 'mapExtent');

        $procedureData['orgaId'] = $data['orgaId'];
        $procedureData['orgaName'] = $data['orgaName'];

        // Phase Konfiguration der Öffentlichkeit
        $procedureData['publicParticipationPhase'] = $this->masterProcedurePhase;
        $procedureData['copymaster'] = $data['r_copymaster'];
        $procedureData['procedureCoupleToken'] = $this->handleTokenField($data['procedureCoupleToken'] ?? null);

        return $this->procedureService->addProcedureEntity($procedureData, $currentUserId);
    }

    /**
     * Save Procedure data.
     *
     * @param array $data
     * @param bool  $checkMandatoryErrors
     *
     * @return array|false
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \demosplan\DemosPlanCoreBundle\Exception\MessageBagException
     * @throws \demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException
     */
    public function administrationEditHandler($data, $checkMandatoryErrors = true)
    {
        $procedure = [];
        $procedure['settings'] = [];

        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('edit' !== $data['action']) {
            return false;
        }

        // Prüfe Pflichtfelder
        $mandatoryErrors = [];
        if ($this->permissions->hasPermission('feature_institution_participation')) {
            if (!array_key_exists('r_name', $data) || '' === trim($data['r_name'])) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                        'mandatoryError',
                        [
                            'fieldLabel' => $this->translator->trans('name'),
                        ]
                    ),
                ];
            }
            if (!array_key_exists('r_phase', $data) || '' === trim($data['r_phase'])) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                        'mandatoryError',
                        [
                            'fieldLabel' => $this->translator->trans('phase'),
                        ]
                    ),
                ];
            }
        }

        $currentProcedure = $this->procedureHandler->getProcedureWithCertainty($data['r_ident']);
        $isBlueprint = $currentProcedure->getMaster();
        $isNotInConfiguration = array_key_exists('r_phase', $data) && 'configuration' !== $data['r_phase'];
        $isNotInPublicConfiguration = array_key_exists('r_publicParticipationPhase', $data) && 'configuration' !== $data['r_publicParticipationPhase'];
        if ($this->permissions->hasPermission('feature_procedure_require_location') && !$isBlueprint && ($isNotInPublicConfiguration || $isNotInConfiguration)) {
            if ((array_key_exists('r_coordinate', $data) && '' == $data['r_coordinate']) || !array_key_exists('r_coordinate', $data)) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                        'mandatoryError',
                        [
                            'fieldLabel' => $this->translator->trans('wizard.topic.location'),
                        ]
                    ),
                ];
            }
        }

        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'ident');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'name');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'shortUrl');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'oldSlug');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'desc');

        // T15644: in case of deselected customer master blue print, the key 'r_customerMasterBlueprint' will be missing:
        $procedure['customer'] = null;
        if (array_key_exists('r_customerMasterBlueprint', $data)) { // soll current customer gesetzt werden?
            $currentCustomer = $this->customerService->getCurrentCustomer();
            if ($currentCustomer->getId() !== $currentProcedure->getCustomerId()// ist cc noch nicht gesetzt?
                && $this->procedureService->isCustomerMasterBlueprintExisting($currentCustomer->getId())) {
                $this->messageBag->add(
                    'warning',
                    'customer.master.blueprint.already.exists',
                    ['customerName' => $currentCustomer->getName()]
                );
            } else {
                $procedure['customer'] = $currentCustomer;
            }
        }

        // save authorizedUsers only if user can choose them in interface
        if ($this->globalConfig->hasProcedureUserRestrictedAccess() && $this->permissions->hasPermission('feature_procedure_user_restrict_access_edit')) {
            $procedure['authorizedUsers'] = [];
            $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'authorizedUsers');
            // get current user + add current user to authorizedUsers:
            $procedure['authorizedUsers'][] = $this->currentUser->getUser()->getId();
        }

        $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'links');
        $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'emailTitle');

        // T9581
        if ($this->permissions->hasPermission('feature_procedure_legal_notice_write')) {
            $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'legalNotice');
        }

        if (array_key_exists('r_phase', $data)) {
            $procedure['phase'] = $data['r_phase'];
            $procedure['closed'] = 'closed' === $data['r_phase'];

            // T9581 T9838: remove legal notice on change r_phase (toeb phase)
            // check for change of toeb phase
            if ($this->permissions->hasPermission('feature_procedure_legal_notice_write') && $currentProcedure->getPhase() != $procedure['phase']) {
                $procedure['settings']['legalNotice'] = ''; // '' == default value
                $this->messageBag->add('warning', 'procedure.legalnotice.cleared');
            }
        }
        if (array_key_exists('r_startdate', $data) && '----' != $data['r_startdate']) {
            $procedure['startDate'] = $data['r_startdate'];
        }

        if (array_key_exists('r_enddate', $data) && '----' != $data['r_enddate']) {
            $procedure['endDate'] = $data['r_enddate'];
        }
        if (array_key_exists('r_logo', $data) && '' !== $data['r_logo']) {
            $procedure['logo'] = $data['r_logo'];
        }
        if (array_key_exists('delete_logo', $data)) {
            $procedure['logo'] = '';
        }

        if (array_key_exists('r_dataInputOrga', $data)) {
            $procedure['dataInputOrga'] = $data['r_dataInputOrga'];
        } else {
            $procedure['dataInputOrga'] = [];
        }
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'externalName');
        // Falls keine Verfahrensname für die Öffentlichkeit angelegt wird, dann speicher den allgem. Name als external Name
        if (empty($data['r_externalName']) && array_key_exists('name', $procedure) && '' !== $procedure['name']) {
            $procedure['externalName'] = $procedure['name'];
        }

        if (array_key_exists('r_externalDesc', $data)) {
            // Strippe Newlines, weil die die Javascriptausgabe in den Popups zerschiessen
            $singleLineExternalDesc = preg_replace('/(?>\r\n|\n|\r)/s', '', nl2br($data['r_externalDesc']));
            $procedure['externalDesc'] = $singleLineExternalDesc;
        }

        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, AbstractProcedureFormTypeInterface::AGENCY_EXTRA_EMAIL_ADDRESSES, '');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, AbstractProcedureFormTypeInterface::ALLOWED_SEGMENT_ACCESS_PROCEDURE_IDS, '');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, AbstractProcedureFormTypeInterface::AGENCY_MAIN_EMAIL_ADDRESS, '');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'locationName');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'locationPostCode');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'publicParticipationContact');

        if ($this->permissions->hasPermission('feature_procedure_categories_edit') &&
            array_key_exists('r_procedure_categories', $data)) {
            // if empty string, reset categories
            if ('' === $data['r_procedure_categories']) {
                $data['r_procedure_categories'] = [];
            }
            $procedure['procedure_categories'] =
                $this->procedureCategoryService->
                transformProcedureCategoryIdsToObjects($data['r_procedure_categories']);
        }

        if (array_key_exists('r_publicParticipationPhase', $data)) {
            $procedure['publicParticipationPhase'] = $data['r_publicParticipationPhase'];
            // Das Backend benötigt die Info, ob Beteiligugnsphase aktiv ist für das Steuern der Rechte und Aktionen
            $publicParticipationPhases = $this->globalConfig->getExternalPhasesAssoc('read||write');
            if (array_key_exists($data['r_publicParticipationPhase'], $publicParticipationPhases)) {
                $procedure['publicParticipation'] = true;
            } else {
                $procedure['publicParticipation'] = false;
            }
        }

        if (array_key_exists('r_publicParticipationStartDate', $data) && '----' !== $data['r_publicParticipationStartDate']) {
            $procedure['publicParticipationStartDate'] = $data['r_publicParticipationStartDate'];
            // T16467 in case of institution participation is disabled: copy value to date field of institution
            if (!$this->permissions->hasPermission('feature_institution_participation')) {
                $procedure['startDate'] = $data['r_publicParticipationStartDate'];
            }
        }

        if (array_key_exists('r_publicParticipationEndDate', $data) && '----' !== $data['r_publicParticipationEndDate']) {
            $procedure['publicParticipationEndDate'] = $data['r_publicParticipationEndDate'];
            // T16467 in case of institution participation is disabled: copy value to date field of institution
            if (!$this->permissions->hasPermission('feature_institution_participation')) {
                $procedure['endDate'] = $data['r_publicParticipationEndDate'];
            }
        }

        if (array_key_exists('r_publicParticipationPublicationEnabled', $data)) {
            $procedure['publicParticipationPublicationEnabled'] = true;
        } else {
            $procedure['publicParticipationPublicationEnabled'] = false;
        }

        // liegt das Enddatum vor dem Startdatum?
        if (isset($procedure['endDate']) && strtotime($procedure['endDate']) < strtotime($procedure['startDate'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.date.endbeforestart'),
            ];
        }

        // liegt das Enddatum vor dem Startdatum der öffentlichen Beteiligung?
        if (isset($procedure['publicParticipationEndDate']) && strtotime($procedure['publicParticipationEndDate']) < strtotime($procedure['publicParticipationStartDate'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.date.endbeforestart'),
            ];
        }

        $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'coordinate');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'municipalCode');
        $procedure = $this->arrayHelper->addToArrayIfKeyExists($procedure, $data, 'ars');

        $phaseAutoSwitchAllowed = $this->permissions->hasPermission('feature_auto_switch_procedure_phase');

        // check for autoswitch mandatory fields
        if (array_key_exists('r_autoSwitch', $data) && $phaseAutoSwitchAllowed) {
            $fieldsToCheck = [
                'r_designatedSwitchDate' => 'procedure.phase.autoswitch.date',
                'r_designatedPhase'      => 'procedure.phase.autoswitch.targetphase',
                'r_designatedEndDate'    => 'procedure.phase.autoswitch.enddate',
            ];

            [$hasMandatoryAutoSwitchError, $mandatoryErrors] = $this->checkSwitchDateMandatoryFields(
                $data,
                $fieldsToCheck,
                $mandatoryErrors
            );

            if (!$hasMandatoryAutoSwitchError) {
                [$hasMandatoryAutoSwitchError, $mandatoryErrors] = $this->checkSwitchDateValidFields(
                    $data['r_designatedSwitchDate'],
                    $data['r_designatedEndDate'],
                    $mandatoryErrors
                );
            }

            if (!$hasMandatoryAutoSwitchError && $this->permissions->hasPermission('feature_auto_switch_procedure_phase')) {
                $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'designatedSwitchDate');
                $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'designatedPhase');
                $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'designatedEndDate');
            }
        } else {
            // no autoswitchdate set, remove all Fields
            $procedure['settings']['designatedSwitchDate'] = null;
            $procedure['settings']['designatedPhase'] = null;
            $procedure['settings']['designatedEndDate'] = null;
        }

        // check for autoswitch mandatory fields public phase
        if (array_key_exists('r_autoSwitchPublic', $data) && $phaseAutoSwitchAllowed) {
            $fieldsToCheck = [
                'r_designatedPublicSwitchDate' => 'procedure.phase.autoswitch.date',
                'r_designatedPublicPhase'      => 'procedure.phase.autoswitch.targetphase',
                'r_designatedPublicEndDate'    => 'procedure.phase.autoswitch.enddate',
            ];

            [$hasMandatoryPublicAutoSwitchError, $mandatoryErrors] = $this->checkSwitchDateMandatoryFields(
                $data,
                $fieldsToCheck,
                $mandatoryErrors
            );

            if (!$hasMandatoryPublicAutoSwitchError) {
                [$hasMandatoryPublicAutoSwitchError, $mandatoryErrors] = $this->checkSwitchDateValidFields(
                    $data['r_designatedPublicSwitchDate'],
                    $data['r_designatedPublicEndDate'],
                    $mandatoryErrors
                );
            }

            // if no errors occurred, save Values
            if (!$hasMandatoryPublicAutoSwitchError) {
                $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'designatedPublicSwitchDate');
                $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'designatedPublicPhase');
                $procedure['settings'] = $this->arrayHelper->addToArrayIfKeyExists($procedure['settings'], $data, 'designatedPublicEndDate');
            }
        } else {
            // no autoswitchdate set, remove all Fields
            $procedure['settings']['designatedPublicSwitchDate'] = null;
            $procedure['settings']['designatedPublicPhase'] = null;
            $procedure['settings']['designatedPublicEndDate'] = null;
        }

        if ($this->permissions->hasPermission('feature_statement_notify_counties')) {
            if (array_key_exists('r_sendMailsToCounties', $data)) {
                $procedure['settings']['sendMailsToCounties'] = true;

                // Validate receiverss
                if (array_key_exists('r_receiver', $data)) {
                    $validReceivers = [];

                    foreach ($data['r_receiver'] as $id => $receiver) {
                        if (false !== filter_var($receiver, FILTER_VALIDATE_EMAIL)) {
                            $notificationReceiver = $this->notificationReceiverRepository->get($id);
                            $notificationReceiver->setEmail($receiver);
                            $validReceivers[] = $notificationReceiver;
                        } else {
                            $this->messageBag->add('warning', 'procedure.notifyCountry.setEmails.invalidEmail.notSaved');
                        }
                    }
                    // Add to procedure
                    $procedure['notificationReceivers'] = $validReceivers;
                }
            } else {
                $procedure['settings']['sendMailsToCounties'] = false;
            }
        }

        if ($checkMandatoryErrors && 0 < count($mandatoryErrors)) {
            $this->legacyFlashMessageCreator->setFlashMessages($mandatoryErrors);

            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }

        $data['r_agency'] = $data['r_agency'] ?? [];
        // Überprüfe das Eingabefeld zum Planungsbüro wenn die Berechtigung gesetzt ist
        if ($this->permissions->hasPermission('field_procedure_adjustments_planning_agency')) {
            $procedure['planningOffices'] = $data['r_agency'];
        }
        if ($this->permissions->hasPermission('field_procedure_pictogram')) {
            if (array_key_exists('r_pictogram', $data)
             && '' !== $data['r_pictogram']) {
                $procedure['settings']['pictogram'] = $data['r_pictogram'];
            }
            if (array_key_exists('r_deletePictogram', $data)) {
                $procedure['settings']['pictogram'] = '';
            }
        }

        // Add exportSettings to procedure
        if (array_key_exists('r_export_settings', $data)) {
            $procedure['exportSettings'] = $data['r_export_settings'];
        }

        // If no fieldCompleteions given, we have to assume, that all checkboxes are unchecked
        if (false === array_key_exists('fieldCompletions', $data)) {
            $data['fieldCompletions'] = [];
        }
        // Set flags, which indicates status of field of procedure.
        $this->contentService->setProcedureFieldCompletions($procedure['ident'], $data['fieldCompletions']);

        return $this->procedureService->updateProcedure($procedure);
    }

    /**
     * @param array  $data
     * @param string $procedureID
     *
     * @return array
     *
     * @throws Exception
     */
    public function administrationGlobalGisHandler($data, $procedureID)
    {
        $procedure = [];

        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('mapglobals' !== $data['action']) {
            return false;
        }

        $procedure['ident'] = $procedureID;

        // Array auf
        if (array_key_exists('r_mapExtent', $data)) {
            $procedure['settings']['mapExtent'] = $data['r_mapExtent'];
        }

        if (array_key_exists('r_startScale', $data)) {
            $procedure['settings']['startScale'] = $data['r_startScale'];
        }

        if (array_key_exists('r_boundingBox', $data)) {
            $procedure['settings']['boundingBox'] = $data['r_boundingBox'];
        }

        if (array_key_exists('r_informationUrl', $data)) {
            $procedure['settings']['informationUrl'] = $data['r_informationUrl'];
        }

        if (array_key_exists('r_defaultLayer', $data)) {
            $procedure['settings']['defaultLayer'] = $data['r_defaultLayer'];
        }

        if (array_key_exists('r_copyright', $data)) {
            $procedure['settings']['copyright'] = $data['r_copyright'];
        }

        if (array_key_exists('r_coordinate', $data)) {
            $procedure['settings']['coordinate'] = $data['r_coordinate'];
        }

        if (array_key_exists('r_territory', $data)) {
            $procedure['settings']['territory'] = $data['r_territory'];
        }

        if ($this->permissions->hasPermission('feature_layer_groups_alternate_visibility')) {
            // in case of disabling, key will be not exist
            $enabled = array_key_exists('r_enable_layer_groups_alternate_visibility', $data);

            $newSetting = [
                'content'     => $enabled,
                'procedureId' => $procedureID, ];

            $this->contentService->setSetting('layerGroupsAlternateVisibility', $newSetting);
        }

        $procedure['settings']['scales'] = [];
        if (array_key_exists('r_scales', $data)) {
            $procedure['settings']['scales'] = $data['r_scales'];
        }

        if (isset($data['r_currentMapExtent']) && $data['r_currentMapExtent'] != $data['r_mapExtent']) {
            try {
                $report = $this->procedureReportEntryFactory->createMapExtendUpdateEntry(
                    $procedureID,
                    $data['r_mapExtent']
                );
                $this->reportService->persistAndFlushReportEntries($report);
            } catch (Exception $e) {
                $this->logger->warning('Es konnte kein Protokolleintrag zur Änderung des Startkartenausschnitts erstellt werden. ', [$e]);
            }
        }

        return $this->procedureService->updateProcedure($procedure);
    }

    /**
     * Die Felder 'emailText' && 'emailTitle' && 'emailCc' werden aktualisiert.
     *
     * @param array $data
     *
     * @return bool|array unknown
     *
     * @throws Exception
     */
    public function updateEmailTextHandler($data, string $procedureId)
    {
        $procedure = [];

        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('updateEmailText' !== $data['action']) {
            return false;
        }

        $procedure['ident'] = $procedureId;

        // check if E-Mail addresses are submitted via the CC-field
        if (array_key_exists('r_emailCc', $data) && 0 < count($data['r_emailCc'])) {
            $checkedMails = [];
            // check every given E-Mail address
            foreach ($data['r_emailCc'] as $mail) {
                // delete potential blanks at the start/end of each string
                $mailForCc = trim($mail);
                // check if the individual address is valid
                if (filter_var($mailForCc, FILTER_VALIDATE_EMAIL)) {
                    // add this valid address to the list which is going to be persisted
                    $checkedMails[] = $mailForCc;
                }
            }
            // Concatenate the list of valid E-Mail addresses to a string and persist it
            $procedure['settings']['emailCc'] = implode(', ', $checkedMails);
            $this->procedureService->updateProcedure($procedure);
        }
        // pass on an empty string if the CC-field is empty (deletes the already saved addresses)
        if (array_key_exists('r_emailCc', $data) && 0 === count($data['r_emailCc'])) {
            $procedure['settings']['emailCc'] = '';
        }

        // Array auf
        if (array_key_exists('r_emailTitle', $data)) {
            $procedure['settings']['emailTitle'] = trim($data['r_emailTitle']);
        }

        if (array_key_exists('r_emailText', $data)) {
            $procedure['settings']['emailText'] = trim($data['r_emailText']);
        }

        return $this->procedureService->updateProcedure($procedure);
    }

    /**
     * Die Felder 'planDrawText' && 'planDrawPDF' werden aktualisiert.
     *
     * @param array  $data
     * @param string $procedureID
     *
     * @return array
     */
    public function updatePlanDrawHandler($data, $procedureID)
    {
        $procedure = [];

        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('updatePlan' !== $data['action']) {
            return false;
        }

        $procedure['ident'] = $procedureID;

        // Array auf
        if (array_key_exists('r_planDrawText', $data)) {
            $procedure['settings']['planDrawText'] = $data['r_planDrawText'];
        }

        if (array_key_exists('r_planDrawPDF', $data)) {
            if (isset($data['r_planDrawPDF']) && is_string($data['r_planDrawPDF'])) {
                $procedure['settings']['planDrawPDF'] = $data['r_planDrawPDF'];
            }
            if (isset($data['r_planDrawPDF']) && is_array($data['r_planDrawPDF'])) {
                return false;
            }
        }

        if (array_key_exists('r_planDrawDelete', $data)) {
            // check, ob eine neue Datei hochgeladen werden soll
            if (array_key_exists('r_planDrawPDF', $data) && !isset($data['r_planDrawPDF'])) {
                $procedure['settings']['planDrawPDF'] = '';
            }
        }

        return $this->procedureService->updateProcedure($procedure);
    }

    /**
     * Die Felder 'planDrawText' && 'planDrawPDF' werden aktualisiert.
     *
     * @return array|bool unknown
     *
     * @throws Exception
     */
    public function updatePlanHandler(array $data, string $procedureID)
    {
        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('updatePlan' === $data['action']) {
            $settings = [];

            $settings = $this->arrayHelper->addToArrayIfKeyExists($settings, $data, 'planText');
            $settings = $this->arrayHelper->addToArrayIfKeyExists($settings, $data, 'mapHint');
            if (array_key_exists('mapHint', $settings)) {
                $mapHint = $settings['mapHint'];
                if (!is_string($mapHint)) {
                    throw new InvalidArgumentException('the value of r_mapHint must be a string');
                }
                if (ProcedureSettings::MAP_HINT_MIN_LENGTH > strlen($mapHint)) {
                    $emptyMapHintMessage = $this->translator->trans(
                        'map.hint.warning.tooshort',
                        ['minLength' => ProcedureSettings::MAP_HINT_MIN_LENGTH, 'maxLength' => ProcedureSettings::MAP_HINT_MAX_LENGTH]
                    );
                    $this->legacyFlashMessageCreator->setFlashMessages(
                        [['type' => 'warning', 'message' => $emptyMapHintMessage]]
                    );

                    return false;
                }
                if (ProcedureSettings::MAP_HINT_MAX_LENGTH < strlen($mapHint)) {
                    $emptyMapHintMessage = $this->translator->trans(
                        'map.hint.warning.toolong',
                        ['maxLength' => ProcedureSettings::MAP_HINT_MAX_LENGTH]
                    );
                    $this->legacyFlashMessageCreator->setFlashMessages(
                        [['type' => 'warning', 'message' => $emptyMapHintMessage]]
                    );

                    return false;
                }
            }

            if (isset($data['r_planPDF'])) {
                $settings = $this->arrayHelper->addToArrayIfKeyExists($settings, $data, 'planPDF');
            }

            // check, ob eine neue Datei hochgeladen werden soll
            if (array_key_exists('r_planDelete', $data)
                && array_key_exists('r_planPDF', $data)
                && !isset($data['r_planPDF'])) {
                $settings['planPDF'] = '';
            }

            if (isset($data['r_planningArea'])) {
                $settings = $this->arrayHelper->addToArrayIfKeyExists($settings, $data, 'planningArea');
            }

            $procedure = [
                'ident'    => $procedureID,
                'settings' => $settings,
            ];

            return $this->procedureService->updateProcedure($procedure);
        }

        return false;
    }

    /**
     * Add organisations of given $organisationIds to procedure of given $procedureId.
     *
     * @param string   $procedureId     - Identifies the procedure
     * @param string[] $organisationIds - Identifies the organisations to add
     *
     * @return bool - false in case of exception, otherwise true
     *
     * @throws Exception
     */
    public function addOrgaToProcedureHandler($procedureId, $organisationIds)
    {
        try {
            $procedure = $this->procedureService->getProcedure($procedureId);
            $organisations = [];

            foreach ($organisationIds as $organisationId) {
                $organisation = $this->orgaService->getOrga($organisationId);
                $organisations[] = $organisation;
            }

            $this->procedureService->addOrganisations($procedure, $organisations);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Detaches organisation of given $organisationId to procedure of given $procedureId.
     *
     * @param string   $procedureId     - Identifies the procedure
     * @param string[] $organisationIds - Identifies the organisation to detach
     *
     * @return bool - false in case of exception, otherwise true
     *
     * @throws Exception
     */
    public function detachOrganisationsFromProcedure($procedureId, $organisationIds)
    {
        $procedure = $this->procedureService->getProcedure($procedureId);
        $organisations = $this->orgaService->getOrganisationsByIds($organisationIds);

        return $this->procedureService->detachOrganisations($procedure, $organisations);
    }

    /**
     * Check auto switch phase fields for mandatory errors.
     *
     * @param array $data
     * @param array $fieldsToCheck
     * @param array $mandatoryErrors
     *
     * @return array<int, mixed>
     */
    protected function checkSwitchDateMandatoryFields(
        $data,
        $fieldsToCheck,
        $mandatoryErrors
    ): array {
        $hasMandatoryAutoSwitchError = false;
        foreach ($fieldsToCheck as $fieldToCheck => $fieldTranslationLabel) {
            if (!array_key_exists($fieldToCheck, $data) || '' === $data[$fieldToCheck]) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                        'mandatoryError',
                        [
                            'fieldLabel' => $this->translator->trans(
                                $fieldTranslationLabel
                            ),
                        ]
                    ),
                ];
                $hasMandatoryAutoSwitchError = true;
            }
        }

        return [$hasMandatoryAutoSwitchError, $mandatoryErrors];
    }

    /**
     * Check whether date Fields are valid.
     *
     * @param array<int, array> $mandatoryErrors
     *
     * @return array<int, mixed>
     */
    protected function checkSwitchDateValidFields(
        string $startDate,
        string $endDate,
        $mandatoryErrors
    ) {
        $hasMandatoryAutoSwitchError = false;

        $designatedSwitchDate = Carbon::createFromFormat(Carbon::ATOM, date(DATE_ATOM, strtotime($startDate)));
        $designatedSwitchEndDate = Carbon::createFromFormat('d.m.Y', $endDate);
        if (!$designatedSwitchDate->isFuture()) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator
                    ->trans('error.designated.switchdate.in.past'),
            ];
            $hasMandatoryAutoSwitchError = true;
        }
        if (!$designatedSwitchEndDate->isFuture()) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator
                    ->trans('error.designated.date.in.past'),
            ];
            $hasMandatoryAutoSwitchError = true;
        }
        if (!$designatedSwitchDate->lt($designatedSwitchEndDate)) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator
                    ->trans('error.designated.switchdate.after.enddate'),
            ];
            $hasMandatoryAutoSwitchError = true;
        }

        return [$hasMandatoryAutoSwitchError, $mandatoryErrors];
    }

    /**
     * @param ContentService $contentService
     */
    public function setContentService($contentService)
    {
        $this->contentService = $contentService;
    }

    public function setCustomerService(CustomerService $customerService): void
    {
        $this->customerService = $customerService;
    }

    protected function getEventDispatcher(): EventDispatcherPostInterface
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher(EventDispatcherPostInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Will return the given token if it is a non-empty string (after trimming whitespaces).
     *
     * Otherwise, `null` will be returned.
     *
     * Does *not* check the length of the token or the contained characters.
     *
     * @param mixed|null $token
     */
    private function handleTokenField($token): ?string
    {
        if (null === $token) {
            return null;
        }

        if (!is_string($token)) {
            throw new InvalidArgumentException('Token must be a string if given');
        }

        if ('' === trim($token)) {
            return null;
        }

        return $token;
    }
}
