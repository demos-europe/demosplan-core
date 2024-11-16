<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\News;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\ManualListSorter;
use demosplan\DemosPlanCoreBundle\Logic\News\GlobalNewsHandler;
use demosplan\DemosPlanCoreBundle\Logic\News\NewsHandler;
use demosplan\DemosPlanCoreBundle\Logic\News\ProcedureNewsService;
use demosplan\DemosPlanCoreBundle\Logic\News\ServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\BrandingService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ausgabe Newseiten.
 */
class DemosPlanNewsController extends BaseController
{
    public function __construct(private readonly GlobalNewsHandler $globalNewsHandler, private readonly NewsHandler $newsHandler, private readonly ProcedureNewsService $procedureNewsService)
    {
    }

    /**
     * Check, whether News is Global or not.
     *
     * @param string|null $procedure
     */
    protected function isGlobalNews($procedure): bool
    {
        return null === $procedure;
    }

    /**
     * Stelle eine Newsdetailansicht für die Beteiligungsebene dar.
     *
     * @DplanPermissions("area_public_participation")
     *
     * @param string $procedure Procedure Id
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_news_news_public_detail', path: '/verfahren/{procedure}/public/aktuelles/{newsID}')]
    public function newsPublicDetailAction(
        BrandingService $brandingService,
        CurrentProcedureService $currentProcedureService,
        PermissionsInterface $permissions,
        string $newsID,
        string $procedure,
    ) {
        // @improve T14613
        $procedureId = $procedure;

        // Template Variable aus Storage Ergebnis erstellen(Output)
        $templateVars = [
            'news'                  => $this->procedureNewsService->getSingleNews($newsID),
            'procedure'             => $procedureId,
            // Verfahrensname holen und an das Template übergeben
            'procedureExternalName' => $currentProcedureService->getProcedureWithCertainty()->getExternalName(),
        ];
        // orga Branding
        if ($permissions->hasPermission('area_orga_display')) {
            $orgaBranding = $brandingService->createOrgaBrandingFromProcedureId($procedureId);
            $templateVars['orgaBranding'] = $orgaBranding;
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanNews/public_newsdetail.html.twig',
            [
                'procedure'    => $procedureId,
                'templateVars' => $templateVars,
                'title'        => 'news.detail',
            ]
        );
    }

    /**
     * Exportiere vorhandene News zu einem Verfahren.
     *
     * @DplanPermissions("area_globalnews")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_globalnews_news_export', path: '/news/export')]
    public function globalnewsExportAction(ServiceOutput $serviceOutputNews, TranslatorInterface $translator, NameGenerator $nameGenerator)
    {
        $pdfName = $translator->trans('news.global.export', [], 'page-title');
        $pdfContent = $serviceOutputNews->generatePdf(
            null,
            'procedure:'.null,
            'news.global.export'
        );

        return $this->handleNewsExport($pdfContent, $pdfName, $nameGenerator);
    }

    /**
     * Exportiere vorhandene News zu einem Verfahren.
     *
     * @DplanPermissions("area_news")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_news_news_export', path: '/verfahren/{procedure}/aktuelles/export')]
    public function newsExportAction(ServiceOutput $serviceOutputNews, TranslatorInterface $translator, NameGenerator $nameGenerator, string $procedure)
    {
        $pdfName = $translator->trans('news.export', [], 'page-title');
        $pdfContent = $serviceOutputNews->generatePdf(
            $procedure,
            'procedure:'.$procedure,
            'news.export'
        );

        return $this->handleNewsExport($pdfContent, $pdfName, $nameGenerator);
    }

    /**
     * Gib die globalen News je nach Eingeloggt/Ausgeloggt und Übersicht aus.
     *
     * @DplanPermissions("area_globalnews")
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_globalnews_news', path: '/news')]
    public function newsListGlobalIndexAction(
        Breadcrumb $breadcrumb,
        CurrentUserService $currentUserService,
        TranslatorInterface $translator,
    ) {
        // Reichere die breadcrumb mit einem extraItem an
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('misc.information', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_misccontent_static_information'),
            ]
        );

        $user = $currentUserService->getUser();
        $newsList = $this->globalNewsHandler->getNewsList($user, 50);

        $pressList = [];
        $recentNewsList = [];
        foreach ($newsList as $news) {
            $categoryTypes = array_column($news['categories'], 'name');
            if (in_array('press', $categoryTypes, true)) {
                $pressList[] = $news;
            }
            if (in_array('news', $categoryTypes, true)) {
                $recentNewsList[] = $news;
            }
        }

        $templateVars = [
            'list'           => [
                'newslist' => $newsList,
            ],
            'pressList'      => $pressList,
            'recentNewsList' => $recentNewsList,
        ];

        return $this->renderTemplate(
            // globale Newslist ausgeloggt
            '@DemosPlanCore/DemosPlanNews/globalnewslist_list_index.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'news.global',
            ]
        );
    }

    /**
     * Liste der Verfahrensmitteilungen auf öffentlicher Verfahrensdetail-Seite.
     *
     * @DplanPermissions("area_news")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_news_news_public', path: '/verfahren/{procedure}/public/aktuelles')]
    public function newsPublicListAction(Request $request, CurrentProcedureService $currentProcedureService, CurrentUserService $currentUserService, string $procedure)
    {
        try {
            // Nur News für Gäste auflisten
            $roles = [Role::GUEST];

            $sResult = $this->procedureNewsService->getNewsList(
                $procedure,
                $currentUserService->getUser(),
                'procedure:'.$procedure,
                null,
                $roles
            );

            // Logo holen und an das Template übergeben
            $procedureArray = $currentProcedureService->getProcedureArray();
            $templateVars = [
                'list'      => [
                    'newslist' => $sResult['result'],
                ],
                'procedure' => $procedure,
                'logo'      => $procedureArray['logo'],
            ];

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanNews/public_newslist.html.twig',
                [
                    'templateVars' => $templateVars,
                    'procedure'    => $procedure,
                    'title'        => 'news',
                ]
            );
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Gib die Adminliste der News aus.
     *
     * @DplanPermissions("area_admin_news")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_news_administration_news', path: '/verfahren/{procedure}/verwalten/aktuelles', options: ['expose' => true])]
    public function newsAdminListAction(Request $request, TranslatorInterface $translator, string $procedure)
    {
        $procedureId = $procedure;

        $this->handleNewsAdminListManualSortPostRequest($request, $translator, $procedureId);

        $sResult = $this->procedureNewsService->getProcedureNewsAdminList(
            $procedureId,
            'procedure:'.$procedureId
        );

        $templateVars = [
            'list' => [
                'newslist' => $sResult['result'],
            ],
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanNews/news_admin_list.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedureId,
                'title'        => 'news.admin',
            ]
        );
    }

    /**
     * Gib die Adminliste der News aus.
     *
     * @DplanPermissions("area_admin_globalnews")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_globalnews_administration_news', path: '/news/verwalten', options: ['expose' => true])]
    public function globalNewsAdminListAction(ManualListSorter $manualListSorter, Request $request, TranslatorInterface $translator, CustomerService $customerService)
    {
        $this->handleNewsAdminListManualSortPostRequest($request, $translator);

        $newsResult = $this->globalNewsHandler->getGlobalNewsAdminList('news');
        $pressResult = $this->globalNewsHandler->getGlobalNewsAdminList('press');

        $mergedResult = array_merge($newsResult, $pressResult);
        $sortedMergedResult = $manualListSorter->orderByManualListSort('global:news', 'global', 'content:news', $mergedResult, customer: $customerService->getCurrentCustomer());

        $templateVars = [
            'list' => [
                // avoid duplicate content:
                'newslist' => collect($sortedMergedResult['list'])->unique()->toArray(),
            ],
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanNews/news_admin_list.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => null,
                'title'        => 'news.global.admin',
            ]
        );
    }

    /**
     * Bereite die einkommenden Daten vor.
     *
     * @param string $action
     */
    private function prepareIncomingData(Request $request, $action): array
    {
        $result = [];

        $incomingFields = [
            'newsnew'  => [
                'action',
                'r_title',
                'r_description',
                'r_text',
                'r_pictitle',
                'r_pdftitle',
                'r_enable',
                'r_group_code',
                'r_category_name',
                'r_designatedSwitchDate',
                'r_determinedToSwitch',
                'r_designatedState',
            ],
            'newsedit' => [
                'action',
                'r_ident',
                'r_title',
                'r_description',
                'r_text',
                'r_pictitle',
                'r_enable',
                'delete_picture',
                'delete_pdf',
                'r_group_code',
                'r_pdftitle',
                'r_category_name',
                'r_designatedSwitchDate',
                'r_determinedToSwitch',
                'r_designatedState',
            ],
        ];

        $requestParameters = $request->request->all();

        foreach ($incomingFields[$action] as $key) {
            if (array_key_exists($key, $requestParameters)) {
                $result[$key] = $requestParameters[$key];
            }
        }

        return $result;
    }

