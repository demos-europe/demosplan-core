<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use BadMethodCallException;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\GuestStatementSubmittedEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationStrictEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\CookieException;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\DraftStatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateInternIdException;
use demosplan\DemosPlanCoreBundle\Exception\GdprConsentRequiredException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\RowAwareViolationsException;
use demosplan\DemosPlanCoreBundle\Exception\TimeoutException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedWorksheetNameException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\Document\DocumentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelImporter;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\StatementSpreadsheetImporterWithZipSupport;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureCoupleTokenFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Statement\CountyService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementListHandlerResult;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementListUserFilter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\XlsxStatementImport;
use demosplan\DemosPlanCoreBundle\Logic\User\BrandingService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Logic\XlsxStatementImporterFactory;
use demosplan\DemosPlanCoreBundle\Repository\NotificationReceiverRepository;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use demosplan\DemosPlanCoreBundle\Services\DatasheetService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\DraftStatementListFilters;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Exception;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use function explode;

/**
 * Ausgabeseiten Stellungnahmen.
 */
class DemosPlanStatementController extends BaseController
{
    private const STATEMENT_IMPORT_ENCOUNTERED_ERRORS = 'statement import failed';

    public function __construct(private readonly CurrentProcedureService $currentProcedureService, private readonly CurrentUserService $currentUser, private readonly DraftStatementHandler $draftStatementHandler, private readonly DraftStatementService $draftStatementService, private readonly Environment $twig, private readonly MailService $mailService, private readonly PermissionsInterface $permissions, private readonly NameGenerator $nameGenerator)
    {
    }

