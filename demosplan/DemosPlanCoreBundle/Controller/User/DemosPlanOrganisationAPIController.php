<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Logger\ApiLoggerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\TopLevel;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Event\User\NewOrgaCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\User\OrgaAdminEditedEvent;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\NullPointerException;
use demosplan\DemosPlanCoreBundle\Exception\OrgaNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiPaginationParser;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OrgaResourceType;
use demosplan\DemosPlanCoreBundle\Traits\CanTransformRequestVariablesTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\RequestHandling\PaginatorFactory;
use EDT\JsonApi\Validation\FieldsValidator;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use Exception;
use League\Fractal\Resource\Collection;
use Pagerfanta\Adapter\ArrayAdapter;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;
use Webmozart\Assert\Assert;

class DemosPlanOrganisationAPIController extends APIController
{
    use CanTransformRequestVariablesTrait;

    public function __construct(ApiLoggerInterface $apiLogger,
        PrefilledTypeProvider $resourceTypeProvider,
        FieldsValidator $fieldsValidator,
        private readonly TranslatorInterface $translator,
        LoggerInterface $logger,
        GlobalConfigInterface $globalConfig,
        MessageBagInterface $messageBag,
        SchemaPathProcessor $schemaPathProcessor,
        MessageFormatter $messageFormatter,
        private readonly RoleHandler $roleHandler,
    ) {
        parent::__construct($apiLogger, $resourceTypeProvider, $fieldsValidator, $translator, $logger, $globalConfig, $messageBag, $schemaPathProcessor, $messageFormatter);
    }

    /**
     * Get organisation by ID.
     *
     * @DplanPermissions("feature_orga_get")
     */
    #[Route(path: '/api/1.0/Orga/{id}', name: 'dplan_api_orga_get', options: ['expose' => true], methods: ['GET'])]
    public function getAction(CurrentUserService $currentUser, OrgaHandler $orgaHandler, PermissionsInterface $permissions, string $id): APIResponse
    {
        try {
            if ($permissions->hasPermissions(['area_manage_orgas', 'area_manage_orgas_all'], 'OR')) {
                $organization = $orgaHandler->getOrga($id);
            } else {
                $user = $currentUser->getUser();
                $organization = $user->getOrga();
                if (!$organization instanceof Orga || $organization->getId() !== $id) {
                    throw AccessDeniedException::missingPermission('area_manage_orgas_all', $user);
                }
            }

            if ($organization instanceof Orga) {
                $item = $this->resourceService->makeItemOfResource($organization, OrgaResourceType::getName());

                return $this->renderResource($item);
            }

            throw OrgaNotFoundException::createFromId($id);
        } catch (Exception $e) {
            $this->logger->warning('', [$e]);

            return $this->handleApiError($e);
        }
    }

