<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\OrgaServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\EmailAddressInUseException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Report\OrganisationReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaTypeRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitablePublicAgencyResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OrgaResourceType;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\OrgaSignatureValueObject;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use demosplan\DemosPlanCoreBundle\ValueObject\User\DataProtectionOrganisation;
use demosplan\DemosPlanCoreBundle\ValueObject\User\ImprintOrganisation;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\QueryException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\FunctionInterface;
use Exception;
use ReflectionException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrgaService extends CoreService implements OrgaServiceInterface
{
    /** @var MailService */
    protected $mailService;

    /**
     * @var RoleService
     */
    protected $roleService;

    /**
     * @var AddressService
     */
    protected $addressService;

    /**
     * @var ContentService
     */
    protected $contentService;

    /**
     * @var PermissionsInterface
     */
    protected $permissions;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    public function __construct(
        AddressService $addressService,
        ContentService $contentService,
        private readonly CustomerRepository $customerRepository,
        private readonly DqlConditionFactory $conditionFactory,
        MailService $mailService,
        private readonly FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly InvitablePublicAgencyResourceType $invitablePublicAgencyResourceType,
        private readonly OrganisationReportEntryFactory $organisationReportEntryFactory,
        private readonly OrgaRepository $orgaRepository,
        private readonly OrgaResourceType $orgaResourceType,
        private readonly OrgaTypeRepository $orgaTypeRepository,
        PermissionsInterface $permissions,
        private readonly ReportService $reportService,
        RoleService $roleService,
        private readonly SortMethodFactory $sortMethodFactory,
        TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator,
        private readonly UserService $userService,
    ) {
        $this->addressService = $addressService;
        $this->contentService = $contentService;
        $this->permissions = $permissions;
        $this->roleService = $roleService;
        $this->tokenStorage = $tokenStorage;
        $this->mailService = $mailService;
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findOrgaBySlug(string $slug): Orga
    {
        return $this->orgaRepository->findOrgaBySlug($slug);
    }

    /**
     * Get non-deleted orgas that are accepted in the given customer as municipality.
     *
     * @return array<int, DataProtectionOrganisation>
     */
    public function getDataProtectionMunicipalities(Customer $customer): array
    {
        return $this->orgaRepository->getDataProtectionMunicipalities($customer);
    }

    /**
     * Get non-deleted orgas that are accepted in the given customer as municipality.
     *
     * @return array<int, ImprintOrganisation>
     */
    public function getImprintMunicipalities(Customer $customer): array
    {
        return $this->orgaRepository->getImprintMunicipalities($customer);
    }

    /**
     * @throws ConnectionException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws EmailAddressInUseException
     */
    public function createOrgaRegister(
        string $orgaName,
        string $phone,
        string $userFirstName,
        string $userLastName,
        string $email,
        Customer $customer,
        array $orgaTypeNames,
    ): Orga {
        $masterUserRoles = $this->roleService->getUserRolesByCodes([Role::ORGANISATION_ADMINISTRATION]);
        $masterUserRole = $masterUserRoles[0];

        if (!$this->userService->checkUniqueEmailAndLogin($email)) {
            $e = new EmailAddressInUseException(sprintf('The email address %s is already in use', $email));
            $e->setValue($email);
            throw $e;
        }

        return $this->orgaRepository->createOrgaRegistration(
            $orgaName,
            $phone,
            $userFirstName,
            $userLastName,
            $email,
            $customer,
            $masterUserRole,
            $orgaTypeNames
        );
    }

    /**
     * @throws Exception
     */
    public function sendRegistrationRequestConfirmation(string $emailFrom, string $userEmail, array $orgaTypeNames, string $customerName, string $userFirstName, string $userLastName, string $orgaName)
    {
        $orgaTypeLabels = $this->transformOrgaTypeNamesToLabels($orgaTypeNames);

        // Send email
        $this->mailService->sendMail(
            'orga_registration_request_confirmation',
            'de_DE',
            $userEmail,
            $emailFrom,
            '',
            '',
            'extern',
            ['orga_type' => implode(', ', $orgaTypeLabels), 'customer' => $customerName, 'firstname' => $userFirstName, 'lastname' => $userLastName, 'orga_name' => $orgaName]
        );
    }

    /**
     * @throws Exception
     */
    public function sendRegistrationAccepted(string $from, string $to, string $orgaTypeLabel, string $customerName, string $userFirstName, string $userLastName, string $orgaName)
    {
        // Send email
        $this->mailService->sendMail(
            'orga_registration_accepted',
            'de_DE',
            $to,
            $from,
            '',
            '',
            'extern',
            ['orga_type' => $orgaTypeLabel, 'customer' => $customerName, 'firstname' => $userFirstName, 'lastname' => $userLastName, 'orga_name' => $orgaName]
        );
    }

    /**
     * @throws Exception
     */
    public function sendRegistrationRejected(string $from, string $to, string $orgaTypeLabel, string $customerName, string $userFirstName, string $userLastName, string $orgaName)
    {
        // Send email
        $this->mailService->sendMail(
            'orga_registration_rejected',
            'de_DE',
            $to,
            $from,
            '',
            '',
            'extern',
            ['orga_type' => $orgaTypeLabel, 'customer' => $customerName, 'firstname' => $userFirstName, 'lastname' => $userLastName, 'orga_name' => $orgaName]
        );
    }

    // @improve T15377

    /**
     * Delete Orga. No wiping, real deleting. This is currently not the way we want to do it.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/deletion_of_entity_objects/ delete entity objects
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteOrga($entityId)
    {
        try {
            return $this->orgaRepository->delete($entityId);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen der Orga: ', [$e]);

            return false;
        }
    }

    /**
     * Delete the addresses of the given organisation.
     *
     * @param string $entityId - Identifies the organisation, whose addresses will be deleted
     *
     * @return bool true if successful deleted, otherwise false
     */
    public function deleteAddressesOfOrga($entityId)
    {
        try {
            /** @var Orga $organisation */
            $organisation = $this->orgaRepository->get($entityId);

            foreach ($organisation->getAddresses() as $address) {
                // remove addresses form organisation, to avoid undefined index
                // because doctrine will not do this, because there are no address sited relation, to use annotations
                $organisation->setAddresses([]);
                $this->addressService->deleteAddress($address->getId());
            }

            // remove addresses form organisation, to avoid undefined index
            // doctrine will not do this, because there are no address sited relation, to use annotations
            $organisation->setAddresses([]);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen der Adressen: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * @return Department[]
     *
     * @throws Exception
     */
    public function getDepartments(Orga $orga): array
    {
        try {
            return $orga->getDepartments()->toArray();
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der Abteilungsliste: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add a User to Organisation.
     *
     * @param string $orgaId
     *
     * @return Orga
     *
     * @throws Exception
     */
    public function orgaAddUser($orgaId, User $user)
    {
        try {
            $orga = $this->orgaRepository->addUser($orgaId, $user);
            $this->logger->info('Added User '.$user->getLogin().' to Orga '.$orgaId);

            return $orga;
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Update der Orga: ', [$e]);
            throw $e;
        }
    }

    /**
     * @return array<int, Orga>
     */
    public function getParticipants(): array
    {
        return $this->getOrganisations([
            $this->conditionFactory->propertyHasValue(1, $this->orgaResourceType->showname),
            $this->conditionFactory->propertyHasValue(true, $this->orgaResourceType->showlist),
        ]);
    }

    /**
     * Determines the count of organisation instances accepted in the given customer and returns
     * them sorted by the count value.
     *
     * The keys used are the translation keys corresponding to the logical meaning of each
     * organisation type:
     *
     * * `invitable_institution`: Institutionen
     * * `planningagency`: Planungsbüros
     * * `procedure.agency`: Verfahrensträger
     *
     * @return array<string, int<0, max>>
     */
    public function getOrgaCountByTypeTranslated(Customer $customerContext): array
    {
        return collect($this->getAcceptedOrgaCountByType($customerContext))
            ->mapWithKeys(fn (int $count, string $translationKey): array => [$this->translator->trans($translationKey) => $count])
            ->sort()
            ->all();
    }

    /**
     * Fetch **undeleted** {@link Orga} entities connected to the **current customer** sorted by their {@link Orga::$name}.
     *
     * The special {@link Orga} instance for citizens will **not** be included in the output.
     *
     * @param array<int, FunctionInterface<bool>> $additionalConditions must match for an entity to be returned
     *
     * @return array<int, Orga>
     *
     * @throws Exception
     */
    public function getOrganisations(array $additionalConditions = []): array
    {
        try {
            $conditions = [...$additionalConditions, ...$this->orgaResourceType->getMandatoryConditions()];
            $conditions[] = $this->conditionFactory->propertyHasNotValue(
                User::ANONYMOUS_USER_ORGA_ID,
                $this->orgaResourceType->id
            );
            $sortMethod = $this->sortMethodFactory->propertyAscending($this->orgaResourceType->name);
            $orgas = $this->orgaRepository->getEntities($conditions, [$sortMethod]);

            // add Notifications and submission types to entity
            array_map($this->loadMissingOrgaData(...), $orgas);

            return $orgas;
        } catch (Exception $e) {
            $this->logger->error('Fehler bei getList Orga:', [$e]);
            throw $e;
        }
    }

    /**
     * @return array<int, Orga>
     */
    public function getOrgasInCustomer(Customer $customer): array
    {
        $conditions = [
            $this->conditionFactory->propertyHasValue(
                $customer->getId(),
                ['statusInCustomers', 'customer']
            ),
            $this->conditionFactory->propertyHasValue(false, ['deleted']),
        ];
        $sortMethod = $this->sortMethodFactory->propertyAscending(['name']);

        return $this->orgaRepository->getEntities($conditions, [$sortMethod]);
    }

    /**
     * Get Count of accepted orga types for given customer.
     *
     * The keys correspond to the following german meanings:
     *
     * * `invitable_institution`: Institutionen
     * * `planningagency`: Planungsbüros
     * * `procedure.agency`: Verfahrensträger
     *
     * @return array{'procedure.agency': int<0, max>, 'planningagency': int<0, max>, 'invitable_institution': int<0, max>}
     */
    protected function getAcceptedOrgaCountByType(Customer $customerContext): array
    {
        $municipalityCount = $this->getAcceptedOrgaCount($customerContext, OrgaType::MUNICIPALITY);
        $planningAgencyCount = $this->getAcceptedOrgaCount($customerContext, OrgaType::PLANNING_AGENCY);
        $institutionCount = $this->getAcceptedOrgaCount($customerContext, OrgaType::PUBLIC_AGENCY);

        return [
            'invitable_institution' => $institutionCount,
            'planningagency'        => $planningAgencyCount,
            'procedure.agency'      => $municipalityCount,
        ];
    }

    /**
     * @param key-of<OrgaType::ORGATYPE_ROLE> $orgaTypeName
     *
     * @return int<0, max>
     */
    protected function getAcceptedOrgaCount(Customer $customerContext, string $orgaTypeName): int
    {
        $conditions = [
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomer::STATUS_ACCEPTED,
                $this->orgaResourceType->statusInCustomers->status
            ),
            $this->conditionFactory->propertyHasValue(
                $orgaTypeName,
                $this->orgaResourceType->statusInCustomers->orgaType->name
            ),
            // The resource type will already contain this restriction,
            // but we add it here too for clarity.
            $this->conditionFactory->propertyHasValue(
                $customerContext->getId(),
                $this->orgaResourceType->statusInCustomers->customer->id
            ),
        ];

        return $this->orgaResourceType->getEntityCount($conditions);
    }

    /**
     * Load existing notifications for the Organisation.
     *
     * @throws Exception
     */
    protected function loadOrgaNotifications(Orga $orga): void
    {
        $notifications = [];

        // Update Benachrichtigung neue Stellungnahme
        if ($this->permissions->hasPermission('feature_notification_statement_new')) {
            $settingNewStatement = $this->contentService->getSettings(
                'emailNotificationNewStatement',
                SettingsFilter::whereOrga($orga)->lock()
            );
            if (is_array($settingNewStatement) && 1 === count($settingNewStatement)) {
                $notifications['emailNotificationNewStatement'] = $settingNewStatement[0];
            }
        }

        // Update Benachrichtigung endende Beteiligungsphase
        if ($this->permissions->hasPermission('feature_notification_ending_phase')) {
            $settingEndingPhase = $this->contentService->getSettings(
                'emailNotificationEndingPhase',
                SettingsFilter::whereOrga($orga)->lock()
            );
            if (is_array($settingEndingPhase) && 1 === count($settingEndingPhase)) {
                $notifications['emailNotificationEndingPhase'] = $settingEndingPhase[0];
            }
        }
        $this->logger->debug('loadOrgaNotifications Loaded: '.DemosPlanTools::varExport($notifications, true));
        $orga->setNotifications($notifications);
    }

    /**
     * Load existing submissionType for the Organisation.
     *
     * @throws Exception
     */
    protected function loadOrgaSubmissionType(Orga $orga): void
    {
        $orga->setSubmissionType($this->globalConfig->getProjectSubmissionType());

        $settingSubmissionType = $this->contentService->getSettings(
            'submissionType',
            SettingsFilter::whereOrga($orga)->lock(),
            false
        );
        if (is_array($settingSubmissionType) && 1 === count($settingSubmissionType)) {
            $orga->setSubmissionType($settingSubmissionType[0]->getContent());
            $this->logger->debug('loadOrgaSubmissionType Loaded: '.DemosPlanTools::varExport($settingSubmissionType[0]->getContent(), true));
        }
    }

    /**
     * Get single orgaobject.
     *
     * @param string $orgaId
     *
     * @throws Exception
     */
    public function getOrga($orgaId): ?Orga
    {
        try {
            $orga = $this->orgaRepository->get($orgaId);
            // add Notifications to entity
            if (null !== $orga) {
                $this->loadMissingOrgaData($orga);
            }

            return $orga;
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der Orga: ', [$e]);
            throw $e;
        }
    }

    public function isOrgaType(string $orgaId, string $orgaType): bool
    {
        /** @var Orga $orga */
        $orga = $this->orgaRepository->find($orgaId);

        return $this->orgaRepository->isOrgaType($orga, $orgaType);
    }

    /**
     * Overrides the data of the given organisation.
     *
     * @param string $organisationId - Id of the organisation to wipe
     *
     * @return bool|Orga - updated orga
     */
    public function wipeOrganisation($organisationId)
    {
        try {
            return $this->orgaRepository
                ->wipe($organisationId);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen der Organisation: ', [$e]);

            return false;
        }
    }

    /**
     * Add Orga.
     *
     * @param array|Orga $data
     *
     * @throws Exception
     */
    public function addOrga($data): Orga
    {
        try {
            if ($data instanceof Orga) {
                $orga = $this->orgaRepository->addOrgaObject($data);
            } else {
                $orga = $this->orgaRepository->add($data);
            }

            // update ggf. Notifications
            $orga = $this->updateOrgaNotifications($orga, $data);

            return $this->updateOrgaSubmissionType($orga, $data);
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Anlegen der Orga: ', [$e]);
            throw $e;
        }
    }

    public function updateOrga(Orga $orga): Orga
    {
        return $this->orgaRepository->updateObject($orga);
    }

    /**
     * Update der Benachrictigungen der Organisation.
     *
     * @param Orga  $orga
     * @param array $data
     *
     * @return Orga
     */
    public function updateOrgaNotifications($orga, $data)
    {
        // Update Benachrichtigung neue Stellungnahme
        if ($this->permissions->hasPermission('feature_notification_statement_new')) {
            $data = $this->handleFormPostCheckbox($data, 'emailNotificationNewStatement');
            if (array_key_exists('emailNotificationNewStatement', $data)) {
                $notificationNewStatement = [
                    'orgaId'  => $orga->getId(),
                    'content' => true === $data['emailNotificationNewStatement'] ? 'true' : 'false',
                ];
                $this->contentService->setSetting('emailNotificationNewStatement', $notificationNewStatement);
            }
        }

        // Update Benachrichtigung endende Beteiligungsphase
        if ($this->permissions->hasPermission('feature_notification_ending_phase')) {
            $data = $this->handleFormPostCheckbox($data, 'emailNotificationEndingPhase');

            if (array_key_exists('emailNotificationEndingPhase', $data)) {
                $notificationEndingPhase = [
                    'orgaId'  => $orga->getId(),
                    'content' => true === $data['emailNotificationEndingPhase'] ? 'true' : 'false',
                ];
                $this->contentService->setSetting('emailNotificationEndingPhase', $notificationEndingPhase);
            }
        }

        // update entity
        $this->loadOrgaNotifications($orga);

        return $orga;
    }

    /**
     * Update Statement submission type.
     *
     * @param Orga  $orga
     * @param array $data
     *
     * @return Orga
     *
     * @throws Exception
     */
    public function updateOrgaSubmissionType($orga, $data)
    {
        // Update Orga Submission Type
        if (!$this->permissions->hasPermission('feature_change_submission_type')) {
            return $orga;
        }

        if (!array_key_exists('submission_type', $data)) {
            return $orga;
        }

        if ($this->globalConfig->getProjectSubmissionType() == $data['submission_type']) {
            // delete existing setting if exists
            $existingSetting = $this->contentService->getSettings(
                'submissionType',
                SettingsFilter::whereOrga($orga)->lock(),
                false
            );
            if (null === $existingSetting || [] === $existingSetting) {
                return $orga;
            }
            try {
                $this->contentService->deleteSetting($existingSetting[0]->getId());
            } catch (Exception) {
                // bad luck. Has been logged, go on ;-(
            }
        } else {
            $submissionTypeData = [
                'orgaId'  => $orga->getId(),
                'content' => $data['submission_type'],
            ];
            $this->contentService->setSetting('submissionType', $submissionTypeData);
        }

        // update entity
        $this->loadOrgaSubmissionType($orga);

        return $orga;
    }

    /**
     * Add a report entry about orga update and second if value of showlist changed.
     *
     * @param array  $data
     * @param string $showListBefore
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     */
    public function addReport(string $orgaId, $data, $showListBefore)
    {
        $message = [];
        $user = new AnonymousUser();
        if ($this->tokenStorage instanceof TokenStorageInterface && $this->tokenStorage->getToken() instanceof TokenInterface) {
            $user = $this->tokenStorage->getToken()->getUser();
        }

        if (!array_key_exists('showlist', $data)) {
            $data['showlist'] = false;
        }

        $message['ident'] = $orgaId;
        $message = array_merge($message, $data);

        $updateEntry = $this->organisationReportEntryFactory->createUpdateEntry(
            $orgaId,
            $message,
            ['data' => $data],
            $user
        );

        $this->reportService->persistAndFlushReportEntries($updateEntry);

        // only create report entry when user is at all allowed to update showlist entry
        if (array_key_exists('updateShowlist', $data) && $data['showlist'] !== $showListBefore) {
            if (!array_key_exists('showlistChangeReason', $data)) {
                $data['showlistChangeReason'] = '';
            }

            $data['ident'] = $orgaId;
            unset($data['address_phone'], $data['address_email'], $data['url'], $data['ccEmail2']);

            $showlistEntry = $this->organisationReportEntryFactory->createShowlistEntry(
                $user,
                $orgaId,
                ['data' => $data],
                $data['showlistChangeReason']
            );

            $this->reportService->persistAndFlushReportEntries($showlistEntry);
        }
    }

    /**
     * Completely deletes the logo of an organisation.
     *
     * @throws Exception
     */
    public function deleteLogoByOrgaId(string $orgaId): bool
    {
        /** @var Orga $orga */
        $orga = $this->orgaRepository->find($orgaId);
        $logo = $orga->getLogo();
        if (!$logo instanceof File) {
            return false;
        }

        // reset logo in orga in db
        $orga->setLogo(null);

        // delete file and file in db
        $this->fileService->deleteFile($logo->getHash());

        return true;
    }

    /**
     * Get List of Datainput Orgas.
     *
     * @return array Orga[]
     *
     * @throws Exception
     */
    public function getDataInputOrgaList()
    {
        try {
            return $this->orgaRepository->getDataInputOrgaList();
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der InputOrgas: ', [$e]);
            throw $e;
        }
    }

    /**
     * @return array<int, Orga>
     *
     * @throws Exception
     */
    public function getInvitablePublicAgencies(): array
    {
        return $this->invitablePublicAgencyResourceType->getEntities([], []);
    }

    /**
     * Get List of Planningoffices.
     *
     * @return array<int, Orga>|null
     *
     * @throws Exception
     */
    public function getPlanningOfficesList(Customer $customer): ?array
    {
        try {
            return $this->orgaRepository->getPlanningOfficesList($customer);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der PlanningOffices: ', [$e]);
            throw $e;
        }
    }

    /**
     * Organisationsdaten abrufen.
     *
     * @param array $organisationIds
     *
     * @return Orga[]
     *
     * @throws Exception
     */
    public function getOrganisationsByIds($organisationIds)
    {
        try {
            return $this->orgaRepository->findBy([
                'deleted' => false,
                'id'      => $organisationIds,
            ], [
                'name' => Criteria::ASC,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei getOrganisationsByIds Orga: ', [$e]);
            throw $e;
        }
    }

    /**
     * Gets a list of orgaInterfaces by filter.
     *
     * @param array $filter
     *
     * @return array Orga[]
     *
     * @throws Exception
     */
    public function getOrgaByFields($filter)
    {
        try {
            return $this->orgaRepository->findBy($filter);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der Organisation: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all {@link OrgaType} instances that are assigned to the given {@link Orga} regarding
     * the given {@link Customer} with at least one of being {@link OrgaStatusInCustomer::STATUS_ACCEPTED}.
     *
     * @return array<int,OrgaType>|OrgaType[]
     *
     * @throws QueryException
     */
    public function getAcceptedOrgaTypes(Orga $orga, Customer $customer): array
    {
        $query = $this->orgaTypeRepository->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyHasValue($orga->getId(), ['orgaStatusInCustomers', 'orga', 'id'])
            ->propertyHasValue($customer->getId(), ['orgaStatusInCustomers', 'customer', 'id'])
            ->propertyHasValue(OrgaStatusInCustomer::STATUS_ACCEPTED, ['orgaStatusInCustomers', 'status']);

        return $query->getEntities();
    }

    public function findPublicAffairsAgenciesByCustomer(Customer $customer): array
    {
        $customerOrgas = $customer->getOrgas()->toArray();

        return array_filter($customerOrgas, fn ($orga) => $this->orgaRepository->isPublicAffairsAgency($orga));
    }

    public function findPublicAffairsAgenciesIdsByCustomer(Customer $customer): array
    {
        $customerPublicAffairsAgencies = $this->findPublicAffairsAgenciesByCustomer($customer);

        return array_map(static fn (Orga $customerPublicAffairsAgency) => $customerPublicAffairsAgency->getId(), $customerPublicAffairsAgencies);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addCustomerToPublicAffairsAgency(Customer $customer, Orga $publicAffairsAgency)
    {
        if (!$this->orgaRepository->isPublicAffairsAgency($publicAffairsAgency)) {
            throw new InvalidArgumentException(sprintf('Orga with id %s is no Public Affairs Agency.', $publicAffairsAgency->getId()));
        }
        if (in_array($publicAffairsAgency, $this->findPublicAffairsAgenciesByCustomer($customer), true)) {
            throw new InvalidArgumentException(sprintf('Public Affairs Agency with id %s is already in Customer %s.', $publicAffairsAgency->getId(), $customer->getId()));
        }
        $this->addCustomerToOrga($customer, $publicAffairsAgency);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addCustomerToPublicAffairsAgencyByIds(string $customerId, string $publicAffairsAgencyId)
    {
        /** @var Orga $publicAffairsAgency */
        $publicAffairsAgency = $this->orgaRepository->find($publicAffairsAgencyId);
        if (!$this->orgaRepository->isPublicAffairsAgency($publicAffairsAgency)) {
            throw new InvalidArgumentException(sprintf('Orga with id %s is no Public Affairs Agency.', $publicAffairsAgency->getId()));
        }
        /** @var Customer $customer */
        $customer = $this->customerRepository->find($customerId);
        $this->addCustomerToPublicAffairsAgency($customer, $publicAffairsAgency);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function removeCustomerFromPublicAffairsAgencyByIds(string $customerId, string $publicAffairsAgencyId)
    {
        /** @var Orga $publicAffairsAgency */
        $publicAffairsAgency = $this->orgaRepository->find($publicAffairsAgencyId);
        if (!$this->orgaRepository->isPublicAffairsAgency($publicAffairsAgency)) {
            throw new InvalidArgumentException(sprintf('Orga with id %s is no Public Affairs Agency.', $publicAffairsAgency->getId()));
        }
        /** @var Customer $customer */
        $customer = $this->customerRepository->find($customerId);
        $this->removeCustomerFromPublicAffairsAgency($customer, $publicAffairsAgency);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function removeCustomerFromPublicAffairsAgency(Customer $customer, Orga $publicAffairsAgency)
    {
        $orgaRepository = $this->orgaRepository;
        if (!$orgaRepository->isPublicAffairsAgency($publicAffairsAgency)) {
            throw new InvalidArgumentException(sprintf('Orga with id %s is no Public Affairs Agency.', $publicAffairsAgency->getId()));
        }
        $orgaRepository->removeCustomer($customer, $publicAffairsAgency);
        $this->customerRepository->removeOrga($customer, $publicAffairsAgency);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addCustomerToOrga(Customer $customer, Orga $orga)
    {
        $this->orgaRepository->addCustomer($customer, $orga);
        $this->customerRepository->addOrga($customer, $orga);
    }

    /**
     * convert Formpost checkbox field into current jsonAPI Data format.
     *
     * @param array  $data
     * @param string $fieldName
     */
    protected function handleFormPostCheckbox($data, $fieldName): array
    {
        if (array_key_exists('isFormPost', $data)) {
            $data[$fieldName] = array_key_exists($fieldName, $data);
        }

        return $data;
    }

    protected function getOrgaTypeLabelMap()
    {
        return [
            OrgaType::MUNICIPALITY    => 'municipality',
            OrgaType::PLANNING_AGENCY => 'planningagency',
            OrgaType::PUBLIC_AGENCY   => 'invitable_institution',
        ];
    }

    /**
     * @param array<int,string> $orgaTypeNames
     *
     * @return array<int,string>
     */
    public function transformOrgaTypeNamesToLabels(array $orgaTypeNames): array
    {
        $labelMap = $this->getOrgaTypeLabelMap();

        return array_map(
            fn (string $orgaTypeName) => $this->translator->trans($labelMap[$orgaTypeName]), $orgaTypeNames
        );
    }

    public function transformOrgaTypeNameToLabel(string $orgaTypeName): string
    {
        $labels = $this->transformOrgaTypeNamesToLabels([$orgaTypeName]);

        if (1 !== count($labels)) {
            // fail with the translator default behaviour of returning the untranslated key
            $this->logger->warning('Failed to translate orga type name', [$orgaTypeName]);

            return $orgaTypeName;
        }

        return $labels[0];
    }

    public function getActivationChanges(array $data): array
    {
        return $this->orgaRepository->getActivationChanges($data);
    }

    /**
     * @throws Exception
     */
    public function getOrgaSignatureByProcedure(Procedure $procedure): OrgaSignatureValueObject
    {
        $orga = $this->getOrga($procedure->getOrgaId());

        return new OrgaSignatureValueObject(
            $orga->getName() ?? '',
            $orga->getStreet() ?? '',
            $orga->getPostalcode() ?? '',
            $orga->getCity() ?? '',
            $procedure->getAgencyMainEmailAddress() ?? ''
        );
    }

    private function loadMissingOrgaData(Orga $orga): void
    {
        $this->loadOrgaNotifications($orga);
        $this->loadOrgaSubmissionType($orga);
    }
}