    /**
     * Gib das News anlegen formular aus.
     *
     * Needs to be situated before DemosPlan_news_administration_news_edit_get as otherwise
     * path /verfahren/{procedure}/verwalten/aktuelles/neu would be interpreted as
     * /verfahren/{procedure}/verwalten/aktuelles/{newsID} with "neu" as {newsId}
     *
     * @DplanPermissions("area_admin_news")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_news_administration_news_new_get', path: '/verfahren/{procedure}/verwalten/aktuelles/neu', methods: ['GET'], options: ['expose' => true])]
    #[Route(name: 'DemosPlan_news_administration_news_new_post', path: '/verfahren/{procedure}/verwalten/aktuelles/neu', methods: ['POST'])]
    public function newsAdminNewAction(
        Breadcrumb $breadcrumb,
        FileUploadService $fileUploadService,
        ProcedureService $procedureService,
        Request $request,
        TranslatorInterface $translator,
        string $procedure,
    ) {
        // reichere die breadcrumb mit extraItem an (hier procedure news)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('news.admin', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_news_administration_news', ['procedure' => $procedure]),
            ]
        );

        $templateVars = [];

        // Formulardaten verarbeiten
        $success = $this->handleNewsAdminNewPostRequest($request, $fileUploadService, $templateVars, $procedure);
        if (true === $success) {
            return $this->redirectToRoute('DemosPlan_news_administration_news', ['procedure' => $procedure]);
        }

        return $this->handleNewsAdminNewGetRequest($procedureService, 'news.admin.new', $procedure);
    }

    /**
     * Gib das Editformular der News aus.
     *
     * @DplanPermissions("area_admin_news")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_news_administration_news_edit_get', path: '/verfahren/{procedure}/verwalten/aktuelles/{newsID}', methods: ['GET'], options: ['expose' => true])]
    #[Route(name: 'DemosPlan_news_administration_news_edit_post', path: '/verfahren/{procedure}/verwalten/aktuelles/{newsID}', methods: ['POST'])]
    public function newsAdminEditAction(
        Breadcrumb $breadcrumb,
        FileUploadService $fileUploadService,
        ProcedureService $procedureService,
        Request $request,
        TranslatorInterface $translator,
        string $newsID,
        string $procedure,
    ) {
        // reichere die breadcrumb mit extraItem an (hier procedure news)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('news.admin', [], 'page-title'),
                'url'   => $this->generateUrl(
                    'DemosPlan_news_administration_news',
                    ['procedure' => $procedure]
                ),
            ]
        );

        $success = $this->handleNewsAdminEditPostRequest($request, $fileUploadService, $this->procedureNewsService, $procedure);
        if (true === $success) {
            return $this->redirectToRoute('DemosPlan_news_administration_news', ['procedure' => $procedure]);
        }

        return $this->handleNewsAdminEditGetRequest($procedureService, $this->procedureNewsService, $newsID, 'news.admin.edit', $procedure);
    }

    /**
     * @DplanPermissions("area_admin_globalnews")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_globalnews_administration_news_edit_get', path: '/news/{newsID}/edit', methods: ['GET'], options: ['expose' => true])]
    #[Route(name: 'DemosPlan_globalnews_administration_news_edit_post', path: '/news/{newsID}/edit', methods: ['POST'])]
    public function globalnewsAdminEditAction(
        Breadcrumb $breadcrumb,
        FileUploadService $fileUploadService,
        ProcedureService $procedureService,
        Request $request,
        TranslatorInterface $translator,
        string $newsID,
    ) {
        // reichere die breadcrumb mit extraItem an (hier global news)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('news.global.admin', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_globalnews_administration_news'),
            ]
        );

        $success = $this->handleNewsAdminEditPostRequest($request, $fileUploadService, $this->globalNewsHandler);
        if (true === $success) {
            return $this->redirectToRoute('DemosPlan_globalnews_administration_news', ['procedure' => null]);
        }

        return $this->handleNewsAdminEditGetRequest($procedureService, $this->globalNewsHandler, $newsID, 'news.global.admin.edit');
    }

    /**
     * @DplanPermissions("area_admin_globalnews")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_globalnews_administration_news_new_get', path: '/news/neu', methods: ['GET'], options: ['expose' => true])]
    #[Route(name: 'DemosPlan_globalnews_administration_news_new_post', path: '/news/neu', methods: ['POST'])]
    public function globalnewsAdminNewAction(
        Breadcrumb $breadcrumb,
        FileUploadService $fileUploadService,
        ProcedureService $procedureService,
        Request $request,
        TranslatorInterface $translator,
    ) {
        // reichere die breadcrumb mit extraItem an (hier global news)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('news.global.admin', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_globalnews_administration_news'),
            ]
        );

        $templateVars = [];

        // Formulardaten verarbeiten
        $success = $this->handleNewsAdminNewPostRequest($request, $fileUploadService, $templateVars);
        if (true === $success) {
            return $this->redirectToRoute('DemosPlan_globalnews_administration_news', ['procedure' => null]);
        }

        return $this->handleNewsAdminNewGetRequest($procedureService, 'news.global.admin.new');
    }

    /**
     * News detail. Needs to be situated after all other /news/ routes as otherwise it catches the route.
     *
     * @DplanPermissions("area_globalnews")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_globalnews_news_detail', path: '/news/{newsID}')]
    public function globalnewsDetailAction(Breadcrumb $breadcrumb, TranslatorInterface $translator, string $newsID)
    {
        // Template Variable aus Storage Ergebnis erstellen(Output)
        $templateVars = [
            'news'      => $this->globalNewsHandler->getSingleNews($newsID),
            'procedure' => null,
        ];

        // Reichere die breadcrumb mit zusätzl. items an
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('news.global', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_globalnews_news'),
            ]
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanNews/newsdetail.html.twig',
            [
                'procedure'    => null,
                'templateVars' => $templateVars,
                'title'        => 'news.global.detail',
            ]
        );
    }

    // @improve T12637
    /**
     * @param string|null $procedureId
     *
     * @throws MessageBagException
     */
    protected function handleNewsAdminNewPostRequest(Request $request, FileUploadService $fileUploadService, array &$templateVars, $procedureId = null): bool
    {
        $requestPost = $request->request->all();
        // Formulardaten verarbeiten
        if (!empty($requestPost['action']) && 'newsnew' === $requestPost['action']) {
            $inData = $this->prepareIncomingData($request, 'newsnew');

            // Storage Formulardaten übergeben
            $inData['r_picture'] = $fileUploadService->prepareFilesUpload($request, 'r_picture');
            $inData['r_pdf'] = $fileUploadService->prepareFilesUpload($request, 'r_pdf');

            $storageResult = $this->isGlobalNews($procedureId)
                ? $this->newsHandler->handleNewGlobalNews($inData)
                : $this->newsHandler->handleNewProcedureNews($procedureId, $inData);

            // Wenn Storage erfolgreich: zurueck zur Liste
            if (array_key_exists('ident', $storageResult)
                && !array_key_exists('fieldWarnings', $storageResult)
            ) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.saved');

                return true;
            }
            $templateVars['prefill'] = $inData;
        }