    /**
     * PDF-Export der Statements.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @param string $procedure
     * @param string $type
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_statement_list_released_group_export_pdf', path: '/verfahren/{procedure}/stellungnahmen/freigabenGruppe/pdf', defaults: ['title' => 'statements.final.group', 'type' => 'releasedGroup'])]
    #[Route(name: 'DemosPlan_statement_list_final_group_export_pdf', path: '/verfahren/{procedure}/stellungnahmen/endfassungenGruppe/pdf', defaults: ['title' => 'statements.final.group', 'type' => 'finalGroup'])]
    #[Route(name: 'DemosPlan_statement_list_final_citizen_export_pdf', path: '/verfahren/{procedure}/stellungnahmen/endfassungenCitizen/pdf', defaults: ['title' => 'statements.final.group', 'type' => 'finalCitizen'])]
    #[Route(name: 'DemosPlan_statement_single_export_pdf', path: '/verfahren/{procedure}/stellungnahmen/single/pdf', defaults: ['type' => 'single'], options: ['expose' => true])]
    public function pdfAction(
        CurrentProcedureService $currentProcedureService,
        Request $request,
        NameGenerator $nameGenerator,
        TranslatorInterface $translator,
        $procedure,
        $type,
    ) {
        $itemsToExport = null;
        $draftStatementList = [];
        $filename = \sprintf('_%s.pdf', $translator->trans('statement'));
        // Dass die Stellungnahme gedruckt werden darf, muss an aufrufender Stelle implementiert werden
        $requestGet = $request->query->all();
        if (isset($requestGet['sId'])) {
            $itemsToExport[] = $requestGet['sId'];
        }

        $file = $this->draftStatementService->generatePdf($draftStatementList, $type, $procedure, $itemsToExport);

        if ('' === $file->getContent()) {
            throw new RuntimeException('PDF-Export fehlgeschlagen');
        }

        // Bürger und Gäste bekommen den externen Namen angezeigt
        $roles = $this->currentUser->getUser()->getRoles();
        $rolesforExternalName = [Role::GUEST, Role::CITIZEN];

        $procedureObject = $currentProcedureService->getProcedure();
        if ($procedureObject instanceof Procedure) {
            $procedureName = \in_array($roles[0], $rolesforExternalName, true) ?
                $procedureObject->getExternalName() :
                $procedureObject->getName();
            $filename = $procedureName.$filename;
        }
        $response = new Response($file->getContent(), 200);
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $nameGenerator->generateDownloadFilename($filename));

        return $response;
    }

    /**
     * @DplanPermissions("area_statements_public")
     *
     * @return RedirectResponse|Response|null
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_list_public', path: '/verfahren/{procedure}/stellungnahmen/toeb', defaults: ['templateName' => 'list_public'])]
    public function otherCompaniesListAction(
        Request $request,
        CurrentProcedureService $currentProcedureService,
        string $_route,
        string $procedure,
        string $templateName,
    ) {
        $this->saveDraftListFiltersInSession($request, $procedure, $templateName);
        $requestPost = 0 === $request->request->count() ? $this->getDraftListFiltersFromSession($request) : $request->request;

        $search = $requestPost->get('search_word');

        if ($requestPost->has('f_sort')) {
            $sort = ToBy::createArray(
                $requestPost->get('f_sort'),
                $requestPost->get('f_sort_ascdesc')
            );
        } else {
            $sort = null;
        }
        $userFilter = new StatementListUserFilter();
        if ($requestPost->has('f_organisation') && '' !== $requestPost->get('f_organisation')) {
            $userFilter->setOrga($requestPost->get('f_organisation'));
        }

        // Template Variable aus Storage Ergebnis erstellen(Output)
        $outputResult = $this->draftStatementHandler->statementOtherCompaniesListHandler(
            $procedure,
            $search,
            $userFilter,
            $sort
        );

        // PDF Export
        $templateName = 'list_final_other';
        if ($requestPost->has('pdfExport') || $requestPost->has('pdfExportSingle')) {
            if ($requestPost->has('pdfExportSingle')) {
                $singleItemToBeExported = $requestPost->get('pdfExportSingle');
                $requestPost->set('item_check', [$singleItemToBeExported]);
            }

            $procedureObject = $currentProcedureService->getProcedureWithCertainty();
            $exportResponse = $this->exportStatementList($requestPost, $outputResult, $templateName, $procedureObject);
            if (null === $exportResponse) {
                return $this->redirectToRoute($_route, ['procedure' => $procedure]);
            }

            return $exportResponse;
        }

        $templateVars = $this->draftStatementHandler->createTemplateVars($outputResult->toArray());
        $templateVars['procedure'] = $procedure;
        $templateVars['search'] = $search;

        // Display as participationLayer
        $templateVars['procedureLayer'] = 'participation';

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/list_public.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
                'title'        => 'statements.other.companies',
            ]
        );
    }

    /**
     * Einreichen einer Stellungnahme aus der öffentlichen Beteiligung.
     *
     * @DplanPermissions({"feature_new_statement", "area_statements_draft"})
     *
     * @param string $_route
     *
     * @throws MessageBagException
     * @throws Throwable
     */
    #[Route(name: 'DemosPlan_statement_public_submit', path: '/verfahren/{procedure}/stellungnahmen/public/submit')]
    public function submitPublicStatementAction(
        MapService $mapService,
        Request $request,
        CurrentProcedureService $currentProcedureService,
        CountyService $countyService,
        DraftStatementHandler $draftStatementHandler,
        DraftStatementService $draftStatementService,
        ProcedureService $procedureService,
        StatementHandler $statementHandler,
        string $procedure,
        $_route): Response
    {
        $procedureId = $procedure;
        try {
            $procedure = $procedureService->getProcedure($procedureId);

            // @improve T14122
            $templateVars = [];
            $templateVars['list']['sortingSetActive'] = [];

            $templateVars['notificationReceivers'] = $draftStatementHandler->getNotificationReceiversByProcedure($procedureId);

            $title = 'statement.submit';
            $redirectParameters = ['procedure' => $procedureId, 'title' => $title];

            $inData = $this->prepareIncomingData($request, 'confirmSubmitPublicStatement');

            /*
             * This abomination checks if a draft statement id was given as get parameter because why not?
             * (The why is that statements can be submitted directly by citizens without going through the
             * clicking game of their draft list first. To make that possible, this route needs to handle
             * a get request.
             */
            if ($request->getSession()->has('singleStatementId')
                && '' !== $request->getSession()->get('singleStatementId')) {
                // query draft statement statement from request
                if (!\array_key_exists('item_check', $inData) || !\is_array($inData['item_check'])) {
                    $inData['item_check'] = [];
                }

                $inData['item_check'][] = $request->getSession()->get('singleStatementId');
                $request->request->set('item_check', $inData['item_check']);

                // remove the id from the session just to be safe
                $request->getSession()->remove('singleStatementId');
            }

            $requestPost = $request->request;

            // refs: T12321
            if ($requestPost->has('pdfExportSingle')) {
                $draftStatementId = $requestPost->get('pdfExportSingle');
                $requestPost->set('pdfExport', true);
                $requestPost->set('item_check', [$draftStatementId]);
            }

            // Prüfe, ob StellungnahmeIds übergeben wurden
            if (!isset($inData['item_check']) && !$requestPost->has('pdfExport')) {
                $this->getMessageBag()->add('warning', 'warning.select.entries');

                return $this->redirectToRoute('DemosPlan_statement_list_draft', $redirectParameters);
            }

            $user = $this->currentUser->getUser();

            if ($requestPost->has('pdfExport')) {
                [
                    $chosenDraftStatements,
                    $itemsToExport,
                    $procedureObject,
                ] = $this->submitPublicStatementPdfExportHandling($currentProcedureService, $procedureId,
                    $draftStatementHandler, $user, $requestPost);

                return $this->createPdfDraftStatement($chosenDraftStatements, 'list_draft', $procedureObject, $itemsToExport);
            }

            // use citizentemplates if neded
            $templateVars['isCitizen'] = $user->isCitizen();

            // Ergänze die Daten zum Statement mit bestehenden User-Daten
            $inData['userName'] = $user->getFullname();
            if ('' !== $user->getEmail()) {
                $inData['userEmail'] = $user->getEmail();
            }
            if ($this->permissions->hasPermission('feature_draft_statement_add_address_to_private_person')) {
                $inData['userStreet'] = $user->getStreet();
                $inData['userPostalCode'] = $user->getPostalcode();
                $inData['userCity'] = $user->getCity();
                $inData['houseNumber'] = $user->getHouseNumber();
            }
            $templateVars['user'] = $user;

            // Angemeldete Bürger bekommen automatisch per email Rückmeldung
            $inData['feedback'] = 'email';

            // Behandle die Stellungnahme (Einreichung, Freigabe)
            if (\array_key_exists('action', $inData) && 'confirmSubmitPublicStatement' === $inData['action']) {
                $invalidGprCheck = $this->invalidGdprCheck($inData);
                $invalidPrivacyCheck = $this->invalidPrivacyCheck($inData);
                $invalidLocalityCheck = $this->invalidLocalityCheck($inData);

                if ($invalidGprCheck || $invalidPrivacyCheck || $invalidLocalityCheck) {
                    return $this->redirectToRoute(
                        'DemosPlan_statement_list_draft',
                        ['procedure' => $procedureId]
                    );
                }

                $showConfirmWithPublicationDelay = false;

                $gdprConsentReceived =
                    \array_key_exists('r_gdpr_consent', $inData)
                    && 'on' === $inData['r_gdpr_consent'];
                foreach ($inData['item_check'] as $statementToRelease) {
                    // Speichern der neuen Formulardaten
                    $inData['procedureId'] = $procedureId;

                    // need to get publicAllowed status to avoid incorrect overriding
                    $draftStatement = $draftStatementService->getDraftStatementEntity($statementToRelease);
                    if (null === $draftStatement) {
                        throw DraftStatementNotFoundException::createFromId($statementToRelease);
                    }
                    $inData['r_makePublic'] = $draftStatement->isPublicAllowed();
                    // citizen and public agencies should always get feedback
                    $inData['uFeedback'] = true;

                    $showConfirmWithPublicationDelay = $draftStatement->isPublicAllowed() && $procedure->getPublicParticipation();

                    $statementHandler->updateDraftStatement($statementToRelease, $inData);
                    // Bei erfolgter Speicherung des Entwurfs wird die Stellungnahme direkt freigegeben.
                    $statementHandler->releaseDraftStatement($statementToRelease);
                    // Bei erfolgter Freigabe des Stellungnahme wird die sie direkt eingereicht
                    $statementHandler->submitStatement($statementToRelease, '', false, $gdprConsentReceived);
                }

                $this->getMessageBag()->addChoice(
                    'confirm',
                    'statement.submitted',
                    ['count' => is_countable($inData['item_check']) ? count($inData['item_check']) : 0]
                );
                if ($showConfirmWithPublicationDelay) {
                    $this->getMessageBag()->addChoice(
                        'confirm',
                        'statement.submitted.clarity.delay',
                        ['count' => is_countable($inData['item_check']) ? count($inData['item_check']) : 0]
                    );
                }

                return $this->redirectToRoute('DemosPlan_statement_list_final_group', $redirectParameters);
            }

            $statementsToSubmit = [];
            $statementsToSubmitIds = [];

            if (isset($inData['item_check']) && 0 < (is_countable($inData['item_check']) ? count($inData['item_check']) : 0)) {
                foreach ($inData['item_check'] as $draftStatementId) {
                    // get draft Statement
                    $draftStatement = $draftStatementHandler->getSingleDraftStatement($draftStatementId);

                    // wenn kein Statement zurückgegeben wird, zeige sie nicht an
                    if (!isset($draftStatement['ident'])) {
                        continue;
                    }

                    $statementsToSubmit[] = $draftStatement;
                    $statementsToSubmitIds[] = $draftStatementId;
                }
            }

            $templateVars['statementList'] = $statementsToSubmit;
            $templateVars['list']['statementlist'] = $statementsToSubmit;
            $templateVars['statementsToSubmitIds'] = $statementsToSubmitIds;

            $templateVars['counties'] = $countyService->getCounties();
            // current route
            $templateVars['actionPath'] = $_route;
            // use public layout
            $templateVars['procedureLayer'] = 'participation';
            $baseLayers = $mapService->getGisList($procedureId, 'base');
            $templateVars['baselayers'] = [
                'gislayerlist' => $mapService->getLayerObjects($baseLayers),
            ];
            $templateVars['procedure'] = $procedure;

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanStatement/new_public_participation_statement_confirm.html.twig',
                [
                    'templateVars' => $templateVars,
                    'procedure'    => $procedureId,
                    'title'        => $title,
                ]
            );
        } catch (AccessDeniedException $e) {
            if (false === $this->permissions->hasPermissionsetWrite()) {
                $this->getMessageBag()->add('error', 'error.statement.submit.phase');

                return $this->redirectToRoute('DemosPlan_statement_list_draft', ['procedure' => $procedureId]);
            }

            return $this->handleError($e);
        }
    }

    /**
     * The GET parameter reset resets the filters in session.
     *
     * @DplanPermissions("area_statements")
     *
     * @param string      $_route
     * @param string|bool $submitted `true`, `false` or `"both"`
     *
     * @return RedirectResponse|Response
     *
     * @throws Throwable
     */
    #[Route(name: 'DemosPlan_statement_list_final_group', path: '/verfahren/{procedure}/stellungnahmen/endfassungenGruppe', defaults: ['templateName' => 'list_final_group', 'released' => true, 'scope' => 'group', 'submitted' => true, 'title' => 'statements.final.group'], options: ['expose' => true])]
    #[Route(name: 'DemosPlan_statement_list_released', path: '/verfahren/{procedure}/stellungnahmen/freigaben', defaults: ['templateName' => 'list_released', 'released' => true, 'scope' => 'own', 'submitted' => 'both', 'title' => 'statements.released'], options: ['expose' => true])]
    #[Route(name: 'DemosPlan_statement_list_draft', path: '/verfahren/{procedure}/stellungnahmen/entwuerfe', defaults: ['templateName' => 'list_draft', 'released' => false, 'scope' => 'own', 'submitted' => false, 'title' => 'statements.drafts'], options: ['expose' => true])]
    #[Route(name: 'DemosPlan_statement_list_released_group', path: '/verfahren/{procedure}/stellungnahmen/freigabenGruppe', defaults: ['templateName' => 'list_released_group', 'released' => true, 'scope' => 'group', 'submitted' => false, 'title' => 'statements.released.group'], options: ['expose' => true])]
    public function listAction(
        BrandingService $brandingService,
        Breadcrumb $breadcrumb,
        CountyService $countyService,
        CurrentProcedureService $currentProcedureService,
        DatasheetService $datasheetService,
        ElementsService $elementsService,
        NotificationReceiverRepository $notificationReceiverRepository,
        OrgaHandler $orgaHandler,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        Request $request,
        TranslatorInterface $translator,
        UserService $userService,
        $_route,
        string $procedure,
        bool $released,
        string $scope,
        $submitted,
        string $templateName,
        string $title,
        StatementHandler $statementHandler,
    ) {
        $this->saveDraftListFiltersInSession($request, $procedure, $templateName);
        $userRole = $this->currentUser->getUser()->getDplanRolesString();
        $currentProcedureArray = $currentProcedureService->getProcedureArray();
        $currentProcedure = $currentProcedureService->getProcedureWithCertainty();
        $fscope = 'group';

        if (Role::CITIZEN === $userRole) {
            $template = '@DemosPlanCore/DemosPlanStatement/'.$templateName.'_citizen.html.twig';
            // Ändere den Templatename für den pdf-export
            $templateName = 'list_final_group_citizen';
            if ('statements.final.group' === $title) {
                $title = 'statements.final.own';
            }
        } else {
            $template = '@DemosPlanCore/DemosPlanStatement/'.$templateName.'.html.twig';
        }

        $manualSortScope = null;

        $requestPost = 0 === $request->request->count() ? $this->getDraftListFiltersFromSession($request) : $request->request;
        $requestGet = $request->query;

        $filters = $this->determineListFilters($released, $submitted, $request, $requestPost);
        $search = $this->determineListSearch($requestPost);
        $sort = $this->determineListSort($requestPost);

        if (true === $submitted) {
            $this->permissions->checkPermission('area_statements_final');
            if ($requestPost->has('f_scope') && 'own' === $requestPost->get('f_scope')) {
                $filters->setUserId($this->currentUser->getUser()->getId());
                $scope = 'own';
                $fscope = 'own';
            }
            if (Role::CITIZEN === $userRole) {
                $filters->setUserId($this->currentUser->getUser()->getId());
                $scope = 'ownCitizen';
            }

            $manualSortScope = 'orga:'.$this->currentUser->getUser()->getOrganisationId();
        } elseif (true === $released) {
            if ('group' === $scope) {
                $this->permissions->checkPermission('area_statements_released_group');
            } else {
                $this->permissions->checkPermission('area_statements_released');
            }
        } else {
            $this->permissions->checkPermission('area_statements_draft');
        }

        // loeschen verarbeiten
        if ($requestGet->has('statement_delete')) {
            return $this->deleteStatement($translator, $_route, $procedure, $released, $requestGet->get('statement_delete'));
        }

        // freigeben verarbeiten
        if ($requestPost->has('statement_release')) {
            if ($requestPost->has('item_check')) {
                return $this->releaseStatement($procedure, $requestPost->get('item_check'), $currentProcedureArray);
            }

            $this->getMessageBag()->add('warning', $translator->trans('warning.select.entries'));

            return $this->redirectToRoute($_route, ['procedure' => $procedure]);
        }

        // manuelle sortierung verarbeiten
        if ($requestPost->has('resetManualsort') || $requestPost->has('saveManualsort')) {
            return $this->saveManualsort($request, $translator, $_route, $procedure);
        }

        // zurueckweisen verarbeiten
        if ($requestPost->has('statement_reject') && 0 < \strlen((string) $requestPost->get('statement_reject'))) {
            return $this->rejectStatement($request, $translator, $currentProcedure, $requestPost->get('statement_reject'), $userService);
        }

        // einreichen verarbeiten
        if ($requestPost->has('statementSubmit')) {
            return $this->submitStatement($request, $_route, $procedure, $notificationReceiverRepository, $permissions, $statementHandler, $orgaHandler, $procedureService);
        }

        // Emailversand
        if ($requestPost->has('statement_send')) {
            return $this->sendStatement($request, $translator, $_route, $procedure);
        }

        // Template Variable aus Storage Ergebnis erstellen(Output)
        $outputResult = $this->draftStatementHandler->statementListHandler(
            $procedure,
            $scope,
            $filters,
            $search,
            $sort->toArray(),
            $this->currentUser->getUser(),
            $manualSortScope
        );

        // todo: should be able do in twig by using getProcedurePhase()?!
        $outputResult->setStatementList($this->replacePhaseByPhaseNameForDraftStatements($outputResult->getStatementList()));

        $votedStatements = $this->replacePhaseByPhaseNameForVotedStatementList(
            $statementHandler->determineVotedStatements($procedure)
        );

        if ($requestPost->has('pdfExport') || $requestPost->has('pdfExportSingle')) {
            if ($requestPost->has('pdfExportSingle')) {
                $singleItemToBeExported = $requestPost->get('pdfExportSingle');
                $requestPost->set('item_check', [$singleItemToBeExported]);
            }

            $procedureObject = $currentProcedureService->getProcedureWithCertainty();
            $exportResponse = $this->exportStatementList($requestPost, $outputResult, $templateName, $procedureObject);
            if (null === $exportResponse) {
                return $this->redirectToRoute($_route, ['procedure' => $procedure]);
            }

            return $exportResponse;
        }

        $templateVars = ['list' => $outputResult->toArray()];
        $templateVars['procedure'] = $procedure;
        $templateVars['search'] = $search;
        $templateVars['list']['votedStatements'] = $votedStatements;

        // Setze ein Flag für den Bürger, damit die richtige Druckversion benutzt wird
        $templateVars['isCitizen'] = Role::CITIZEN === $userRole;

        // kontextuelle Hilfe
        if (Role::CITIZEN === $userRole) {
            $contextualHelpTitle = $title.'.citizen';
        } else {
            $contextualHelpTitle = $title;
        }

        $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp($contextualHelpTitle);

        $templateVars['hasPermissionsetWrite'] = $this->permissions->hasPermissionsetWrite();

        // Gib Informationen über die Filter und Sortierung ins Template
        $templateVars['filterName'] = [
            'planningDocument' => $translator->trans('document'),
            'element'          => $translator->trans('document'),
            'reasonParagraph'  => $translator->trans('paragraph'),
            'institution'      => $translator->trans('invitable_institution'),
            'priority'         => $translator->trans('priority'),
            'department'       => $translator->trans('department'),
            'tags'             => $translator->trans('tags'),
            'phase'            => $translator->trans('procedure.public.phase'),
        ];

        $templateVars['sortingName'] = [
            'createdDate' => $translator->trans('date.created'),
            'document'    => $translator->trans('document'),
            'paragraph'   => $translator->trans('paragraph'),
        ];

        // Übergib die aktive Sortierung ins Template
        $templateVars['list']['sortingSetActive'] = [];

        foreach ($templateVars['list']['sort'] as $sortingSet) {
            if (true === $sortingSet['active']) {
                // Füge die Sortierrichtung hinzu
                $sortDirectionTranslatorkey = 'ascending';
                if ('DESC' === $sortingSet['sorting']) {
                    $sortDirectionTranslatorkey = 'descending';
                }
                $sortingSet['sortingDirectionLabel'] = $translator->trans(
                    $sortDirectionTranslatorkey
                );
                // Füge den Klarnamen des Sortierkriteriums hinzu
                $sortingSet['sortingName'] = $templateVars['sortingName'][$sortingSet['name']] ?? '';

                $templateVars['list']['sortingSetActive'] = $sortingSet;
                break;
            }
        }

        // is the negative statement plannindocument category enabled?
        $templateVars['planningDocumentsHasNegativeStatement'] =
            $elementsService->hasNegativeReportElement($procedure);

        $templateVars['notificationReceivers'] = $this->draftStatementHandler->getNotificationReceiversByProcedure($procedure);
        $templateVars['counties'] = $countyService->getCounties();
        // Display as participationLayer
        $templateVars['procedureLayer'] = 'participation';
        $templateVars['orga'] = null;

        try {
            // add orga to be able to use short submission
            $templateVars['orga'] = $orgaHandler->getOrga($this->currentUser->getUser()->getOrganisationId());
        } catch (Exception) {
            // just go on
        }

        // orga Branding
        if ($this->permissions->hasPermission('area_orga_display')) {
            $orgaBranding = $brandingService->createOrgaBrandingFromProcedureId($currentProcedureArray['id']);
            $templateVars['orgaBranding'] = $orgaBranding;
        }

        $datasheetVersion = $datasheetService->getDatasheetVersion($procedure);

        $templateVars['htmlAvailable'] = 1 === $datasheetVersion || 2 === $datasheetVersion;
        $templateVars['procedureUiDefinition'] = $currentProcedure->getProcedureUiDefinition();
        $templateVars['procedureBehaviorDefinition'] = $currentProcedure->getProcedureBehaviorDefinition();
        $templateVars['statementFormDefinition'] = $currentProcedure->getStatementFormDefinition();

        return $this->renderTemplate(
            $template,
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
                'title'        => $title,
                'fscope'       => $fscope,
                // überschreibt ein übergebenes 'permissions' die Werte aus der Session
                'permissions'  => $this->permissions->getPermissions(),
            ]
        );
    }

    /**
     * Stellungahme mitzeichnen.
     *
     * @DplanPermissions("feature_statements_vote_may_vote")
     *
     * @param string $procedure Procedure Id
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_public_vote', path: '/verfahren/{procedure}/stellungnahmen/public/{statementID}/vote')]
    public function votePublicStatementAction(
        BrandingService $brandingService,
        MapService $mapService,
        ProcedureService $procedureService,
        Request $request,
        StatementService $statementService,
        UserService $userService,
        string $procedure,
        string $statementID,
    ) {
        // @improve T14613
        $procedureId = $procedure;

        $templateVars = [];

        $templateVars['user'] = $this->currentUser->getUser();

        $requestPost = $request->request->all();
        if (\array_key_exists('action', $requestPost) && 'confirmVotePublicStatement' === $requestPost['action']) {
            // add vote to statement
            $statementService->addVote($statementID, $this->currentUser->getUser());

            return $this->redirectToRoute(
                'DemosPlan_procedure_public_detail',
                ['procedure' => $procedureId, '_fragment' => 'procedureDetailsStatementsPublic']
            );
        }

        // get Statement-Details
        $outputResult = $statementService->getStatementByIdent($statementID);

        $templateVars['statement'] = $outputResult;

        $baseLayers = $mapService->getGisList($procedureId, 'base');
        $templateVars['baselayers'] = [
            'gislayerlist' => $mapService->getLayerObjects($baseLayers),
        ];

        $overlayLayers = $mapService->getGisList($procedureId, 'overlay');
        $templateVars['overlays'] = [
            'gislayerlist' => $mapService->getLayerObjects($overlayLayers),
        ];

        // Display as participationLayer
        $templateVars['procedureLayer'] = 'participation';

        // orga Branding
        if ($this->permissions->hasPermission('area_orga_display')) {
            $orgaBranding = $brandingService->createOrgaBrandingFromProcedureId($procedureId);
            $templateVars['orgaBranding'] = $orgaBranding;
        }

        $templateVars['procedure'] = $procedureService->getProcedure($procedureId);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/new_public_participation_statement_vote.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedureId,
                'title'        => 'statement.vote',
            ]
        );
    }

    /**
     * Stellungahme mitzeichnen.
     *
     * @DplanPermissions("feature_statements_like_may_like")
     *
     * @param string $procedure
     * @param string $statementId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_public_like', path: '/verfahren/{procedure}/stellungnahmen/public/{statementId}/vote/anonymous')]
    public function likePublicStatementAction(
        EventDispatcherPostInterface $eventDispatcherPost,
        Request $request,
        StatementService $statementService,
        $procedure,
        $statementId,
    ) {
        $response = $this->redirectToRoute('DemosPlan_procedure_public_detail', ['procedure' => $procedure]);

        $event = new RequestValidationStrictEvent(
            $request,
            $response,
            'statementId',
            $statementId
        );

        try {
            $eventDispatcherPost->post($event);
        } catch (CookieException|Exception) {
            return $response;
        }

        // Mitzeichnen einer Stellungnahme
        // Füge dem Statement eine Mitzeichnung hinzu
        $statementService->addLike($statementId, null);

        $this->getMessageBag()->add('confirm', 'confirm.statement.like');

        return $response;
    }

    /**
     * Speichere eine Stellungnahme via Ajax-Aufruf.
     *
     * @param string $procedure Procedure Id
     *
     * @return Response
     *
     * initially use area_demosplan, specific permissions are checked below
     *
     * @throws Throwable
     *
     * @DplanPermissions("area_demosplan")
     */
    #[Route(name: 'DemosPlan_statement_public_participation_new_ajax', methods: 'POST', path: '/verfahren/{procedure}/stellungnahmen/public/neu/ajax', options: ['expose' => true])]
    public function newPublicStatementAjaxAction(
        CurrentProcedureService $currentProcedureService,
        EventDispatcherPostInterface $eventDispatcherPost,
        RateLimiterFactory $anonymousStatementLimiter,
        Request $request,
        StatementHandler $statementHandler,
        FileUploadService $fileUploadService,
        EventDispatcherInterface $eventDispatcher,
        string $procedure,
    ) {
        try {
            if (!$this->permissions->hasPermissionsetWrite()) {
                throw new Exception('In der aktuellen Phase darf keine Stellungnahme abgegeben werden');
            }

            $limiter = $anonymousStatementLimiter->create($request->getClientIp());

            // avoid brute force attacks
            // if the limit bites during development or testing, you can increase the limit in the config via setting
            // framework.rate_limiter.anonymous_statement.limit in the parameters.yml to a higher value
            if (false === $limiter->consume(1)->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }
            $requestPost = $request->request->all();
            $this->logger->debug('Received ajaxrequest to save statement', ['request' => $requestPost, 'procedure' => $procedure]);

            if (!\array_key_exists('action', $requestPost) || 'statementpublicnew' === !$requestPost['action']) {
                throw new Exception('cannot handle request');
            }

            $this->logger->debug(
                'Ajaxrequest save statement: Session: '.DemosPlanTools::varExport(
                    $this->currentUser->getUser()->getLastname(),
                    true
                )
            );

            $submitRoute = null;
            $immediateSubmit = false;
            if ($this->permissions->hasPermission('feature_draft_statement_citizen_immediate_submit')) {
                $immediateSubmit = $request->query->has('immediate_submit')
                    && true === (bool) $request->query->get('immediate_submit');
            }

            // Abgabe der Stellungnahme als angemeldeter Nutzer via Beteiligungsebene
            // ggf. trotzdem als Bürger
            if (true === $this->currentUser->getUser()->isLoggedIn()
                && !$this->permissions->hasPermission('feature_statements_participation_area_always_citizen')
            ) {
                $this->permissions->checkPermission('feature_new_statement');

                // Formulardaten einsammeln
                $requestPost['r_uploaddocument'] = $fileUploadService->prepareFilesUpload($request, 'r_file');

                // Storage Formulardaten übergeben
                $draftStatement = $this->draftStatementHandler->newHandler($procedure, $requestPost);

                if ($immediateSubmit) {
                    $this->getLogger()->info('Immediate finalization requested');

                    $submitRoute = $this->generateUrl('DemosPlan_statement_public_submit', [
                        'procedure' => $procedure,
                    ]);

                    $request->getSession()->set('singleStatementId', $draftStatement['id']);
                }

                $draftStatementId = $draftStatement['id'];
                $draftStatementNumber = $draftStatement['number'];
                $template = '@DemosPlanCore/DemosPlanProcedure/public_detail_form_confirmation_loggedin.html.twig';
            } else {
                $event = new RequestValidationWeakEvent(
                    $request,
                    null,
                    'publicStatement'
                );

                try {
                    $this->logger->info('Pre RequestValidationWeakEvent');
                    $eventDispatcherPost->post($event);
                } catch (Exception $e) {
                    $this->logger->error('Could not validate request', [$e]);

                    return $this->renderJson([], 100, false);
                }
                $this->logger->info('Post RequestValidationWeakEvent');

                $statementHandler->setRequestValues($requestPost);
                $statementHandler->setDisplayNotices(false);

                $fullEmailAddress = '';
                if ($request->request->has('r_email') && 0 < \strlen((string) $requestPost['r_email'])) {
                    $fullEmailAddress = $requestPost['r_email'];
                }

                try {
                    $submittedStatement = $statementHandler->savePublicStatement($procedure);
                    $draftStatementId = $submittedStatement->getDraftStatementId();
                    $draftStatementNumber = $submittedStatement->getExternId();
                    $eventDispatcher->dispatch(
                        new GuestStatementSubmittedEvent($submittedStatement, $requestPost['r_text'], $fullEmailAddress)
                    );
                } catch (GdprConsentRequiredException $e) {
                    $this->getMessageBag()->add('warning', 'warning.gdpr.consent');

                    throw $e;
                } catch (ViolationsException $violation) {
                    $errorResponse = [
                        'code'    => 100,
                        'success' => false,
                        'errors'  => $violation->getViolationsAsStrings(),
                    ];

                    $this->logger->error('Statement data violated constraints', [$errorResponse]);

                    return $this->renderJson($errorResponse);
                }
                $template = '@DemosPlanCore/DemosPlanProcedure/public_detail_form_confirmation.html.twig';
            }

            $responseHtml = $this->renderTemplate(
                $template,
                [
                    'templateVars' => [
                        'procedure'           => $procedure,
                        'draftStatementIdent' => $draftStatementId,
                        'number'              => $draftStatementNumber,
                        'confirmationText'    => $statementHandler->getPresentableStatementSubmitConfirmationText(
                            $draftStatementNumber, $currentProcedureService->getProcedureWithCertainty()
                        ),
                    ],
                    'procedure'    => $procedure,
                ]
            )->getContent();

            $responseBody = [
                'code'             => 100,
                'success'          => true,
                'draftStatementId' => $draftStatementId,
                'responseHtml'     => $responseHtml,
            ];

            if ($immediateSubmit && null !== $submitRoute) {
                $responseBody['submitRoute'] = $submitRoute;
            }

            // return result as JSON
            return $this->renderJson($responseBody);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Detailansicht einer Stellungnahme in der Bürgeransicht.
     *
     * @DplanPermissions("area_statements_public_published_public")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_public_participation_published', path: '/verfahren/{procedure}/stellungnahme/{statementID}')]
    public function publicStatementDetailAction(
        StatementService $statementService,
        string $statementID,
    ) {
        $templateVars = [];
        // Das Formular ausgeben und mit Werten befuellen
        $templateVars['statement'] = $statementService->getStatementByIdent(
            $statementID
        );

        // Zähle die Zahl der Mitzeichner
        if (isset($templateVars['statement']['votes'])) {
            $countVotes = is_countable($templateVars['statement']['votes']) ? count($templateVars['statement']['votes']) : 0;
            $templateVars['statement']['votesNum'] = $countVotes;
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/list_public_participation_published_entry.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'statement.public',
            ]
        );
    }

    /**
     * Edit Statement.
     *
     * @DplanPermissions({"area_statements_draft","feature_statements_draft_edit"})
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_edit', path: '/verfahren/{procedure}/stellungnahmen/{statementID}/edit', options: ['expose' => true])]
    public function editStatementAction(
        FileUploadService $fileUploadService,
        MessageBagInterface $messageBag,
        Request $request,
        TranslatorInterface $translator,
        string $procedure,
    ) {
        $urlFragment = '';

        $inData = $this->prepareIncomingData($request, 'statementedit');
        if (\array_key_exists('action', $inData) && 'statementedit' === $inData['action']) {
            // Formulardaten einsammeln

            $requestPost = $request->request->all();

            if ($fileUploadService->hasUploadedFiles($request, 'r_file')) {
                $inData['r_uploaddocument'] = $fileUploadService->prepareFilesUpload($request, 'r_file');
            }

            // Lösche ggf. Zuweisungen
            if (\array_key_exists('delete_element', $requestPost)) {
                $inData['r_elementID'] = '';
                $inData['r_documentID'] = '';
                $inData['r_paragraphID'] = '';
            }
            if (\array_key_exists('delete_document', $requestPost)) {
                $inData['r_documentID'] = '';
            }
            if (\array_key_exists('delete_paragraph', $requestPost)) {
                $inData['r_paragraphID'] = '';
            }
            // Setze Zuweisungen neu
            if (\array_key_exists('r_element_new', $requestPost) && 0 < \strlen((string) $requestPost['r_element_new'])) {
                $inData['r_elementID'] = $requestPost['r_element_new'];
                $inData['r_documentID'] = '';
                $inData['r_paragraphID'] = '';

                if (\array_key_exists('r_paragraph_'.$inData['r_elementID'].'_new', $requestPost)) {
                    $inData['r_paragraphID'] = $requestPost['r_paragraph_'.$inData['r_elementID'].'_new'];
                }

                if (\array_key_exists('r_document_'.$inData['r_elementID'].'_new', $requestPost)) {
                    $inData['r_documentID'] = $requestPost['r_document_'.$inData['r_elementID'].'_new'];
                }
            }

            // save
            $inData['procedureId'] = $procedure;
            $storageResult = $this->draftStatementHandler->updateDraftStatement($inData);

            if (false !== $storageResult && \array_key_exists('id', $storageResult)
                && !\array_key_exists('mandatoryfieldwarning', $storageResult)
            ) {
                $messageBag->add('confirm', $translator->trans('confirm.statement.saved'));
                $urlFragment = '#'.$storageResult['id'];
            }
        }

        return $this->redirect(
            $this->generateUrl('DemosPlan_statement_list_draft', ['procedure' => $procedure]).$urlFragment
        );
    }

    /**
     * @param string $procedure
     * @param string $statementID
     *
     * @return RedirectResponse|Response
     *
     * @throws Throwable
     */
    #[Route(name: 'DemosPlan_statement_send', path: '/verfahren/{procedure}/stellungnahmen/{statementID}/send', options: ['expose' => true])]
    public function sendStatementAction(Breadcrumb $breadcrumb, Request $request, TranslatorInterface $translator, $procedure, $statementID)
    {
        $templateVars = [];
        try {
            // Send Statement kann von meheren Stellen aus angesprungen werden. Es muss daher der Ursprungsort mitgegeben werden und dahin zuruckgesprungen werden
            $requestPost = $request->query->all();
            if (\array_key_exists('target', $requestPost) && 'released_group' === $requestPost['target']) {
                $target = 'DemosPlan_statement_list_released_group';
                $permission = 'feature_statements_released_group_email';
                $area = 'area_statements_released';
                $breadcrumb->addItem(
                    [
                        'title' => $translator->trans(
                            'statements.released.group',
                            [],
                            'page-title'
                        ),
                        'url'   => $this->generateUrl(
                            'DemosPlan_statement_list_released_group',
                            ['procedure' => $procedure]
                        ),
                    ]
                );
            } elseif (\array_key_exists('target', $requestPost) && 'released' === $requestPost['target']) {
                $target = 'DemosPlan_statement_list_released';
                $permission = 'feature_statements_released_email';
                $area = 'area_statements_released';
                $breadcrumb->addItem(
                    [
                        'title' => $translator->trans('statements.released', [], 'page-title'),
                        'url'   => $this->generateUrl('DemosPlan_statement_list_released', ['procedure' => $procedure]),
                    ]
                );
            } elseif (\array_key_exists('target', $requestPost) && 'final_group' === $requestPost['target']) {
                $target = 'DemosPlan_statement_list_final_group';
                $permission = 'feature_statements_final_email';
                $area = 'area_statements_final';
                $breadcrumb->addItem(
                    [
                        'title' => $translator->trans('statements.final.group', [], 'page-title'),
                        'url'   => $this->generateUrl(
                            'DemosPlan_statement_list_final_group',
                            ['procedure' => $procedure]
                        ),
                    ]
                );
            } else {
                $target = 'DemosPlan_statement_list_draft';
                $permission = 'feature_statements_draft_email';
                $area = 'area_statements_draft';
                $breadcrumb->addItem(
                    [
                        'title' => $translator->trans('statements'),
                        'url'   => $this->generateUrl('DemosPlan_statement_list_draft', ['procedure' => $procedure]),
                    ]
                );
            }
            $templateVars['breadcrumb'] = $breadcrumb;

            $this->initialize([$area, $permission]);

            // Baue den Stellungnahmetext zusammen
            $draftStatement = $this->draftStatementHandler->getSingleDraftStatement($statementID);
            if (null === $draftStatement) {
                throw DraftStatementNotFoundException::createFromId($statementID);
            }

            $statementParagraph = '';
            $statementDocument = '';
            $statementSingleDocument = '';
            if (isset($draftStatement['element'])) {
                $statementDocument = $draftStatement['element']['title'];
            }
            if (isset($draftStatement['paragraph'])) {
                $statementParagraph = $draftStatement['paragraph']['title'];
            }
            if (isset($draftStatement['document'])) {
                $statementSingleDocument = $draftStatement['document']['title'];
            }

            $mailTemplateVars = [
                'user_name'                => $this->currentUser->getUser()->getFullname(),
                'user_email'               => $this->currentUser->getUser()->getEmail(),
                'procedure_name'           => $this->currentProcedureService->getProcedure()->getName(),
                'organisation_name'        => $this->currentProcedureService->getProcedure()->getOrgaName(),
                'statement_id'             => $draftStatement['number'],
                'statement_document'       => $statementDocument,
                'statement_paragraph'      => $statementParagraph,
                'statement_singleDocument' => $statementSingleDocument,
                'statement_text'           => \html_entity_decode(
                    \strip_tags((string) $draftStatement['text']),
                    \ENT_QUOTES,
                    'utf-8'
                ),
            ];

            $templateVars['mailbody'] = $this->twig
                ->load('@DemosPlanCore/DemosPlanStatement/send_statement_email.html.twig')
                ->renderBlock(
                    'body_plain',
                    [
                        'templateVars' => $mailTemplateVars,
                    ]
                );

            $templateVars['procedure'] = $procedure;
            $templateVars['backroute'] = $target;
            $templateVars['statementID'] = $statementID;
            $templateVars['procedureLayer'] = 'participation';

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanStatement/send_statement.html.twig',
                [
                    'templateVars' => $templateVars,
                    'procedure'    => $procedure,
                    'target'       => $target,
                    'title'        => 'statements.send.per.email',
                ]
            );
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * @DplanPermissions("feature_statements_draft_versions")
     *
     * @param string $procedure   ID of the Procedure
     * @param string $statementID ID of the DraftStatement
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException|UserNotFoundException
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_versions', path: '/verfahren/{procedure}/stellungnahmen/{statementID}/version', options: ['expose' => true])]
    #[Route(name: 'DemosPlan_statement_versiondetail', path: '/verfahren/{procedure}/stellungnahmen/{statementID}/version/{versionID}')]
    public function versionsOfStatementAction(
        Request $request,
        RouterInterface $router,
        string $procedure,
        string $statementID,
    ) {
        $templateVars = [];
        $draftStatementId = $statementID; // actually ID of a DraftStatement
        try {
            $templateVars = [
                'draftStatementVersions' => $this->draftStatementService->getVersionList($draftStatementId),
            ];
        } catch (UserNotFoundException) {
            $this->logger->addError(UserNotFoundException::createFromId($this->currentUser->getUser()->getId()));
        }
        $templateVars['procedureLayer'] = 'participation';

        $refererPathInfo = Request::create($request->headers->get('referer'))->getPathInfo();

        // Remove the scriptname
        $refererPathInfo = str_replace($request->getScriptName(), '', (string) $refererPathInfo);

        // try to match the path with routing
        $routeInfos = $router->match($refererPathInfo);

        // get the Symfony route name
        $refererRoute = $routeInfos['_route'] ?? '';
        $templateVars['backToUrl'] = $refererRoute;

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/versions_of_statement.html.twig',
            [
                'templateVars'    => $templateVars,
                'origStatementId' => $statementID,
                'procedure'       => $procedure,
                'title'           => 'statement.versions',
            ]
        );
    }

    /**
     * Veröffentliche die Stellungnahme für andere TöB.
     *
     * @DplanPermissions("feature_statements_released_group_submit")
     *
     * @param string $procedure
     * @param string $statementID
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_statement_publish', path: '/verfahren/{procedure}/stellungnahme/{statementID}/publish', options: ['expose' => true])]
    public function publishStatementAction(
        DraftStatementHandler $draftStatementHandler,
        TranslatorInterface $translator,
        MessageBagInterface $messageBag,
        $procedure,
        $statementID,
    ): RedirectResponse {
        $userRole = $this->currentUser->getUser()->getDplanRolesString();

        if (Role::CITIZEN !== $userRole) {
            $this->permissions->checkPermission(
                'feature_statements_released_group_submit'
            );
            // Storage Formulardaten übergeben
            $storageResult = $draftStatementHandler->publishHandler(
                [$statementID]
            );
            if (true === $storageResult) {
                $messageBag->add(
                    'confirm',
                    $translator->trans('confirm.statement.published')
                );
            }
        }

        return $this->redirectToRoute(
            'DemosPlan_statement_list_final_group',
            [
                'procedure' => $procedure,
            ]
        );
    }

    /**
     * @DplanPermissions("feature_statements_released_group_submit")
     *
     * Ziehe die Veröffentlichung der Stellungnahme für andere TöB zurück.
     *
     * @param string $procedure
     * @param string $statementID
     *
     * @return RedirectResponse
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_statement_unpublish', path: '/verfahren/{procedure}/stellungnahme/{statementID}/unpublish', options: ['expose' => true])]
    public function unpublishStatementAction(
        DraftStatementHandler $draftStatementHandler,
        TranslatorInterface $translator,
        MessageBagInterface $messageBag,
        $procedure,
        $statementID,
    ) {
        $userRole = $this->currentUser->getUser()->getDplanRolesString();

        if (Role::CITIZEN !== $userRole) {
            $this->permissions->checkPermission(
                'feature_statements_released_group_submit'
            );
            // Storage Formulardaten übergeben
            $storageResult = $draftStatementHandler->unpublishHandler(
                [$statementID]
            );
            if (true === $storageResult) {
                $messageBag->add(
                    'confirm',
                    $translator->trans('confirm.statement.unpublished')
                );
            }
        }

        return $this->redirectToRoute(
            'DemosPlan_statement_list_final_group',
            [
                'procedure' => $procedure,
            ]
        );
    }

    /**
     * Get draftStatement.
     *
     * @DplanPermissions("area_statements")
     *
     * @param string $procedureId      Needed for initializing
     * @param string $draftStatementId
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_get_ajax', path: '/rest/draftStatement/get/{procedureId}/{draftStatementId}', options: ['expose' => true])]
    public function getDraftStatementAjaxAction(DocumentHandler $documentHandler, Request $request, StatementHandler $statementHandler, string $procedureId, $draftStatementId)
    {
        try {
            $draftStatement = $statementHandler->getDraftStatement($draftStatementId);

            // prepare the response
            $response = [
                'code'                 => 100,
                'success'              => true,
                'draftStatement'       => $draftStatement,
                'hasPlanningDocuments' => $documentHandler->hasProcedureElements($procedureId, $this->currentUser->getUser()->getOrganisationId()),
            ];

            // return result as JSON
            return new JsonResponse($response);
        } catch (SessionUnavailableException $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * @DplanPermissions("area_statements")
     *
     * @return JsonResponse
     */
    #[Route(name: 'DemosPlan_statement_get_count_internal', path: '/rest/statement/count/{procedure}')]
    public function getStatementCountInternalAction(Request $request, StatementHandler $statementHandler, string $procedure)
    {
        $userRole = $this->currentUser->getUser()->getDplanRolesString();
        $statementCounts = $statementHandler->getStatementCounts(
            $procedure,
            $userRole,
            $this->currentUser->getUser()
        );

        return new JsonResponse($statementCounts);
    }

    /**
     * Save filters into session to select them again in twig via app.session.get().
     * Users don't want to always set filters again.
     * Resets with GET parameter 'reset'.
     */
    protected function saveDraftListFiltersInSession(Request $request, string $procedureId, string $templateName)
    {
        $session = $request->getSession();

        if (null === $session) {
            throw new BadMethodCallException('Can not save draftListFilters, because the session is null');
        }

        $draftFilterList = $session->get('draftListFilters');

        // Initialize if not exists
        if (!\is_array($draftFilterList)) {
            $draftFilterList = [];
        }

        if (!\array_key_exists($procedureId, $draftFilterList)) {
            $draftFilterList[$procedureId] = [];
        }

        if (!\array_key_exists($templateName, $draftFilterList[$procedureId])) {
            $draftFilterList[$procedureId][$templateName] = null;
        }

        /** @var DraftStatementListFilters $draftListFilterVO */
        $draftListFilterVO = null;
        if (!($draftFilterList[$procedureId][$templateName] instanceof DraftStatementListFilters)) {
            $draftListFilterVO = new DraftStatementListFilters();
        } else {
            $draftListFilterVO = $draftFilterList[$procedureId][$templateName];
        }

        if ($request->query->has('reset')) {
            $draftFilterList[$procedureId][$templateName] = new DraftStatementListFilters();
            $session->set('draftListFilters', $draftFilterList);

            return;
        }

        // Save filters

        if ($request->request->has('f_department')) {
            $f_department = $request->request->get('f_department');
            $draftListFilterVO->setDepartmentId($f_department);
        }

        if ($request->request->has('f_document')) {
            $f_document = $request->request->get('f_document');
            $draftListFilterVO->setElementsId($f_document);
        }

        if ($request->request->has('search_word')) {
            $search_word = $request->request->get('search_word');
            $draftListFilterVO->setSearchWord($search_word);
        }

        if ($request->request->has('f_sort')) {
            $f_sort = $request->request->get('f_sort');
            $draftListFilterVO->setSortBy($f_sort);
        }

        if ($request->request->has('f_sort_ascdesc')) {
            $f_sort_ascdesc = $request->request->get('f_sort_ascdesc');
            $draftListFilterVO->setSortDirection($f_sort_ascdesc);
        }

        if ($request->request->has('f_organisation')) {
            $f_organisation = $request->request->get('f_organisation');
            $draftListFilterVO->setOrganisationId($f_organisation);
        }

        $draftFilterList[$procedureId][$templateName] = $draftListFilterVO;
        $session->set('draftListFilters', $draftFilterList);
    }

    protected function getDraftListFiltersFromSession(Request $request): DraftStatementListFilters
    {
        $session = $request->getSession();

        if (null === $session) {
            throw new BadMethodCallException('Can not get draftListFilters, because the session is null');
        }

        $templateName = $request->get('templateName');
        if (null === $templateName) {
            throw new BadMethodCallException('Could not get templateName from Request. Therefore unable to resolve filters from session.');
        }

        $draftFilterList = $session->get('draftListFilters') ?? [];
        $procedureId = $request->get('procedure');

        /* @var DraftStatementListFilters $procedureFilters */
        return $draftFilterList[$procedureId][$templateName];
    }

    /**
     * @param string $action
     */
    protected function prepareIncomingData(Request $request, $action): array
    {
        $result = [];

        $incomingFields = [
            'statementnew'                 => [
                'action',
                'r_text',
                'r_elementID',
                'r_paragraphID',
                'r_documentID',
                'r_polygon',
            ],
            'list'                         => [
                'action',
                'flip_status',
                'submit',
            ],
            'statementedit'                => [
                'action',
                'delete_file',
                'r_ident',
                'r_text',
                'r_elementID',
                'r_element_id',
                'r_paragraphID',
                'r_paragraph_id',
                'r_recommendation',
                'r_documentID',
                'r_document_id',
                'r_isNegativeReport',
                'r_location',
                'r_county',
                'r_represents',
                'r_location_geometry',
                'r_location',
                'r_location_point',
                'r_location_priority_area_key',
                'r_location_priority_area_type',
                'r_makePublic',
                'location_is_set',
            ],
            'publicstatementnew'           => [
                'action',
                'url',
                'r_loadtime',
                'r_text',
                'r_firstname',
                'r_lastname',
                'r_email',
            ],
            'confirmSubmitPublicStatement' => [
                'action',
                'statement_release',
                'item_check',
                'r_privacy',
                'r_makePublic',
                'r_gdpr_consent',
                'r_confirm_locality',
            ],
        ];

        $request = $request->request->all();

        foreach ($incomingFields[$action] as $key) {
            if (\array_key_exists($key, $request)) {
                $result[$key] = $request[$key];
            }
        }

        // map JS-Style-named fields to old php style named fields. Dööörty
        if (isset($result['r_element_id'])) {
            $result['r_elementID'] = $result['r_element_id'];
            unset($result['r_element_id']);
        }
        if (isset($result['r_paragraph_id'])) {
            $result['r_paragraphID'] = $result['r_paragraph_id'];
            unset($result['r_paragraph_id']);
        }
        if (isset($result['r_document_id'])) {
            $result['r_documentID'] = $result['r_document_id'];
            unset($result['r_document_id']);
        }

        return $result;
    }

    /**
     * Replace phase by translated string.
     *
     * @param array<int, array> $draftStatementList - list of draftStatements, whose phase will be translated
     *
     * @return array<int, array> - equal to the input parameter $statementList, except of the translated phases
     */
    protected function replacePhaseByPhaseNameForDraftStatements(array $draftStatementList): array
    {
        // replace the phase name that is stored within the draftStatement
        foreach ($draftStatementList as $key => $draftStatementArrayFormat) {
            if (\array_key_exists('phase', $draftStatementArrayFormat)
                && \array_key_exists('publicDraftStatement', $draftStatementArrayFormat)
            ) {
                $draftStatementList[$key]['phase'] = $this->globalConfig->getPhaseNameWithPriorityExternal($draftStatementArrayFormat['phase']);
                if (DraftStatement::INTERNAL === $draftStatementArrayFormat['publicDraftStatement']
                ) {
                    $draftStatementList[$key]['phase'] = $this->globalConfig->getPhaseNameWithPriorityInternal($draftStatementArrayFormat['phase']);
                }
            }
        }

        return $draftStatementList;
    }

    /**
     * Replace phase by translated string.
     *
     * @param array<int, Statement> $statementList - list of statements, whose phase will be translated
     *
     * @return array<int, Statement> - equal to the input parameter $statementList, except of the translated phases
     */
    protected function replacePhaseByPhaseNameForVotedStatementList(array $statementList): array
    {
        // replace the phase name that is stored within the votedStatement
        /** @var Statement $statement */
        foreach ($statementList as $statement) {
            $statement->setPhase($this->globalConfig->getPhaseNameWithPriorityExternal($statement->getPhase()));
            if (Statement::INTERNAL === $statement->getPublicStatement()) {
                $statement->setPhase($this->globalConfig->getPhaseNameWithPriorityInternal($statement->getPhase()));
            }
        }

        return $statementList;
    }

    /**
     * Generates a pdf of a list of Draft Statements.
     *
     * @param array      $draftStatementList
     * @param string     $type               list_released_group|single|finalCitizen|list_final_group
     * @param array|null $itemsToExport
     *
     * @return Response
     *
     * @throws Exception
     */
    protected function createPdfDraftStatement(
        $draftStatementList,
        $type,
        Procedure $procedure,
        $itemsToExport = null,
    ) {
        $file = $this->draftStatementService->generatePdf($draftStatementList, $type, $procedure->getId(), $itemsToExport);

        if ('' === $file->getContent()) {
            throw new Exception('PDF-Export fehlgeschlagen');
        }

        $filename = $procedure->getName().$file->getName();

        $response = new Response($file->getContent(), 200);
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename($filename));

        return $response;
    }

    /**
     * Prepares a list of statements and export them as PDF.
     *
     * @param ParameterBag $requestPost
     * @param string       $templateName
     *
     * @return Response|null
     *
     * @throws MessageBagException
     */
    protected function exportStatementList(
        $requestPost,
        StatementListHandlerResult $outputResult,
        $templateName, Procedure $procedure,
    ) {
        // wenn einzelne Stellungnahmen ausgewählt wurde, speicher sie in einem string
        $itemsToExport = $requestPost->get('item_check');
        if (null !== $itemsToExport && 0 < (is_countable($itemsToExport) ? count($itemsToExport) : 0)) {
            $itemsToExport = \implode(',', $itemsToExport);
        }

        try {
            return $this->createPdfDraftStatement(
                $outputResult->getStatementList(),
                $templateName,
                $procedure,
                $itemsToExport
            );
        } catch (Exception $e) {
            $this->getLogger()->error('exportStatementList failed: '.$e);
            $this->getMessageBag()->add('error', 'error.pdf.generation');
        }

        return null;
    }

    /**
     * Deleting a draftstatement.
     *
     * @param string $_route
     * @param string $procedure
     *
     * @throws MessageBagException
     */
    protected function deleteStatement(TranslatorInterface $translator, $_route, $procedure, bool $released, string $draftStatementId): RedirectResponse
    {
        try {
            if (true === $released) {
                $this->permissions->checkPermission(
                    'feature_statements_released_group_delete'
                );
            } else {
                $this->permissions->checkPermission(
                    'feature_statements_draft_delete'
                );
            }

            // Storage Formulardaten uebergeben
            $this->draftStatementHandler->deleteHandler($draftStatementId);

            // @improve T12803
            $this->getMessageBag()->add(
                'confirm',
                $translator->trans('confirm.statement.deleted')
            );
        } catch (Exception) {
            $this->getMessageBag()->add(
                'error',
                $translator->trans('error.delete')
            );
        }

        return $this->redirectToRoute(
            $_route,
            [
                'procedure' => $procedure,
            ]
        );
    }

    /**
     * Release Statement.
     *
     * @param string $procedure
     * @param array  $procedureArray
     *
     * @return RedirectResponse
     *
     * @throws Throwable
     * @throws MessageBagException
     */
    protected function releaseStatement($procedure, array $statementIds, $procedureArray)
    {
        $this->permissions->checkPermission(
            'feature_statements_draft_release'
        );

        $storageResult = false;
        $redirectRoute = 'DemosPlan_statement_list_draft';

        // Storage Formulardaten uebergeben
        if (0 < count($statementIds)) {
            $storageResult = $this->draftStatementHandler->releaseHandler(
                $statementIds,
                $this->currentUser->getUser(),
                $procedureArray
            );
        }

        if (true == $storageResult) {
            $this->getMessageBag()->add('confirm', 'confirm.statements.marked.released');
            $redirectRoute = 'DemosPlan_statement_list_released';
        }

        return $this->redirectToRoute(
            $redirectRoute,
            [
                'procedure' => $procedure,
            ]
        );
    }

    /**
     * Speichere eine manuelle Sortierreihenfolge.
     *
     * @param string $_route
     * @param string $procedure
     *
     * @return RedirectResponse
     *
     * @throws Exception
     */
    protected function saveManualsort(Request $request, TranslatorInterface $translator, $_route, $procedure)
    {
        $this->permissions->checkPermission(
            'feature_statements_manualsort'
        );
        $requestPost = $request->request;

        // Nur wenn eine Sortierreihenfolge gesetzt werden soll, ist manualsort gesetzt
        $requestPost->has('manualsort') ? $manualsort = $requestPost->get('manualsort') : $manualsort = 'delete';

        // manuelle Sortierung uebergeben
        $storageResult = $this->draftStatementHandler->manualSortHandler(
            $procedure,
            'orga:'.$this->currentUser->getUser()->getOrganisationId(),
            $manualsort
        );

        if ($storageResult) {
            $this->getMessageBag()->add(
                'confirm',
                $translator->trans('confirm.sort.saved')
            );
        }

        return $this->redirectToRoute(
            $_route,
            [
                'procedure' => $procedure,
            ]
        );
    }

    /**
     * Speichere eine manuelle Sortierreihenfolge.
     *
     * @param string $statementId
     *
     * @return RedirectResponse
     *
     * @throws Throwable
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function rejectStatement(Request $request, TranslatorInterface $translator, Procedure $procedure, $statementId, UserService $userService)
    {
        $vars = [];
        $this->permissions->checkPermission(
            'feature_statements_released_group_reject'
        );
        $requestPost = $request->request;
        $draftStatementHandler = $this->draftStatementHandler;

        // Storage Formulardaten uebergeben
        $storageResult = $draftStatementHandler->rejectHandler($statementId, $requestPost->get('reject_reason'));
        if (true === $storageResult) {
            $this->getMessageBag()->add(
                'confirm',
                $translator->trans('confirm.statement.rejected')
            );

            // sende eine Email
            $draftStatement = $draftStatementHandler->getSingleDraftStatement($statementId);

            $currentUser = $this->currentUser->getUser();
            $rejectUser = [
                'name'                  => $currentUser->getFullname(),
                'departmentNameLegal'   => $currentUser->getDepartmentNameLegal(),
                'organisationNameLegal' => $currentUser->getOrganisationNameLegal(),
            ];

            $mailTemplateVars = [
                'statement'     => $draftStatement,
                'reject_user'   => $rejectUser,
                'procedureName' => $procedure->getName(),
                'rejectReason'  => $requestPost->get('reject_reason'),
            ];

            $rejectMailBody = $this->twig
                ->load('@DemosPlanCore/DemosPlanStatement/reject_statement_email.html.twig')
                ->renderBlock(
                    'body_plain',
                    [
                        'templateVars' => $mailTemplateVars,
                    ]
                );

            // besorge die die Infos des Users, an den die Mail geschickt werden soll
            $userToSendEmail = $userService->getSingleUser($draftStatement['uId']);

            // verschicke die Mail
            $to = $userToSendEmail->getEmail();
            $from = $currentUser->getEmail();
            $cc = '';
            $mailScope = 'extern';
            $vars['mailsubject'] = $translator->trans('confirm.statement.rejected');
            $vars['mailbody'] = $rejectMailBody;

            $this->mailService->sendMail(
                'dm_stellungnahme',
                'de_DE',
                $to,
                $from,
                $cc,
                '',
                $mailScope,
                $vars
            );
            $this->getMessageBag()->add(
                'confirm',
                $translator->trans('confirm.email.sent')
            );
        }

        return $this->redirectToRoute(
            'DemosPlan_statement_list_released_group',
            [
                'procedure' => $procedure->getId(),
            ]
        );
    }

    /**
     * Reiche ein Statement ein.
     *
     * @param string $_route
     * @param string $procedure
     *
     * @return RedirectResponse
     *
     * @throws MessageBagException
     * @throws Throwable
     */
    protected function submitStatement(
        Request $request,
        $_route,
        $procedure,
        NotificationReceiverRepository $notificationReceiverRepository,
        PermissionsInterface $permissions,
        StatementHandler $statementHandler,
        OrgaHandler $orgaHandler,
        ProcedureService $procedureService)
    {
        // Check whether Orga has shortened Statement Submission and adjust permission check
        $uerOrga = $orgaHandler->getOrga($this->currentUser->getUser()->getOrganisationId());
        $submissionType = $uerOrga->getSubmissionType();
        $permissionToCheck = 'feature_statements_released_group_submit';
        if (Orga::STATEMENT_SUBMISSION_TYPE_SHORT === $submissionType) {
            // on short submission types permission to Release Statement is sufficient to submit statement
            $permissionToCheck = 'feature_statements_draft_release';
        }

        $this->permissions->checkPermission($permissionToCheck);
        $requestPost = $request->request;

        /** @var Procedure $procedureObject */
        $procedureObject = $procedureService->getProcedure($procedure);

        $isStatementValid = true;

        if (!$requestPost->has('item_check')) {
            $isStatementValid = false;
            $this->getMessageBag()->add('warning', 'warning.select.entries');
        }
        if (!$this->permissions->hasPermissionsetWrite()) {
            $isStatementValid = false;
            $this->getMessageBag()->add('error', 'error.statement.submit.nowriteaccess');
        }

        if (
            $this->permissions->hasPermission('feature_statement_notify_counties')
            && $procedureObject->getSettings()->getSendMailsToCounties()
            && (!$requestPost->has('r_receiver') || '' == $requestPost->get('r_receiver'))
        ) {
            $isStatementValid = false;
            $this->getMessageBag()->add('error', 'error.statement.no.county');
        }

        if ($isStatementValid) {
            $receiverId = $requestPost->get('r_receiver', '');
            // if r_receiver has to be required we need a value to submit form
            if ('none' === $receiverId) {
                // reset countyId
                $receiverId = '';
            }
            // Handler Formulardaten uebergeben
            try {
                $gdprConsentReceived = 'on' === $requestPost->get('r_gdpr_consent');
                $statementHandler->submitStatement($requestPost->get('item_check'), $receiverId, false, $gdprConsentReceived);
                $statementNumbers = $statementHandler->getDraftStatementNumbers($requestPost->get('item_check'));
                $numberstring = \implode(', ', $statementNumbers);

                $this->getMessageBag()->add('confirm', 'confirm.statements.marked.submitted');
                $this->getMessageBag()->addChoice(
                    'confirm',
                    'confirm.statements.marked.numbertext',
                    ['numbers'  => $numberstring,
                        'count' => is_countable($statementNumbers) ? count($statementNumbers) : 0, ]
                );

                // is permission to send notification email enabled?
                if ($permissions->hasPermission('feature_statement_notify_counties')
                    && '' != $receiverId
                    && $procedureObject->getSettings()->getSendMailsToCounties()
                ) {
                    $countyNotificationData = $statementHandler->getCountyNotificationData(
                        $requestPost->get('item_check'),
                        $receiverId,
                        $procedure
                    );
                    $orgaName = $countyNotificationData->getOrgaName();
                    $mailBody = $this->twig
                        ->load('@DemosPlanCore/DemosPlanStatement/notify_county_email.html.twig')
                        ->renderBlock(
                            'body_content',
                            [
                                'templateVars' => [
                                    'orga'      => $orgaName,
                                    'procedure' => $countyNotificationData->getProcedure(),
                                    'files'     => $countyNotificationData->getFiles(),
                                ],
                            ]
                        );
                    // Get receiver mail address instead of county
                    $receiver = $notificationReceiverRepository->get($receiverId);

                    $pdfResult = $countyNotificationData->getPdfResult();
                    if ($receiver instanceof NotificationReceiver) {
                        $this->mailService->sendMail(
                            'dm_county_notification',
                            'de_DE',
                            $receiver->getEmail(),
                            '',
                            '',
                            '',
                            MailSend::MAIL_SCOPE_EXTERN,
                            ['mailbody' => $mailBody, 'orga' => $orgaName],
                            [$pdfResult->toArray()]
                        );
                    } else {
                        $this->getLogger()->warning('Could not find NotificationReceiver', ['id' => $receiverId]);
                    }
                }
            } catch (TimeoutException) {
                $this->getMessageBag()->add('error', 'error.timeout');
            } catch (Exception $e) {
                $this->getMessageBag()->add('error', 'error.statements.marked.submitted');
                $this->logger->warning("Error while submitting statements: $e");
            }
        }

        return $this->redirectToRoute(
            $_route,
            ['procedure' => $procedure]
        );
    }

    /**
     * Verschicke Stellungnahme per Email.
     *
     * @param string $_route
     * @param string $procedure
     *
     * @throws Exception
     */
    protected function sendStatement(Request $request, TranslatorInterface $translator, $_route, $procedure): RedirectResponse
    {
        $vars = [];
        $requestPost = $request->request;

        $this->permissions->checkPermission('feature_statements_draft_email');
        $this->permissions->checkPermission('feature_statements_released_email');
        $this->permissions->checkPermission(
            'feature_statements_released_group_email'
        );
        $this->permissions->checkPermission('feature_statements_final_email');

        try {
            $to = $this->getEmailAddresses($translator, explode(',', (string) $requestPost->get('sendasemail_recipient')));
        } catch (InvalidArgumentException) {
            return $this->redirectToRoute(
                'DemosPlan_statement_send',
                [
                    'procedure'   => $procedure,
                    'statementID' => $request->request->get('statementID'),
                ]
            );
        }
        $from = $this->currentUser->getUser()->getEmail();
        $cc = $this->currentUser->getUser()->getEmail();
        $mailScope = 'extern';
        $vars['mailsubject'] = $translator->trans(
            'email.subject.procedure',
            [
                'procedure_name' => $this->currentProcedureService->getProcedure()->getName(),
            ]
        );
        $vars['mailbody'] = $requestPost->get('sendasemail_message');

        $this->mailService->sendMail(
            'dm_stellungnahme',
            'de_DE',
            $to,
            $from,
            $cc,
            '',
            $mailScope,
            $vars
        );
        $this->getMessageBag()->add(
            'confirm',
            $translator->trans('confirm.email.copy.sent')
        );

        return $this->redirectToRoute(
            $_route,
            [
                'procedure' => $procedure,
            ]
        );
    }

    /**
     * Returns an array with valid emails addresses in parameter $emailAddresses.
     * If no valid email address then sets the messages to inform about the error and raises an InvalidArgumentException.
     *
     * @throws MessageBagException|InvalidArgumentException
     */
    private function getEmailAddresses(TranslatorInterface $translator, array $emailAddresses): array
    {
        // trim whitespaces
        $to = \array_map('\trim', $emailAddresses);
        $to = \array_filter($to);
        if (0 === count($to)) {
            $this->getMessageBag()->add(
                'error',
                $translator->trans('error.missing.emailAddress')
            );
            throw new InvalidArgumentException('missing email address');
        }
        $to = \array_filter($to, fn ($emailTo) => filter_var($emailTo, FILTER_VALIDATE_EMAIL));
        if (0 === count($to)) {
            $this->getMessageBag()->add(
                'error',
                $translator->trans('error.email.invalid')
            );
            throw new InvalidArgumentException('Invalid email address');
        }

        return $to;
    }

    /**
     * @param string $procedure
     *
     * @throws Exception
     */
    protected function submitPublicStatementPdfExportHandling(CurrentProcedureService $currentProcedureService, $procedure, DraftStatementHandler $draftStatementHandler, User $demosplanUser, ParameterBag $requestPost): array
    {
        $userFilter = new StatementListUserFilter();
        $userFilter->setReleased(false)->setSubmitted(false);
        $outputResult = $draftStatementHandler->statementListHandler(
            $procedure,
            'own',
            $userFilter,
            null,
            null,
            $demosplanUser,
            null
        );

        if ($requestPost->has('item_check')) {
            $itemsToExport = (array) $requestPost->get('item_check');
        }

        if (isset($itemsToExport) && [] !== $itemsToExport) {
            $chosenDraftStatements = [];
            foreach ($outputResult->getStatementList() as $draftStatement) {
                // wenn kein Statement zurückgegeben wird, zeige sie nicht an
                if (!\in_array($draftStatement['ident'], $itemsToExport, true)) {
                    continue;
                }
                $chosenDraftStatements[] = $draftStatement;
            }
        } else {
            $chosenDraftStatements = $outputResult->getStatementList();
        }

        // wenn einzelne Stellungnahmen ausgewählt wurde, speicher sie in einem string
        $itemsToExport = $requestPost->get('item_check');

        if (\is_array($itemsToExport) && 0 < count($itemsToExport)) {
            $itemsToExport = \implode(',', $itemsToExport);
        } else {
            $exportIds = [];
            foreach ($chosenDraftStatements as $draftStatement) {
                $exportIds[] = $draftStatement['ident'];
            }
            $itemsToExport = \implode(',', $exportIds);
        }

        $procedureObject = $currentProcedureService->getProcedureWithCertainty();

        return [$chosenDraftStatements, $itemsToExport, $procedureObject];
    }

    /**
     * If the gdpr validation is enabled but the user did not accept it returns true.
     * Otherwise returns false.
     *
     * @throws MessageBagException
     */
    protected function invalidGdprCheck(array $inData): bool
    {
        $mustValidateConfirmedGdpr =
            $this->permissions->hasPermission('feature_statement_gdpr_consent_submit');

        $gdprConfirmed =
            \array_key_exists('r_gdpr_consent', $inData)
            && 'on' === $inData['r_gdpr_consent'];

        if ($mustValidateConfirmedGdpr && !$gdprConfirmed) {
            $this->getMessageBag()->add('warning', 'warning.gdpr.consent');

            return true;
        }

        return false;
    }

    /**
     * Returns true if the user did not accept the privacy clause.
     *
     * @throws MessageBagException
     */
    protected function invalidPrivacyCheck(array $inData): bool
    {
        $privacyConfirmed =
            \array_key_exists('r_privacy', $inData)
            && 'on' === $inData['r_privacy'];

        if (!$privacyConfirmed) {
            $this->getMessageBag()->add('warning', 'warning.privacy.confirm');

            return true;
        }

        return false;
    }

    /**
     * If the locality validation is enabled but the user did not accept it returns true.
     * Otherwise returns false.
     *
     * @throws MessageBagException
     */
    protected function invalidLocalityCheck(array $inData): bool
    {
        $mustValidateLocalityConfirmed =
            $this->permissions->hasPermission('feature_require_locality_confirmation');

        $localityConfirmed =
            \array_key_exists('r_confirm_locality', $inData)
            && 'on' === $inData['r_confirm_locality'];

        if ($mustValidateLocalityConfirmed && !$localityConfirmed) {
            $warningMsg = 'warning.local.participant.confirm';
            $this->getMessageBag()->add('warning', $warningMsg);

            return true;
        }

        return false;
    }

    /**
     * List all statements per procedure
     * without any possibilities to edit.
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     *
     * @DplanPermissions("area_admin_statement_list")
     */
    #[Route(name: 'dplan_procedure_statement_list', methods: ['GET'], path: '/verfahren/{procedureId}/einwendungen', options: ['expose' => true])]
    public function readOnlyStatementListAction(
        string $procedureId,
        ProcedureCoupleTokenFetcher $tokenFetcher,
        ProcedureService $procedureService,
    ): Response {
        $procedure = $procedureService->getProcedure($procedureId);

        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        $isSourceAndCoupledProcedure = $tokenFetcher->isSourceAndCoupledProcedure($procedure);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/list_statements.html.twig',
            [
                'procedure'    => $procedureId,
                'title'        => 'statements',
                'templateVars' => [
                    'isSourceAndCoupledProcedure' => $isSourceAndCoupledProcedure,
                ],
            ]
        );
    }

    /**
     * Imports Statements from a xlsx-file.
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     *
     * @DplanPermissions({"feature_statements_import_excel"})
     */
    #[Route(name: 'DemosPlan_statement_import', methods: ['POST'], path: '/verfahren/{procedureId}/stellungnahmen/import', options: ['expose' => true])]
    public function importStatementsAction(
        FileService $fileService,
        ProcedureService $procedureService,
        XlsxStatementImporterFactory $importerFactory,
        ExcelImporter $excelImporter,
        string $procedureId,
        Request $request,
    ): Response {
        $requestPost = $request->request->all();
        $procedure = $procedureService->getProcedure($procedureId);

        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        try {
            // recreate uploaded array
            $uploads = explode(',', (string) $requestPost['uploadedFiles']);
            $files = array_map($fileService->getFileInfo(...), $uploads);
            $importer = $importerFactory->createXlsxStatementImporter($excelImporter);
            $fileNames = [];
            $statementCount = 0;
            /** @var FileInfo $fileInfo */
            foreach ($files as $fileInfo) {
                $localPath = $fileService->ensureLocalFile($fileInfo->getAbsolutePath());
                $localFileInfo = new FileInfo(
                    $fileInfo->getHash(),
                    '',
                    0,
                    '',
                    $localPath,
                    $localPath,
                    null
                );
                $this->importStatementsFromXls($localFileInfo, $importer);
                $fileNames[] = $fileInfo->getFileName();
                $statementCount += count($importer->getCreatedStatements());
                $fileService->deleteFile($fileInfo->getHash());
                $fileService->deleteLocalFile($localPath);
            }
            if ($importer->hasErrors()) {
                return $this->createErrorResponse($procedureId, $importer->getErrorsAsArray());
            }
        } catch (Exception) {
            return $this->redirectToRoute(
                'DemosPlan_procedure_import',
                ['procedureId' => $procedureId]
            );
        }

        return $this->createSuccessResponse($procedureId, $statementCount, $fileNames);
    }

    /**
     * Imports Statements from a xlsx-file inside a zip created by the assessment table export and adds related documents.
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    #[\demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions(permissions: ['feature_statements_participation_import_excel'])]
    #[Route(
        path: '/verfahren/{procedureId}/stellungnahmen/beteilugengsimport',
        name: 'DemosPlan_statement_participation_import',
        options: ['expose' => true],
        methods: [Request::METHOD_POST])
    ]
    public function importParticipationStatementsAction(
        FileService $fileService,
        ProcedureService $procedureService,
        XlsxStatementImporterFactory $importerFactory,
        StatementSpreadsheetImporterWithZipSupport $excelImporter,
        string $procedureId,
        Request $request,
    ): Response {
        $requestPost = $request->request->all();
        $procedure = $procedureService->getProcedure($procedureId);

        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        try {
            // recreate uploaded array
            $uploads = explode(',', (string) $requestPost['uploadedFiles']);
            $files = array_map($fileService->getFileInfo(...), $uploads);
            $importer = $importerFactory->createXlsxStatementImporter($excelImporter);
            $fileNames = [];
            $statementsCount = 0;
            /** @var FileInfo $zipFileInfo */
            foreach ($files as $zipFileInfo) {
                $localPath = $fileService->ensureLocalFile($zipFileInfo->getAbsolutePath());
                $localFileInfo = new FileInfo(
                    $zipFileInfo->getHash(),
                    '',
                    0,
                    '',
                    $localPath,
                    $localPath,
                    null
                );
                $this->importStatementsFromXls($localFileInfo, $importer);

                $fileNames[] = $zipFileInfo->getFileName();
                $statements = $importer->getCreatedStatements();
                $statementsCount += count($statements);

                $fileService->deleteFile($zipFileInfo->getHash());
                $fileService->deleteLocalFile($localPath);
            }
            if ($importer->hasErrors()) {
                return $this->createErrorResponse($procedureId, $importer->getErrorsAsArray());
            }
        } catch (Throwable $e) {
            $this->logger->error('Something went wrong importing Statements from zip', ['exception' => $e]);

            return $this->redirectToRoute(
                'DemosPlan_procedure_import',
                ['procedureId' => $procedureId]
            );
        }

        return $this->createSuccessResponse($procedureId, $statementsCount, $fileNames);
    }

    /**
     * @throws DemosException
     */
    public function importStatementsFromXls(
        FileInfo $fileInfo,
        XlsxStatementImport $importer,
    ): void {
        $splFileInfo = new SplFileInfo(
            $fileInfo->getAbsolutePath(),
            '',
            $fileInfo->getHash()
        );
        try {
            $importer->importFromFile($splFileInfo);
        } catch (RowAwareViolationsException $e) {
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.document.summary',
                ['doc' => $fileInfo->getFileName()]
            );
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.line.summary',
                ['lineNr' => $e->getRow()]
            );
            foreach ($e->getViolationsAsStrings() as $error) {
                $this->getMessageBag()->add('error', $error);
            }
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (MissingDataException) {
            $this->getMessageBag()->add(
                'error',
                'error.missing.data',
                ['fileName' => $fileInfo->getFileName()]
            );
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (UnexpectedWorksheetNameException $e) {
            if ('Abschnitte' === $e->getIncomingTitle()) {
                $this->getMessageBag()->add('error', 'error.wrong.selected.importer');
            } else {
                $this->getMessageBag()->add(
                    'error',
                    'error.worksheet.name',
                    [
                        'worksheetTitle' => $e->getIncomingTitle(),
                        'expectedTitles' => $e->getExpectedTitles(),
                    ]
                );
            }
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (DuplicateInternIdException $e) {
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.document.duplicate.internid'
            );
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (Exception $e) {
            $this->logger->error(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS, ['exception' => $e]);
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.document.unexpected',
                ['doc' => $fileInfo->getFileName()]
            );
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        }
    }

    /**
     * @param list<array{id: int, currentWorksheet: string, lineNumber: int, message: string}> $errors
     */
    protected function createErrorResponse(string $procedureId, array $errors): Response
    {
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_excel_import_errors.html.twig',
            [
                'procedure'  => $procedureId,
                'context'    => 'statements',
                'title'      => 'statements.import',
                'errors'     => $errors,
            ]
        );
    }

    protected function createSuccessResponse(
        string $procedureId,
        int $numberOfCreatedStatements,
        array $fileNames,
    ) {
        $this->getMessageBag()->addChoice(
            'confirm',
            'confirm.statements.imported.from.files.xlsx.format',
            ['count' => $numberOfCreatedStatements, 'fileName' => implode(', ', $fileNames), 'numbers' => (string) $numberOfCreatedStatements]
        );
        $route = 'dplan_procedure_statement_list';
        // Change redirect target if data input user
        if ($this->permissions->hasPermission('feature_statement_data_input_orga')) {
            $route = 'DemosPlan_statement_orga_list';
        }

        return $this->redirectToRoute(
            $route,
            ['procedureId' => $procedureId]
        );
    }

    /**
     * @param Request|DraftStatementListFilters $requestPost
     */
    private function determineListFilters(bool $released, $submitted, Request $request, $requestPost): StatementListUserFilter
    {
        // Filter Definition
        // $submitted kann true, false, oder leer sein. Leer ist er in der released-Liste
        // $submitted kann be: <bool>true/false or <string>'both' in the released-list - could not find an empty string
        // 'both' as a value of $submitted is ignored in order to keep $userFilter->submitted = null;
        $filters = new StatementListUserFilter();
        $filters->setReleased($released);
        if (is_bool($submitted)) {
            $filters->setSubmitted($submitted);
        }

        $action = 'action';
        $flip_status = 'flip_status';
        $submit = 'submit';
        $request = $request->request->all();

        if (\array_key_exists($action, $request)) {
            $filters->setAction($request[$action]);
        }

        if (\array_key_exists($flip_status, $request)) {
            $filters->setFlipStatus($request[$flip_status]);
        }

        if (\array_key_exists($submit, $request)) {
            $filters->setSubmitOfIncomingListField($request[$submit]);
        }

        if ($requestPost->has('f_document') && '' != $requestPost->get('f_document')) {
            // "Dokumente" sind als Elements modelliert
            $filters->setElement($requestPost->get('f_document'));
        }

        if ($requestPost->has('f_department') && '' != $requestPost->get('f_department')) {
            $filters->setDepartment($requestPost->get('f_department'));
        }

        return $filters;
    }

    /**
     * @param Request|DraftStatementListFilters $requestPost
     */
    private function determineListSearch($requestPost)
    {
        if ($requestPost->has('search_word')) {
            return $requestPost->get('search_word');
        }

        return null;
    }

    /**
     * @param Request|DraftStatementListFilters $requestPost
     */
    private function determineListSort($requestPost): ToBy
    {
        if ($requestPost->has('f_sort') && '' !== $requestPost->get('f_sort')) {
            return ToBy::create(
                $requestPost->get('f_sort'),
                $requestPost->get('f_sort_ascdesc')
            );
        }

        return ToBy::create(
            'createdDate',
            'desc'
        );
    }
}
