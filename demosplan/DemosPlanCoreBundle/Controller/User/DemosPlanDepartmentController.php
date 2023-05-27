<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ReservedSystemNameException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class DemosPlanDepartmentController extends BaseController
{
    /**
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_verify_department_switch_or_update', path: '/department/verifychanges', methods: ['GET'])]
    public function verifyDepartmentSwitchOrUpdateAction(AuthenticationUtils $authenticationUtils, Request $request)
    {
        try {
            $session = $request->getSession();

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanUser/verify_orga_switch_or_update.html.twig',
                [
                    'templateVars' => [
                        'type'        => 'Department',
                        'currentName' => $session->get('unknownChange_userDepartmentName'),
                        'gatewayName' => $session->get('unknownChange_gatewayDepartmentName'),
                        'lastUsername'=> $authenticationUtils->getLastUsername(),
                    ],
                ]
            );
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * List departments of specific organisation.
     *
     *
     * @DplanPermissions("area_manage_departments")
     *
     * @param null $orgaId
     *
     * @return RedirectResponse|Response
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_department_list', path: '/department/list/{orgaId}')]
    public function listDepartmentsAction(
        CurrentUserService $currentUser,
        CustomerHandler $customerHandler,
        DqlConditionFactory $conditionFactory,
        EntityFetcher $entityFetcher,
        OrgaService $orgaService,
        Request $request,
        SortMethodFactory $sortMethodFactory,
        UserHandler $userHandler,
        $orgaId)
    {
        $requestPost = $request->request;
        // Hole die User Entity
        $user = $currentUser->getUser();

        // use ogranisationId of requestpost instead of incoming parameter $orgaId, if exists.
        if (0 < count($requestPost) && $requestPost->has('orgaId')) {
            $orgaId = $requestPost->get('orgaId');
        }
        $orga = $orgaService->getOrga($orgaId);

        $userRoles = $user->getRoles();

        $orgaList = [];
        // Falls es sich um den SupportUser handelt, hole alle Orgas des customers,
        // damit er zwischen der orgas wechseln kann
        if (in_array(Role::PLATFORM_SUPPORT, $userRoles, true)) {
            $condition[] = $conditionFactory->propertyHasValue(
                $customerHandler->getCurrentCustomer()->getId(),
                ['statusInCustomers', 'customer']
            );
            $condition[] = $conditionFactory->propertyHasValue(false, ['deleted']);
            $sortMethod = $sortMethodFactory->propertyAscending(['name']);
            $orgaList = $entityFetcher->listEntitiesUnrestricted(Orga::class, $condition, [$sortMethod]);
        }

        $templateVars['orgaList'] = $orgaList;
        $templateVars['departmentList'] = $userHandler->getSortedLegacyDepartmentsWithoutDefaultDepartment($orga);
        $templateVars['organisation'] = $orga;

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/list_departments.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'user.admin.departments',
            ]
        );
    }

    /**
     * Creates a new department and relate to a existing organisation.
     *
     *
     * @DplanPermissions("feature_department_add")
     *
     * @return RedirectResponse|Response
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_department_add', path: '/department/add')]
    public function addDepartmentAction(Request $request, UserHandler $userHandler)
    {
        $requestPost = $request->request;
        try {
            if ($request->isMethod('POST') && $requestPost->has('orgaId')) {
                $result = $userHandler->addDepartment($requestPost->get('orgaId'), $requestPost->all());
                // Fehlermeldung, Pflichtfelder
                if (is_array($result) && array_key_exists('mandatoryfieldwarning', $result)) {
                    $this->getMessageBag()->add('error', 'error.mandatoryfields');
                }

                if ($result instanceof Department) {
                    $this->getMessageBag()->add('confirm', 'confirm.department.created');
                }
            }
        } catch (ReservedSystemNameException $reservedSystemNameException) {
            $this->getMessageBag()->add(
                'error', 'error.reserved.name',
                ['name' => $reservedSystemNameException->getName()]
            );
        } catch (Exception $e) {
            // TODO: check wether we can't return more sanely here

            return $this->handleError($e);
        }

        return $this->redirectToRoute('DemosPlan_department_list', ['orgaId' => $requestPost->get('orgaId')]);
    }

    /**
     * Edit Departments.
     *
     *
     * @DplanPermissions("area_manage_orgas")
     *
     * @return RedirectResponse|Response
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_department_edit', path: '/department/edit/{departmentId}')]
    public function editDepartmentAction(Request $request)
    {
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/edit_department.html.twig',
            [
                'templateVars' => [],
                'title'        => 'project.name',
            ]
        );
    }

    /**
     * Administrate departments of a specific organisation.
     * In this case administrate means, save or delete departments.
     *
     *
     * @DplanPermissions("area_manage_departments")
     *
     * @param string $orgaId
     *
     * @return RedirectResponse|Response
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_departments_admin', path: '/departments/admin/{orgaId}')]
    public function adminDepartmentsAction(Request $request, UserHandler $userHandler, $orgaId)
    {
        // wenn der request gefüllt ist, bearbeite ihn
        if (0 < $request->request->count()) {
            $requestPost = $request->request;
            $userHandler->adminDepartmentsHandler($requestPost);
        }

        return $this->redirectToRoute('DemosPlan_department_list', ['orgaId' => $orgaId]);
    }
}