    /**
     * List organizations, depending on permissions.
     *
     * @DplanPermissions("feature_organisation_user_list")
     *
     * @return APIResponse
     */
    #[Route(path: '/api/1.0/organisation', name: 'dplan_api_organisation_list', options: ['expose' => true], methods: ['GET'])]
    public function listAction(
        CustomerHandler $customerHandler,
        OrgaResourceType $orgaResourceType,
        OrgaService $orgaService,
        PaginatorFactory $paginatorFactory,
        PermissionsInterface $permissions,
        Request $request,
        CurrentUserService $currentUser,
        JsonApiPaginationParser $paginationParser,
    ) {
        try {
            if (false === $permissions->hasPermissions(
                ['area_organisations_view_of_customer', 'area_manage_orgas_all'],
                'OR'
            )) {
                // The orgalist is required. If it's not loaded, there's no point in having this route.
                throw new AccessDeniedException('User has no access rights to get $orgalist.');
            }

            $currentCustomer = $customerHandler->getCurrentCustomer();
            $currentUserOrga = $currentUser->getUser()->getOrga();
            Assert::notNull($currentUserOrga);

            $orgaList = $permissions->hasPermission('feature_organisation_own_users_list') ?
                [$currentUserOrga] : $orgaService->getOrgasInCustomer($currentCustomer);

            $filter = $request->query->has('filter') ? $request->query->get('filter') : [];
            $filterRegisterStatus = $filter['registerStatus'] ?? '';
            $orgaSubdomain = $currentCustomer->getSubdomain();
            if (OrgaStatusInCustomerInterface::STATUS_PENDING === $filterRegisterStatus) {
                $orgaList = $this->getPendingOrgas($orgaList, $orgaSubdomain);
            } else {
                // consider a rejected or accepted orga (considering their status for different orga types and subdomains)
                $orgaList = $this->getRegisteredOrgas($orgaList, $orgaSubdomain);
            }
            $filterNameContains = $this->getFilterOrgaNameContains($filter);
            if ('' !== $filterNameContains) {
                $orgaList = array_filter(
                    $orgaList,
                    static fn (Orga $orga) => false !== stripos($orga->getName(), (string) $filterNameContains)
                );
            }

            // pagination
            $pagination = $paginationParser->parseApiPaginationProfile(
                $this->request->query->all('page'),
                $this->request->query->get('sort', ''),
                $this->request->query->get('size', '10')
            );

            $adapter = new ArrayAdapter($orgaList);
            $paginator = new DemosPlanPaginator($adapter);
            $paginator->setCurrentPage($pagination->getNumber());
            $paginator->setMaxPerPage($pagination->getSize());

            $collection = new Collection($paginator, $orgaResourceType->getTransformer(), $orgaResourceType::getName());
            $paginatorAdapter = $paginatorFactory->createPaginatorAdapter($paginator, $request);
            $collection->setPaginator($paginatorAdapter);

            return $this->renderResource($collection);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    /**
     * Method to get the filter to return only Orgas whoses' name include the received string.
     * If no filter to be applied will return an empty string.
     *
     * @return mixed|string
     */
    private function getFilterOrgaNameContains(array $filter)
    {
        $filterPath = $filter['namefilter']['condition']['path'] ?? '';
        $filterOperator = $filter['namefilter']['condition']['operator'] ?? '';
        $filterValue = $filter['namefilter']['condition']['value'] ?? '';

        if ('name' === $filterPath && 'contains' === $filterOperator && '' !== $filterValue) {
            return $filterValue;
        }

        return '';
    }

    /**
     * Given a list of orgas returns those which are in a pending status for the given subdomain.
     * If no subdomain is provided then all of them will be checked.
     */
    private function getPendingOrgas(array $orgas, string $currentSubdomain = ''): array
    {
        $pendingOrgas = [];
        /** @var Orga $orga */
        foreach ($orgas as $orga) {
            if ($this->isOrgaPendingActivation($orga, $currentSubdomain)) {
                $pendingOrgas[] = $orga;
            }
        }

        return $pendingOrgas;
    }

    /**
     * Given a list of orgas returns those which are not anymore pending for the given subdomain.
     * If no subdomain is provided then all of them will be checked.
     */
    private function getRegisteredOrgas(array $orgas, string $currentSubdomain = ''): array
    {
        $registeredOrgas = [];
        /** @var Orga $orga */
        foreach ($orgas as $orga) {
            if ($this->isOrgaRegistered($orga, $currentSubdomain)) {
                $registeredOrgas[] = $orga;
            }
        }

        return $registeredOrgas;
    }

    /**
     * Given an orga checks whether in the given subdomain none of its orgatype status is set to 'pending'.
     * If no subdomain is provided then all of them will be checked.
     */
    private function isOrgaRegistered(Orga $orga, string $orgaSubdomain = ''): bool
    {
        $pendingStatus = OrgaStatusInCustomer::STATUS_PENDING;
        $orgaStatusInCustomers = $orga->getStatusInCustomers();
        $isRegistered = true;
        $orgaTypesInSubdomain = 0;
        /** @var OrgaStatusInCustomer $orgaStatusInCustomer */
        foreach ($orgaStatusInCustomers as $orgaStatusInCustomer) {
            $orgaTypeSubdomain = $orgaStatusInCustomer->getCustomer()->getSubdomain();
            $orgaTypeStatus = $orgaStatusInCustomer->getStatus();
            $isRegistered = $isRegistered && ($orgaTypeStatus !== $pendingStatus || $orgaTypeSubdomain !== $orgaSubdomain);
            if ($orgaTypeSubdomain === $orgaSubdomain || '' === $orgaSubdomain) {
                ++$orgaTypesInSubdomain;
            }
        }

        return $isRegistered && $orgaTypesInSubdomain > 0;
    }

    /**
     * Given an orga checks whether in the given subdomain is set as pending in any of its orgatypes.
     * If no subdomain is provided then all of them will be checked.
     */
    private function isOrgaPendingActivation(Orga $orga, string $orgaSubdomain = ''): bool
    {
        $isPending = false;
        $pendingStatus = OrgaStatusInCustomer::STATUS_PENDING;
        $orgaStatusInCustomers = $orga->getStatusInCustomers();
        /** @var OrgaStatusInCustomer $orgaStatusInCustomer */
        foreach ($orgaStatusInCustomers as $orgaStatusInCustomer) {
            $orgaTypeSubdomain = $orgaStatusInCustomer->getCustomer()->getSubdomain();
            $orgaTypeStatus = $orgaStatusInCustomer->getStatus();
            if ($orgaTypeStatus === $pendingStatus && ('' === $orgaSubdomain || $orgaTypeSubdomain === $orgaSubdomain)) {
                $isPending = true;
                break;
            }
        }

        return $isPending;
    }

    /**
     * This action DOES NOT delete an orga. Instead, it "wipes" it, which is our way of deleting.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/deletion_of_entity_objects/ delete entity objects
     *
     * @DplanPermissions("feature_orga_delete")
     */
    #[Route(path: '/api/1.0/organisation/{id}', name: 'organisation_delete', options: ['expose' => true], methods: ['DELETE'])]
    public function wipeOrgaAction(UserHandler $userHandler, string $id): APIResponse
    {
        $orgaId = $id;
        try {
            $result = $userHandler->wipeOrganisationData($orgaId);

            if (is_array($result)) {
                // Handle errors here
                foreach ($result as $error) {
                    $this->messageBag->add('error', $error);
                }

                return $this->renderEmpty(Response::HTTP_UNAUTHORIZED);
            }
            // Handle successful wipe
            $this->messageBag->addChoice(
                'confirm',
                'confirm.orga.deleted',
                ['count' => 1]
            );

            return $this->renderEmpty();
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    private function getAvailableOrgaRoles(Orga $preUpdateOrga): array
    {
        // get all orga status in customers
        $orgaStatusInCustomers = $preUpdateOrga->getStatusInCustomers();

        // filter out only orga with accepted status in customer
        $availableOrgaRoles = [];

        foreach ($orgaStatusInCustomers as $orgaStatusInCustomer) {
            if (OrgaStatusInCustomer::STATUS_ACCEPTED === $orgaStatusInCustomer->getStatus()) {
                if (OrgaType::PLANNING_AGENCY === $orgaStatusInCustomer->getOrgaType()->getName()) {
                    $availableOrgaRoles[] = $this->roleHandler->getRoleByCode(RoleInterface::PRIVATE_PLANNING_AGENCY);
                }

                if (OrgaType::MUNICIPALITY === $orgaStatusInCustomer->getOrgaType()->getName()) {
                    $availableOrgaRoles[] = $this->roleHandler->getRoleByCode(RoleInterface::PLANNING_AGENCY_ADMIN);
                }
            }
        }

        return $availableOrgaRoles;
    }

    /**
     * Creates a new Organisation.
     *
     * @DplanPermissions("area_manage_orgas")
     *
     * @return APIResponse
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/organisation', options: ['expose' => true], methods: ['POST'], name: 'organisation_create')]
    public function createOrgaAction(Request $request,
        UserHandler $userHandler,
        CustomerHandler $customerHandler,
        PermissionsInterface $permissions,
        AccessControlService $accessControlPermission,
        EventDispatcherInterface $eventDispatcher)
    {
        try {
            if (!($this->requestData instanceof TopLevel)) {
                throw BadRequestException::normalizerFailed();
            }
            $resourceObject = $this->requestData->getObjectToCreate();
            $orgaDataArray = $userHandler->getOrgaArrayFromResourceObject($resourceObject);

            $orgaDataArray = $this->transformRequestVariables($orgaDataArray);
            // user who are allowed to add users may set their visibility
            $orgaDataArray['updateShowlist'] = true;

            $orgaDataArray['customerSubdomain'] = $customerHandler->getCurrentCustomer()->getSubdomain();

            $newOrga = $userHandler->addOrga($orgaDataArray);

            // Fehlermeldung, Pflichtfelder
            if (is_array($newOrga) && array_key_exists('mandatoryfieldwarning', $newOrga)) {
                $this->messageBag->add('error', 'error.mandatoryfields');
                throw new InvalidArgumentException('Can\'t create orga since mandatory fields are missing.');
            }

            $availableOrgaRoles = $this->getAvailableOrgaRoles($newOrga);

            if (array_key_exists('canCreateProcedures', $orgaDataArray) && empty($availableOrgaRoles)) {
                $this->messageBag->add('warning', $this->translator->trans('warning.organisation.no_available_roles'));
                $this->logger->warning('No available roles for procedure creation permission for orga with id: ', [
                    'orgaId' => $newOrga->getId(),
                ]);
            }

            // Add new permission in case it is present in the request
            $canCreateProcedures = null;
            if ($permissions->hasPermission('feature_manage_procedure_creation_permission')
                && array_key_exists('canCreateProcedures', $orgaDataArray) && !empty($availableOrgaRoles)) {
                try {
                    if (true === $orgaDataArray['canCreateProcedures']) {
                        $canCreateProcedures = true;
                        $accessControlPermission->createPermissions(AccessControlService::CREATE_PROCEDURES_PERMISSION, $newOrga, $customerHandler->getCurrentCustomer(), $availableOrgaRoles);
                    }
                } catch (NullPointerException $e) {
                    $this->logger->warning('Role was not found in Customer. Permission is not created', [
                        'roleName'   => RoleInterface::PRIVATE_PLANNING_AGENCY,
                        'permission' => AccessControlService::CREATE_PROCEDURES_PERMISSION,
                    ]);
                }
            }

            try {
                $newOrgaCreatedEvent = new NewOrgaCreatedEvent($newOrga, $canCreateProcedures);
                $eventDispatcher->dispatch($newOrgaCreatedEvent);
            } catch (Exception $e) {
                $this->logger->warning('Could not successfully perform orga created event', [$e]);
            }

            $item = $this->resourceService->makeItemOfResource($newOrga, OrgaResourceType::getName());

            return $this->renderResource($item);
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.organisation.not.created');
            $this->logger->error('Unable to create Orga: ', [$e]);

            return $this->handleApiError($e);
        }
    }

    /**
     * @DplanPermissions("feature_orga_edit")
     *
     * @return APIResponse
     */
    #[Route(path: '/api/1.0/organisation/{id}', name: 'organisation_update', options: ['expose' => true], methods: ['PATCH'])]
    public function updateOrgaAction(
        CustomerHandler $customerHandler,
        OrgaHandler $orgaHandler,
        PermissionsInterface $permissions,
        Request $request,
        UserHandler $userHandler,
        AccessControlService $accessControlPermission,
        EventDispatcherInterface $eventDispatcher,
        string $id)
    {
        $orgaId = $id;
        try {
            $requestData = Json::decodeToArray($request->getContent())['data'];

            // validation
            if (!isset($requestData['attributes'])) {
                $requestData['attributes'] = [];
            }
            // check if orga exists
            $preUpdateOrga = $orgaHandler->getOrga($orgaId);
            if (!$preUpdateOrga instanceof Orga) {
                throw OrgaNotFoundException::createFromId($orgaId);
            }
            $orgaDataArray = $requestData;
            $orgaHandler->checkWritabilityOfAttributes($orgaDataArray['attributes']);

            $pendingStatus = OrgaStatusInCustomer::STATUS_PENDING;
            $customersWithPendingInvitableInstitution = $preUpdateOrga->getCustomersByActivationStatus(OrgaType::PUBLIC_AGENCY, $pendingStatus);
            $customersWithPendingPlanner = $preUpdateOrga->getCustomersByActivationStatus(OrgaType::MUNICIPALITY, $pendingStatus);
            $customersWithPendingPlanningAgency = $preUpdateOrga->getCustomersByActivationStatus(OrgaType::PLANNING_AGENCY, $pendingStatus);
            if (is_array($orgaDataArray['attributes']) && array_key_exists('showlist', $orgaDataArray['attributes'])) {
                // explicitly set that show list may be updated
                $userHandler->setCanUpdateShowList(true);
            }

            $availableOrgaRoles = $this->getAvailableOrgaRoles($preUpdateOrga);

            if (array_key_exists('canCreateProcedures', $orgaDataArray['attributes']) && empty($availableOrgaRoles)) {
                $this->messageBag->add('warning', $this->translator->trans('warning.organisation.no_available_roles'));
                $this->logger->warning('No available roles for procedure creation permission for orga with id: ', [
                    'orgaId' => $orgaId,
                ]);
            }

            $canCreateProcedures = null;
            if ($permissions->hasPermission('feature_manage_procedure_creation_permission') && is_array($orgaDataArray['attributes'])
                && array_key_exists('canCreateProcedures', $orgaDataArray['attributes']) && !empty($availableOrgaRoles)) {
                try {
                    if (true === $orgaDataArray['attributes']['canCreateProcedures']) {
                        $accessControlPermission->createPermissions(AccessControlService::CREATE_PROCEDURES_PERMISSION, $preUpdateOrga, $customerHandler->getCurrentCustomer(), $availableOrgaRoles);
                        $canCreateProcedures = true;
                    } else {
                        $accessControlPermission->removePermissions(AccessControlService::CREATE_PROCEDURES_PERMISSION, $preUpdateOrga, $customerHandler->getCurrentCustomer(), $availableOrgaRoles);
                        $canCreateProcedures = false;
                    }
                } catch (NullPointerException $e) {
                    $this->logger->warning('Role was not found in Customer. Permission is not created', [
                        'roleName'   => RoleInterface::PRIVATE_PLANNING_AGENCY,
                        'permission' => AccessControlService::CREATE_PROCEDURES_PERMISSION,
                    ]);
                }
            }

            $updatedOrga = $userHandler->updateOrga($orgaId, $orgaDataArray);

            if ($updatedOrga instanceof Orga) {
                $this->messageBag->add(
                    'confirm',
                    'confirm.orga.updated',
                    ['orgaName' => $updatedOrga->getName()]
                );

                $canManageAllOrgas = $permissions->hasPermission('area_manage_orgas_all');
                $currentCustomer = $canManageAllOrgas ? null : $customerHandler->getCurrentCustomer();

                $userHandler->manageMinimalRoles($updatedOrga, OrgaType::PUBLIC_AGENCY, $customersWithPendingInvitableInstitution, $currentCustomer);
                $userHandler->manageMinimalRoles($updatedOrga, OrgaType::MUNICIPALITY, $customersWithPendingPlanner, $currentCustomer);
                $userHandler->manageMinimalRoles($updatedOrga, OrgaType::PLANNING_AGENCY, $customersWithPendingPlanningAgency, $currentCustomer);

                $userHandler->manageStatusChangeNotifications($updatedOrga, OrgaType::PUBLIC_AGENCY, $customersWithPendingInvitableInstitution, $currentCustomer);
                $userHandler->manageStatusChangeNotifications($updatedOrga, OrgaType::MUNICIPALITY, $customersWithPendingPlanner, $currentCustomer);
                $userHandler->manageStatusChangeNotifications($updatedOrga, OrgaType::PLANNING_AGENCY, $customersWithPendingPlanningAgency, $currentCustomer);

                $item = $this->resourceService->makeItemOfResource($updatedOrga, OrgaResourceType::getName());

                try {
                    $newOrgaCreatedEvent = new OrgaAdminEditedEvent($updatedOrga, $canCreateProcedures);
                    $eventDispatcher->dispatch($newOrgaCreatedEvent);
                } catch (Exception $e) {
                    $this->logger->warning('Could not successfully perform orga created event', [$e]);
                }

                return $this->renderResource($item);
            }

            throw new UnexpectedValueException();
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
