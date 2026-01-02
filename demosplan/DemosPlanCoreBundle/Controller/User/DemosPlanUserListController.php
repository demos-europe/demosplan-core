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
    #[Route(name: 'DemosPlan_informationen_teilnehmende_public', path: '/informationen/teilnehmende/public')]
    #[Route(name: 'DemosPlan_informationen_teilnehmende', path: '/teilnehmende')]
    public function showParticipants(OrgaService $orgaService)
    {
        $templateVars = [];
        // Teilnehmende Organisationen (öffentliche Liste)
        $templateVars['orgas'] = $orgaService->getParticipants();

        return $this->renderTemplate(
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
    #[Route(name: 'DemosPlan_user_list', path: '/user/list')]
    public function listUsers()
    {
        $title = 'user.admin.user';

        return $this->renderTemplate(
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
    #[Route(name: 'DemosPlan_get_address_book_entries', path: '/organisation/adressen/liste/{organisationId}', methods: ['GET'])]
    public function getAddressBookEntries(AddressBookEntryService $addressBookEntryService, Request $request, string $organisationId)
    {
        $templateVars = [];
        $checkResult = $this->checkUserOrganisation($organisationId, 'DemosPlan_get_address_book_entries');
        if ($request instanceof RedirectResponse) {
            return $checkResult;
        }
        $templateVars['addressBookEntries'] = $addressBookEntryService->getAddressBookEntriesOfOrganisation($organisationId);

        return $this->renderTemplate(
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
    #[Route(name: 'DemosPlan_user_admin', path: '/user/admin')]
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
