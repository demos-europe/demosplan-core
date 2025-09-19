<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\SubmittedStatementsOnMergeOrganisationsException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\MasterToebListExport;
use demosplan\DemosPlanCoreBundle\Logic\User\MasterToebService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanMasterToebController extends BaseController
{
    public function __construct(private readonly MasterToebService $masterToebService)
    {
    }

    /**
     * Anzeige der MasterTöBliste.
     *
     * @DplanPermissions("area_manage_mastertoeblist")
     *
     * @return RedirectResponse|Response
     *
     * @throws \Exception
     */
    #[Route(name: 'DemosPlan_user_mastertoeblist', path: '/mastertoeblist')]
    public function masterToebListAction()
    {
        $templateVars = [];

        $results = $this->masterToebService->getMasterToebs(true);
        $templateVars['orgas'] = $results;

        $template = '@DemosPlanCore/DemosPlanUser/mastertoeblist.html.twig';

        return $this->renderTemplate($template, [
            'templateVars' => $templateVars,
            'title'        => 'user.invitable_institution.master',
        ]);
    }

    /**
     * Update einer Orga via ajax.
     *
     * @DplanPermissions("area_manage_mastertoeblist")
     *
     * @return Response
     */
    #[Route(name: 'DemosPlan_user_mastertoeblist_update_ajax', path: '/mastertoeblist/organisation/update', options: ['expose' => true])]
    public function updateMasterToebListAjaxAction(Request $request)
    {
        $requestPost = $request->request;
        $updateData = [$requestPost->get('field') => $requestPost->get('value')];
        $this->masterToebService->updateMasterToeb($requestPost->get('oId'), $updateData);

        // prepare the response
        $response = [
            'code'    => 100,
            'success' => true, ];

        // return result as JSON
        return new Response(Json::encode($response));
    }

    /**
     * Gibt es neue Protokolleinträge seit dem letzen Aufruf des Protokolls.
     *
     * @DplanPermissions("area_report_mastertoeblist")
     *
     * @param string $userId
     *
     * @return Response
     *
     * @throws \Exception
     */
    #[Route(name: 'DemosPlan_user_mastertoeblist_has_new_reportentry_ajax', path: '/mastertoeblist/report/hasNewReportentry/{userId}', options: ['expose' => true])]
    public function reportMasterToebListHasNewEntryAjaxAction(ContentService $contentService, $userId)
    {
        $hasNewReportEntry = false;

        // Berechtigungnen werden im Backend geprüft, das Initalisierungsgeraffel tut hier nicht Not
        $this->profilerStart('getReport');
        $results = $this->masterToebService->getMasterToebsReport();
        $this->profilerStop('getReport');

        if ([] !== $results) {
            $reportRead = 0;
            $this->profilerStart('getLastRead');
            try {
                $reportReadSettings = $contentService->getSettings(
                    'reportMastertoebRead'
                );
                // Nur den eigenen Eintrag nutzen
                foreach ($reportReadSettings as $setting) {
                    if ($userId == $setting['userId']) {
                        $reportRead = $setting['content'];
                    }
                }
            } catch (HttpException) {
                // Most likely 404 Setting not set
            }
            $this->profilerStop('getLastRead');

            $this->profilerStart('processEntries');
            // Gibt es Einträge eines anderen Users seit dem letzten Besuch
            foreach ($results as $entry) {
                if ($entry['createdDate'] < $reportRead * 1000) {
                    break;
                }
                if ($entry['userId'] != $userId) {
                    $hasNewReportEntry = true;
                    break;
                }
            }
            $this->profilerStop('processEntries');
        }

        // prepare the response
        $response = [
            'code'              => 100,
            'success'           => true,
            'hasNewReportEntry' => $hasNewReportEntry, ];

        // return result as JSON
        return new JsonResponse($response);
    }

    /**
     * Anlegen einer Orga via ajax.
     *
     * @DplanPermissions("area_manage_mastertoeblist")
     *
     * @return Response
     *
     * @throws CustomerNotFoundException
     */
    #[Route(name: 'DemosPlan_user_mastertoeblist_add_ajax', path: '/mastertoeblist/organisation/add', options: ['expose' => true])]
    public function addMasterToebAjaxAction(
        CustomerHandler $customerHandler,
        OrgaService $orgaService,
        Request $request,
        UserService $userService)
    {
        try {
            $requestPost = $request->request;
            $dataPost = $requestPost->all();

            if ('orgaName' === $dataPost['field']) {
                $data = ['orgaName' => $dataPost['value']];
                // Lege eine Schattenorga an
                $orgaData = [
                    'customer' => $customerHandler->getCurrentCustomer(),
                    'name'     => $dataPost['value'],
                    'type'     => OrgaType::PUBLIC_AGENCY,
                ];
                $newOrga = $orgaService->addOrga($orgaData);
                $newDepartment = $userService->addDepartment(['name' => 'Keine Abteilung'], $newOrga->getId());
                $data['oId'] = $newOrga->getId();
                $data['dId'] = $newDepartment->getId();
                $masterToeb = $this->masterToebService->addMasterToeb($data);
                // prepare the response
                $response = [
                    'code'    => 100,
                    'success' => true,
                    'ident'   => $masterToeb->getId(),
                ];
            } else {
                // prepare the response
                $response = [
                    'code'    => 101,
                    'success' => true, ];
            }

            // return result as JSON
            return new Response(Json::encode($response));
        } catch (HttpException $e) {
            // fange unterschiedliche Fehler ab
            switch ($e->getStatusCode()) {
                case 403:
                    $response = [
                        'code'    => 403,
                        'success' => false, ];
                    break;
                default:
                    // return default result as JSON
                    return $this->handleAjaxError($e);
            }

            // return result as JSON
            return new Response(Json::encode($response));
        }
    }

    /**
     * Löschen einer Orga via ajax.
     *
     * @DplanPermissions("area_manage_mastertoeblist")
     *
     * @return Response
     */
    #[Route(name: 'DemosPlan_user_mastertoeblist_delete_ajax', path: '/mastertoeblist/organisation/delete', options: ['expose' => true])]
    public function deleteMasterToebAjaxAction(Request $request)
    {
        $requestPost = $request->request;
        $this->masterToebService->deleteMasterToeb($requestPost->get('oId'));

        // prepare the response
        $response = [
            'code'    => 100,
            'success' => true, ];

        // return result as JSON
        return new Response(Json::encode($response));
    }

    /**
     * Gebe eine Liste von geänderten Einträgen der Master-Toeb-Liste aus.
     *
     * @DplanPermissions("area_report_mastertoeblist")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException|\Exception
     */
    #[Route(name: 'DemosPlan_user_mastertoeblist_report', path: '/mastertoeblist/report', options: ['expose' => true])]
    public function masterToebListReportAction(
        CurrentProcedureService $currentProcedureService,
        CurrentUserService $currentUser,
        ContentService $contentService,
        PermissionsInterface $permissions,
        TranslatorInterface $translator,
    ) {
        $templateVars = [];
        $templateVars['displayChangesSinceLastVisit'] = false;
        $userId = $currentUser->getUser()->getId();

        $results = $this->masterToebService->getMasterToebsReport();

        if ([] !== $results) {
            // Normale User bekommen keine Extraliste mit nicht gelesenen Einträgen
            $reportRead = time();

            // Wer die Mastertöbliste bearbeiten darf, schon
            if ($permissions->hasPermission('area_manage_mastertoeblist')) {
                $templateVars['displayChangesSinceLastVisit'] = true;
                try {
                    $reportRead = 0;
                    $reportReadSettings = $contentService->getSettings(
                        'reportMastertoebRead'
                    );
                    // Nur den eigenen Eintrag nutzen
                    foreach ($reportReadSettings as $setting) {
                        if ($userId == $setting['userId']) {
                            $reportRead = $setting['content'];
                        }
                    }
                } catch (HttpException) {
                    // Most likely 404 Setting not set
                }
                try {
                    $data = [
                        'userId'  => $userId,
                        'content' => time(),
                    ];
                    // setze das aktuelle Datum als zuletzt gelesen
                    $contentService->setSetting('reportMastertoebRead', $data);
                } catch (HttpException) {
                    $this->logger->warning('Speichern der Setting reportMastertoebRead fehlgeschlagen');
                }
            }

            $categoryLabels = [
                'update' => $translator->trans('update', [], 'master-toeb-list'),
                'delete' => $translator->trans('delete', [], 'master-toeb-list'),
                'add'    => $translator->trans('add', [], 'master-toeb-list'),
                'merge'  => $translator->trans('merge', [], 'master-toeb-list'),
            ];

            $reportEntries = $results;
            $reportEntriesRead = [];
            $reportEntriesUnread = [];

            // gehe die Liste der Einträge durch

            foreach ($reportEntries as $key => $entry) {
                $entry['message'] = '' === $entry['message'] ? [] : Json::decodeToArray($entry['message']);
                $entry['incoming'] = '' === $entry['incoming'] ? [] : Json::decodeToArray($entry['incoming']);
                $entry['changes'] = [];

                // Wenn ein Feld aktualsiert wurde, dann gebe eine Variable aus...
                if ('update' === $entry['category']) {
                    // this really needs to be incoming, as the information
                    // what changed is calculated by incoming vs. message
                    foreach ($entry['incoming'] as $field => $content) {
                        // mit dem geänderter Feld
                        $entry['changes'][0]['fieldOfChange'] = $field;
                        // mit dem neuen Inhalt
                        $entry['changes'][0]['contentNew'] = $content;
                        // und wenn es einen gibt, den alten Eintrag
                        if (isset($entry['message'][$field]) && $content !== $entry['message'][$field]) {
                            $entry['changes'][0]['contentOld'] = $entry['message'][$field];
                        }
                    }
                }

                if ('merge' === $entry['category']) {
                    $entry['changes'][0]['fieldOfChange'] = 'orgaName';
                    $entry['changes'][0]['contentNew'] = 'k.A.';
                    foreach ($entry['message'] as $content) {
                        $entry['message']['orgaName'] = $entry['message']['resultOrganisation']['name'];
                    }
                }
                // Wenn ein Organisation gelöscht wird, dann gebe ihren Namen aus
                if ('delete' === $entry['category']) {
                    $entry['changes'][0]['fieldOfChange'] = 'orgaName';
                    $entry['changes'][0]['contentNew'] = 'k.A.';
                    if (isset($entry['message']['orgaName'])) {
                        $entry['changes'][0]['contentNew'] = $entry['message']['orgaName'];
                    }
                }
                // Wenn ein Organisation hinzugefügt wird, dann gebe ihren Namen aus
                if ('add' === $entry['category']) {
                    if (null === $entry['message']) {
                        $this->getLogger()->warning('Message was null', [$entry]);
                        $entry['message'] = [];
                    }
                    foreach ($entry['message'] as $field => $content) {
                        // Bestimmte Felder sollen nicht angegeben werden
                        $fieldsNotToShow = ['ident', 'dId', 'oId'];
                        if (!in_array($field, $fieldsNotToShow)) {
                            // mit dem geänderter Feld
                            $entry['changes'][0]['fieldOfChange'] = $field;
                            // mit dem neuen Inhalt
                            $entry['changes'][0]['contentNew'] = $content;
                        }
                    }
                }

                // setze ein Label für die Kategorie
                if (array_key_exists($entry['category'], $categoryLabels)) {
                    $entry['categoryLabel'] = $categoryLabels[$entry['category']];
                }

                // Eigene Einträge immer gleich als gelesen darstellen
                $isOwn = $entry['userId'] == $userId;
                if (!$isOwn && $entry['createdDate'] > $reportRead * 1000) {
                    $reportEntriesUnread[$key] = $entry;
                } else {
                    $reportEntriesRead[$key] = $entry;
                }
            }

            $templateVars['entriesRead'] = $reportEntriesRead;
            $templateVars['entriesUnread'] = $reportEntriesUnread;
        }

        $procedure = '';
        if (!$permissions->hasPermission('area_manage_mastertoeblist')) {
            $currentProcedure = $currentProcedureService->getProcedure();
            $procedure = $currentProcedure instanceof Procedure ? $currentProcedure->getId() : '';
        }

        return $this->renderTemplate('@DemosPlanCore/DemosPlanUser/mastertoeblist_report.html.twig', [
            'procedure'    => $procedure,
            'templateVars' => $templateVars,
            'title'        => 'user.invitable_institution.master.report',
        ]);
    }

    /**
     * Exportiere die MasterTöbListe.
     *
     * Basiert auf PHPOffice/PhpSpreadsheet
     * https://github.com/PHPOffice/PhpSpreadsheet, MIT
     *
     * @DplanPermissions("area_use_mastertoeblist")
     *
     * @return RedirectResponse|StreamedResponse
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_mastertoeblist_export', path: '/mastertoeblist/export', options: ['expose' => true])]
    public function masterToebListExportAction(
        FileResponseGeneratorStrategy $responseGenerator,
        PermissionsInterface $permissions,
        Request $request,
        TranslatorInterface $translator): Response
    {
        $result = $this->masterToebService->getMasterToebs();

        try {
            $masterToebListExport = new MasterToebListExport($permissions, $translator);
            $exportFile = $masterToebListExport->generateExport($result);

            return $responseGenerator('xlsx', $exportFile);
        } catch (DemosException $e) {
            $this->getMessageBag()->add('warning', $e->getUserMsg());

            return $this->redirectBack($request);
        }
    }

    /**
     * Zusammenführen von angemeldeten Organisationen mit Organisationen aus der Master-TöB-Liste.
     *
     * @DplanPermissions("area_merge_mastertoeblist")
     *
     * @return RedirectResponse|Response
     *
     * @throws \Exception
     */
    #[Route(name: 'DemosPlan_user_mastertoeblist_merge', path: '/mastertoeblist/merge')]
    public function masterToebListMergeAction(Request $request)
    {
        $masterToebListService = $this->masterToebService;
        $requestPost = $request->request;

        if ($requestPost->has('r_submit_button')) {
            $organisationId = $requestPost->get('r_orga');
            $masterToebId = $requestPost->get('r_orga_mastertoeb');

            if ((0 < strlen((string) $organisationId)) && (0 < strlen((string) $masterToebId))) {
                try {
                    $mergeResult = $masterToebListService->mergeOrganisations($organisationId, $masterToebId);

                    // Generiere eine Erfolgsmeldung
                    if ($mergeResult) {
                        $this->getMessageBag()->add('confirm', 'confirm.merge.success');
                    }
                } catch (SubmittedStatementsOnMergeOrganisationsException $exception) {
                    $this->logger->warning($exception->getMessage(), ['exception' => $exception]);
                    $this->getMessageBag()->add('error', 'error.organisation.merge.statements.existing');
                }
            } else {
                // Generiere eine Fehlermeldung
                $this->getMessageBag()->add('error', 'error.organisation.not.selected');
            }
        }
        $templateVars = [];

        // Hole die Liste der Organisationen, die auf der Plattform existieren, aber nicht mit der MasterTöb-Liste verbunden sind
        $organisations = $masterToebListService->getOrganisations();
        $templateVars['organisations'] = $organisations;

        // Hole die Liste der Organisationen von der Master-TöB-Liste
        $orgasMasterToeb = $masterToebListService->getOrganisationsOfMasterToeb();
        $templateVars['orgasMasterToeb'] = $orgasMasterToeb;

        return $this->renderTemplate('@DemosPlanCore/DemosPlanUser/mastertoeblist_merge.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'user.invitable_institution.master.merge',
        ]);
    }
}
