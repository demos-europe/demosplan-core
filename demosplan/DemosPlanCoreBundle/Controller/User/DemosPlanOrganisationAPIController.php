<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\JsonApi\RequestHandling\PaginatorFactory;
use Exception;
use League\Fractal\Resource\Collection;
use Pagerfanta\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use UnexpectedValueException;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\APIController;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\TopLevel;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiPaginationParser;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OrgaResourceType;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanUserBundle\Exception\OrgaNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserService;
use demosplan\DemosPlanUserBundle\Logic\CustomerHandler;
use demosplan\DemosPlanUserBundle\Logic\OrgaHandler;
use demosplan\DemosPlanUserBundle\Logic\UserHandler;

class DemosPlanOrganisationAPIController extends APIController
{
    /**
     * Get organisation by ID.
     *
     * @Route(
     *     "/api/1.0/Orga/{id}",
     *     name="dplan_api_orga_get",
     *     options={"expose": true},
     *     methods={"GET"}
     * )
     *
     * @DplanPermissions("feature_orga_get")
     */
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
            $this->getLogger()->warning('', [$e]);

            return $this->handleApiError($e);
        }
    }

    /**
     * List organizations, depending on permissions.
     *
     * @Route(
     *     "/api/1.0/organisation/",
     *     name="dplan_api_organisation_list",
     *     options={"expose": true},
     *     methods={"GET"}
     * )
     *
     * @DplanPermissions("area_organisations")
     *
     * @return APIResponse
     */
    public function listAction(
        CustomerHandler $customerHandler,
        DqlConditionFactory $conditionFactory,
        EntityFetcher $entityFetcher,
        OrgaResourceType $orgaResourceType,
        PaginatorFactory $paginatorFactory,
        PermissionsInterface $permissions,
        Request $request,
        SortMethodFactory $sortMethodFactory,
        JsonApiPaginationParser $paginationParser
    ) {
        try {
            if ($permissions->hasPermission('area_organisations_view_of_customer') ||
                $permissions->hasPermission('area_manage_orgas_all')
            ) {
                $currentCustomer = $customerHandler->getCurrentCustomer();
                $condition[] = $conditionFactory->propertyHasValue($currentCustomer->getId(), 'statusInCustomers', 'customer');
                $condition[] = $conditionFactory->propertyHasValue(false, 'deleted');
                $sortMethod = $sortMethodFactory->propertyAscending('name');
                $orgaList = $entityFetcher->listEntitiesUnrestricted(Orga::class, $condition, [$sortMethod]);
                $filter = $request->query->has('filter') ? $request->query->get('filter') : [];
                $filterRegisterStatus = $filter['registerStatus'] ?? '';
                $orgaSubdomain = $currentCustomer->getSubdomain();
                if (OrgaStatusInCustomer::STATUS_PENDING === $filterRegisterStatus) {
                    $orgaList = $this->getPendingOrgas($orgaList, $orgaSubdomain);
                } else {
                    // consider a rejected or accepted orga (considering their status for different orga types and subdomains)
                    $orgaList = $this->getRegisteredOrgas($orgaList, $orgaSubdomain);
                }
                $filterNameContains = $this->getFilterOrgaNameContains($filter);
                if ('' !== $filterNameContains) {
                    $orgaList = array_filter(
                        $orgaList,
                        static function (Orga $orga) use ($filterNameContains) {
                            return false !== stripos($orga->getName(), $filterNameContains);
                        }
                    );
                }
            } else {
                // The orgalist is required. If it's not loaded, there's no point in having this route.
                throw new AccessDeniedException('User has no access rights to get $orgalist.');
            }

            // pagination
            $pagination = $paginationParser->parseApiPaginationProfile(
                $this->request->query->get('page', []),
                $this->request->query->get('sort', ''),
                $this->request->query->get('size', '10')
            );

            $adapter = new ArrayAdapter($orgaList);
            $paginator = new DemosPlanPaginator($adapter);
            $paginator->setCurrentPage($pagination->getNumber());
            $paginator->setMaxPerPage($pagination->getSize());

            $collection = new Collection($paginator, $orgaResourceType->getTransformer(), $orgaResourceType::getName());
            $paginatorAdapter = $paginatorFactory->createPaginatorAdapter($paginator);
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
     * @Route(
     *     "/api/1.0/organisation/{id}",
     *     name="dplan_api_orga",
     *     options={"expose": true},
     *     methods={"DELETE"},
     *     name="organisation_delete"
     * )
     *
     * @DplanPermissions("feature_orga_delete")
     *
     * @return APIResponse
     */
    public function wipeOrgaAction(UserHandler $userHandler, string $id): APIResponse
    {
        $orgaId = $id;
        try {
            $isOrgaDeleted = $userHandler->wipeOrganisationData($orgaId);
            if ($isOrgaDeleted) {
                $this->getMessageBag()->addChoice(
                    'confirm',
                    'confirm.orga.deleted',
                    ['count' => 1]
                );

                return $this->renderEmpty();
            }

            $this->getMessageBag()->add('error', 'error.organisation.not.deleted');

            return $this->renderEmpty(Response::HTTP_UNAUTHORIZED);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    /**
     * Creates a new Organisation.
     *
     * @Route(
     *     "/api/1.0/organisation/",
     *     options={"expose": true},
     *     methods={"POST"},
     *     name="organisation_create"
     * )
     *
     * @DplanPermissions("area_manage_orgas")
     *
     * @return APIResponse
     *
     * @throws MessageBagException
     */
    public function createOrgaAction(Request $request, UserHandler $userHandler, CustomerHandler $customerHandler)
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

            //Fehlermeldung, Pflichtfelder
            if (array_key_exists('mandatoryfieldwarning', $newOrga)) {
                $this->getMessageBag()->add('error', 'error.mandatoryfields');
                throw new InvalidArgumentException('Can\'t create orga since mandatory fields are missing.');
            }

            $item = $this->resourceService->makeItemOfResource($newOrga, OrgaResourceType::getName());

            return $this->renderResource($item);
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.organisation.not.created');
            $this->logger->error('Unable to create Orga: ', [$e]);

            return $this->handleApiError($e);
        }
    }

    /**
     * @Route(
     *     "/api/1.0/organisation/{id}",
     *     name="dplan_api_orga",
     *     options={"expose": true},
     *     methods={"PATCH"},
     *     name="organisation_update"
     * )
     *
     * @DplanPermissions("feature_orga_edit")
     *
     * @return APIResponse
     */
    public function updateOrgaAction(
        CustomerHandler $customerHandler,
        OrgaHandler $orgaHandler,
        PermissionsInterface $permissions,
        Request $request,
        UserHandler $userHandler,
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
            $updatedOrga = $userHandler->updateOrga($orgaId, $orgaDataArray);

            if ($updatedOrga instanceof Orga) {
                $this->getMessageBag()->add(
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

                return $this->renderResource($item);
            }

            throw new UnexpectedValueException();
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
