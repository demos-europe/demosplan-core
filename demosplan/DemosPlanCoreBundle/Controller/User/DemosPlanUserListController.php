<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\User\AddressBookEntryService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class DemosPlanUserListController
 * Contains lists of users.
 */
class DemosPlanUserListController extends DemosPlanUserController
{
    /**
     * Teilnehmerliste anzeigen.
     *
     * @return Response
     *
     * @throws MessageBagException
     */
    #[DplanPermissions('area_main_view_participants')]
    #[Route(path: '/informationen/teilnehmende/public', name: 'DemosPlan_informationen_teilnehmende_public')]
    #[Route(path: '/teilnehmende', name: 'DemosPlan_informationen_teilnehmende')]
    public function showParticipants(OrgaService $orgaService)
    {
        $templateVars = [];
        // Teilnehmende Organisationen (öffentliche Liste)
        $templateVars['orgas'] = $orgaService->getParticipants();

        return $this->render(
            '@DemosPlanCore/DemosPlanUser/showParticipants.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'user.participants',
            ]
        );
    }

    /**
     * List users of a specific organisation.
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[DplanPermissions('area_manage_users')]
    #[Route(path: '/user/list', name: 'DemosPlan_user_list')]
    public function listUsers()
    {
        $title = 'user.admin.user';

        return $this->render(
            '@DemosPlanCore/DemosPlanUser/list_user.html.twig',
            ['title' => $title]
        );
    }

    /**
     * List all AddressBookEntries of specific Organisation.
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[DplanPermissions('area_admin_orga_address_book')]
    #[Route(path: '/organisation/adressen/liste/{organisationId}', name: 'DemosPlan_get_address_book_entries', methods: ['GET'])]
    public function getAddressBookEntries(AddressBookEntryService $addressBookEntryService, Request $request, string $organisationId)
    {
        $templateVars = [];
        $checkResult = $this->checkUserOrganisation($organisationId, 'DemosPlan_get_address_book_entries');
        if ($request instanceof RedirectResponse) {
            return $checkResult;
        }
        $templateVars['addressBookEntries'] = $addressBookEntryService->getAddressBookEntriesOfOrganisation($organisationId);

        return $this->render(
            '@DemosPlanCore/DemosPlanUser/unregistered_publicagency_list.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'invitable_institution.unregistered.administer',
            ]
        );
    }

    /**
     * Administrate users.
     * In this case administrate means, save or delete users.
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[DplanPermissions('area_manage_users')]
    #[Route(path: '/user/admin', name: 'DemosPlan_user_admin')]
    public function adminUsers(Request $request, UserHandler $userHandler): RedirectResponse
    {
        $userIdent = '';
        // wenn der request gefüllt ist, bearbeite ihn
        if (0 < $request->request->count()) {
            $requestPost = $request->request;

            $result = $userHandler->adminUsersHandler($requestPost);

            if ($result instanceof User) {
                $userIdent = $result->getId();
            }
        }

        return $this->redirect($this->generateUrl('DemosPlan_user_list')."#{$userIdent}");
    }
}