        return false;
    }

    // @improve T12637
    /**
     * @param GlobalNewsHandler|ProcedureNewsService $updater
     * @param string|null                            $procedure
     *
     * @throws MessageBagException
     *
     * @DplanPermissions("area_admin_news")
     */
    protected function handleNewsAdminEditPostRequest(Request $request, FileUploadService $fileUploadService, $updater, $procedure = null): bool
    {
        $requestPost = $request->request->all();
        // Formulardaten verarbeiten
        if (!empty($requestPost['action']) && 'newsedit' === $requestPost['action']) {
            $inData = $this->prepareIncomingData($request, 'newsedit');
            // Storage Formulardaten übergeben
            if ([] !== $inData) {
                $inData['r_picture'] = $fileUploadService->prepareFilesUpload($request, 'r_picture');
                $inData['r_pdf'] = $fileUploadService->prepareFilesUpload($request, 'r_pdf');
                $storageResult = $this->newsHandler->handleEditNews($procedure, $inData, $updater);

                // Wenn Storage erfolgreich: zurueck zur Liste
                if (array_key_exists('ident', $storageResult)
                    && !array_key_exists('fieldWarnings', $storageResult)
                ) {
                    // Erfolgsmeldung
                    $this->getMessageBag()->add('confirm', 'confirm.saved');

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string|null $procedure
     *
     * @throws MessageBagException
     * @throws Exception
     */
    protected function handleNewsAdminListManualSortPostRequest(Request $request, TranslatorInterface $translator, $procedure = null)
    {
        $requestPost = $request->request->all();
        $procedureId = $procedure;
        // manuelle sortierung verarbeiten
        if (array_key_exists('manualsort', $requestPost)) {
            // manuelle Sortierung uebergeben, Komma separierte Liste von IDs
            $sortedIds = $requestPost['manualsort'];

            if ('' == $sortedIds) {
                $storageResult = false;
            } else {
                $sortedIds = 'delete' === $sortedIds ? '' : $sortedIds;

                if (null === $procedureId) {
                    $storageResult = $this->globalNewsHandler->setManualSortOfGlobalNews($sortedIds);
                } else {
                    $storageResult = $this->procedureNewsService->setManualSortOfNews($procedureId, $sortedIds);
                }
            }

            // The manualsort property contains all news entry IDs send with the POST as
            // comma (and whitespace) separated list. By iterating this list we can
            // determine if a news was set as enabled or not.
            $allAffectedNewsIds = explode(
                ',',
                str_replace(' ', '', (string) $requestPost['manualsort'])
            );
            $enabledNewsIdsOnly = $requestPost['r_enable'] ?? [];

            $stateChangeSuccess = $this->newsHandler->changeGlobalContentOrNewsEnabledProperties(
                $allAffectedNewsIds,
                $enabledNewsIdsOnly,
                null === $procedure
            );

            // Message ausgeben
            if ($storageResult && true === $stateChangeSuccess) {
                $this->getMessageBag()->add('confirm', $translator->trans('confirm.saved'));
            }
        }
    }

    // @improve T12637

    /**
     * @param ProcedureNewsService|GlobalNewsHandler $newsGetter
     * @param string|null                            $procedure
     *
     * @throws Exception
     */
    protected function handleNewsAdminEditGetRequest(ProcedureService $procedureService, $newsGetter, string $newsId, string $title, $procedure = null): Response
    {
        // Ausgabe des Formulars
        $news = $newsGetter->getSingleNews($newsId);
        $templateVars = ['news' => $news];

        // hole die Textbausteine
        $templateVars['boilerplates'] = $procedureService->getBoilerplatesOfCategory($procedure, 'news.notes');
        $templateVars['boilerplateGroups'] = $procedureService->getBoilerplateGroups($procedure, 'news.notes');

        // Gebe dem Template die Info, ob es sich um eine globale News handelt
        if ($this->isGlobalNews($procedure)) {
            $templateVars['isGlobal'] = true;
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanNews/news_admin_edit.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
                'title'        => $title,
            ]
        );
    }

    // @improve T12637

    /**
     * @param string|null $procedure
     *
     * @throws Exception
     */
    protected function handleNewsAdminNewGetRequest(ProcedureService $procedureService, string $title, $procedure = null): Response
    {
        $templateVars = [];
        $templateVars['procedure'] = $procedure;

        // hole die Textbausteine
        $templateVars['boilerplates'] = $procedureService->getBoilerplatesOfCategory($procedure, 'news.notes');
        $templateVars['boilerplateGroups'] = $procedureService->getBoilerplateGroups($procedure, 'news.notes');

        // Gebe dem Template die Info, ob es sich um eine globale News handelt
        if ($this->isGlobalNews($procedure)) {
            $templateVars['isGlobal'] = true;
        }

        // Ausgabe
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanNews/news_admin_new.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
                'title'        => $title,
            ]
        );
    }

    /**
     * @throws Exception
     */
    protected function handleNewsExport(
        string $pdfContent,
        string $pdfName,
        NameGenerator $nameGenerator,
    ): Response {
        $this->getLogger()->debug('Got Response: '.DemosPlanTools::varExport($pdfContent, true));

        if ('' === $pdfContent) {
            throw new Exception('PDF-Export fehlgeschlagen');
        }

        $response = new Response($pdfContent, 200);
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $nameGenerator->generateDownloadFilename($pdfName));

        return $response;
    }
}
