<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DemosEurope\DemosplanAddon\Controller\APIController;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\JsonApi\RequestHandling\PaginatorFactory;
use EDT\JsonApi\RequestHandling\UrlParameter;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use Exception;
use League\Fractal\Resource\Collection;
use LogicException;
use Pagerfanta\Adapter\ArrayAdapter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\GenericApiController;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\EmailAddressInUseException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\LoginNameInUseException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\SendMailException;
use demosplan\DemosPlanCoreBundle\Exception\UserAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\TopLevel;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiPaginationParser;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdministratableUserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\UserResourceType;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Response\EmptyResponse;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanStatementBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\UserHandler;
use demosplan\DemosPlanUserBundle\Logic\UserService;

class DemosPlanUserAPIController extends APIController
{
    /**
     * @var UserService
     */
    protected $userService;

    public function __construct(
        ApiLogger $apiLogger,
        PrefilledResourceTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator,
        UserService $userService
    ) {
        parent::__construct($apiLogger, $resourceTypeProvider, $translator);

        $this->userService = $userService;
    }

    /**
     * @Route(path="/api/1.0/user/{userId}/get",
     *        methods={"GET"},
     *        name="dplan_api_user_get",
     *        options={"expose": true})
     *
     * @DplanPermissions("feature_user_get")
     *
     * @throws MessageBagException
     */
    public function getAction(string $userId): APIResponse
    {
        try {
            $user = $this->userService->getSingleUser($userId);

            if ($user instanceof User) {
                $item = $this->resourceService->makeItemOfResource(
                    $user,
                    UserResourceType::getName()
                );

                return $this->renderResource($item);
            }

            $e = new EntityIdNotFoundException('No user with that id in found database.');
            $e->setEntityId($userId);
            throw $e;
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'warning.access.denied');
            $this->logger->error('Unable to find user: '.$e);

            return $this->handleApiError($e);
        }
    }

    /**
     * @Route(path="/api/1.0/user/",
     *        methods={"GET"},
     *        name="dplan_api_users_get",
     *        options={"expose": true})
     *
     * @DplanPermissions("feature_user_list")
     *
     * @throws MessageBagException
     */
    public function listAction(
        AdministratableUserResourceType $userType,
        DrupalFilterParser $filterParser,
        JsonApiActionService $jsonApiActionService,
        JsonApiPaginationParser $paginationParser,
        PaginatorFactory $paginatorFactory,
        Request $request,
        SortMethodFactory $sortMethodFactory
    ): APIResponse {
        try {
            if ($request->query->has(UrlParameter::FILTER)) {
                $filterArray = $request->query->get(UrlParameter::FILTER);
                $conditions = $filterParser->parseFilter($filterArray);
            } else {
                $conditions = [];
            }

            $sortMethods = [
                $sortMethodFactory->propertyAscending(...$userType->lastname),
                $sortMethodFactory->propertyAscending(...$userType->firstname),
            ];

            $searchParams = SearchParams::createOptional($request->query->get('search', []));
            if (!$searchParams instanceof SearchParams) {
                $listResult = $jsonApiActionService->listObjects($userType, $conditions, $sortMethods);
            } else {
                $listResult = $jsonApiActionService->searchObjects($userType, $searchParams, $conditions, $sortMethods);
            }
            $users = $listResult->getList();

            $adapter = new ArrayAdapter($users);
            $paginator = new DemosPlanPaginator($adapter);
            $pagination = $paginationParser->parseApiPaginationProfile(
                $this->request->query->get(UrlParameter::PAGE, []),
                $this->request->query->get(UrlParameter::SORT, ''),
                25
            );
            $paginator->setCurrentPage($pagination->getNumber());
            $paginatorAdapter = $paginatorFactory->createPaginatorAdapter($paginator);

            $transformer = $userType->getTransformer();
            $collection = new Collection($paginator, $transformer, 'User');
            $collection->setPaginator($paginatorAdapter);

            return $this->renderResource($collection);
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'warning.access.denied');
            $this->logger->error('Unable to get user list: '.$e);

            return $this->handleApiError($e);
        }
    }

    /**
     * @Route(path="/api/1.0/user/",
     *        methods={"POST"},
     *        name="dplan_api_user_create",
     *        options={"expose": true})
     *
     * @DplanPermissions("feature_user_add")
     *
     * @throws MessageBagException
     *
     * @deprecated Use `/api/2.0/User` instead ({@link GenericApiController::createAction()})
     */
    public function createAction(UserHandler $userHandler): APIResponse
    {
        try {
            if (!($this->requestData instanceof TopLevel)) {
                throw BadRequestException::normalizerFailed();
            }

            $resourceObject = $this->requestData->getObjectToCreate();

            if ('User' !== ucfirst($resourceObject['type'])) {
                throw new BadRequestException('Invalid resource object type');
            }

            $user = $userHandler->createUserFromResourceObject($resourceObject);

            if ($user instanceof User) {
                try {
                    $userHandler->inviteUser($user);
                    $this->getMessageBag()->add('confirm', 'confirm.email.invitation.sent');
                } catch (SendMailException $e) {
                    $this->getMessageBag()->add('error', 'error.email.invitation.send.to.user');
                }

                $item = $this->resourceService->makeItemOfResource(
                    $user,
                    UserResourceType::getName()
                );

                return $this->renderResource($item);
            }

            throw new RuntimeException('Could not create user');
        } catch (EmailAddressInUseException|LoginNameInUseException $e) {
            $this->getMessageBag()->add('error', 'error.login.or.email.not.unique');

            return $this->handleApiError($e);
        } catch (UserAlreadyExistsException $e) {
            $this->getMessageBag()->add('error', 'error.user.login.exists');

            return $this->handleApiError($e);
        } catch (Exception $e) {
            $this->getLogger()->error('New User Entity could not been saved');
            $this->getMessageBag()->add('error', 'error.save');

            return $this->handleApiError($e);
        }
    }

    /**
     * @Route(path="/api/1.0/user/{id}",
     *        methods={"DELETE"},
     *        name="dplan_api_user_delete",
     *        options={"expose": true})
     *
     * @DplanPermissions("feature_user_delete")
     *
     * @return APIResponse|EmptyResponse
     */
    public function deleteAction(string $id): Response
    {
        $this->userService->wipeUser($id);

        return $this->createEmptyResponse();
    }

    /**
     * @Route(path="/api/1.0/user/{id}",
     *        methods={"PATCH"},
     *        name="dplan_api_user_update",
     *        options={"expose": true})
     *
     * @DplanPermissions("feature_user_edit")
     */
    public function updateAction(string $id, UserHandler $userHandler): APIResponse
    {
        if (!($this->requestData instanceof TopLevel)) {
            throw BadRequestException::normalizerFailed();
        }

        $userData = $this->requestData->User[$id]['attributes'] ?? [];
        try {
            $userRelationships = $this->requestData->User[$id]['relationships'] ?? [];

            foreach ($userRelationships as $relationshipName => $relationship) {
                switch ($relationshipName) {
                    case 'roles':
                        $userData['roles'] = array_map(
                            static function ($relObject) {
                                return $relObject['id'];
                            },
                            $relationship['data']
                        );
                        break;

                    case 'orga':
                        $userData['organisationId'] = $relationship['data']['id'];
                        break;

                    case 'department':
                        $userData['departmentId'] = $relationship['data']['id'];
                        break;

                    default:
                        throw new LogicException("Unexpected relationship {$relationship}");
                }
            }
        } catch (InvalidArgumentException $e) {
            // nothing to do here, we just don't have changed relationships
        }

        $updatedUser = $userHandler->updateUser($id, $userData);

        if ($updatedUser instanceof User) {
            $this->getMessageBag()->add('confirm', 'confirm.all.changes.saved');
            $item = $this->resourceService->makeItemOfResource($updatedUser, UserResourceType::getName());

            return $this->renderResource($item);
        }

        return $this->renderError(Response::HTTP_BAD_REQUEST);
    }
}
