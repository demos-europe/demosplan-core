<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\BulkLetterExporter;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthorizedUsersController extends BaseController
{
    /**
     * @DplanPermissions("area_admin_consultations")
     */
    #[Route(path: '/verfahren/{procedureId}/berechtigte', name: 'dplan_admin_procedure_authorized_users', methods: ['HEAD', 'GET'])]
    public function listAction(string $procedureId)
    {
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_authorized_users_list.html.twig',
            [
                'procedure'    => $procedureId,
                'title'        => 'authorized.users',
            ]
        );
    }

    /**
     * @DplanPermissions("area_admin_consultations")
     */
    #[Route(path: '/verfahren/{procedureId}/berechtigte/export', name: 'dplan_admin_procedure_authorized_users_export', methods: ['HEAD', 'GET'], options: ['expose' => true])]
    public function exportAction(
        ConsultationTokenService $consultationTokenService,
        CurrentUserInterface $currentUser,
        FileResponseGeneratorStrategy $responseGenerator,
        Request $request,
        TranslatorInterface $translator,
        string $procedureId
    ): Response {
        $tokenList = $consultationTokenService->getTokenListFromResourceType($procedureId, $request->query->all('sort'));

        try {
            $bulkLetterExport = new BulkLetterExporter($translator);
            $exportFile = $bulkLetterExport->generateExport($tokenList, $currentUser->getUser()->getName());

            return $responseGenerator('xlsx', $exportFile);
        } catch (UserNotFoundException|DemosException $e) {
            $this->getMessageBag()->add('warning', $e->getUserMsg());

            return $this->redirectBack($request);
        }
    }
}
