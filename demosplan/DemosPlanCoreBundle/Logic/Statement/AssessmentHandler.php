<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\HandlerException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ViewOrientation;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\UserFilterSetService;
use demosplan\DemosPlanCoreBundle\Logic\SimpleSpreadsheetService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\StatementHandlingResult;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\DocxExportResult;
use Exception;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AssessmentHandler extends CoreHandler
{
    /**
     * @var AssessmentTableServiceOutput
     */
    protected $assessmentTableServiceOutput;

    /** @var StatementService */
    protected $statementService;

    /** @var StatementFragmentService */
    protected $statementFragmentService;

    /** @var UserFilterSetService */
    protected $userFilterSetService;

    /** @var HashedQueryService */
    protected $filterSetService;

    /** @var TranslatorInterface */
    protected $translator;

    /**
     * @var SimpleSpreadsheetService
     */
    protected $simpleSpreadsheetService;

    public function __construct(
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        private readonly CurrentUserInterface $currentUser,
        private readonly GlobalConfig $globalConfig,
        HashedQueryService $filterSetService,
        MessageBagInterface $messageBag,
        private readonly PresentableOriginalStatementFactory $presentableOriginalStatementFactory,
        private readonly ProcedureService $procedureService,
        private readonly RouterInterface $router,
        SimpleSpreadsheetService $simpleSpreadsheetService,
        StatementFragmentService $statementFragmentService,
        StatementService $statementService,
        TranslatorInterface $translator,
        UserFilterSetService $userFilterSetService,
    ) {
        parent::__construct($messageBag);
        $this->assessmentTableServiceOutput = $assessmentTableServiceOutput;
        $this->filterSetService = $filterSetService;
        $this->simpleSpreadsheetService = $simpleSpreadsheetService;
        $this->statementFragmentService = $statementFragmentService;
        $this->statementService = $statementService;
        $this->translator = $translator;
        $this->userFilterSetService = $userFilterSetService;
    }

    /**
     * Set given values as filter parameters as array. If no values are given, use defaults.
     * See sister-method: updateFilterSetParametersInRequest.
     */
    public function createFilterSetParametersInArray(array $filterSetParameters = []): array
    {
        // set default vars
        $viewModeString = $this->globalConfig->getAssessmentTableDefaultViewMode();
        $defaultFilterValues = [];
        // @improve T12957
        $defaultFilterValues['filter_fragments_status'] = [];
        // @improve T12957
        $defaultFilterValues['filter_status'] = [];
        $defaultFilterValues['filter_externId'] = [];
        $defaultFilterValues['search_fields'] = [];
        $defaultFilterValues['search_word'] = '';
        $defaultFilterValues['sort'] = 'submitDate:desc';
        $defaultFilterValues['view_mode'] = $viewModeString;

        $paramArray = [];
        // set either input vars or default vars
        foreach ($defaultFilterValues as $key => $value) {
            $paramArray[$key] = $filterSetParameters[$key] ?? $value;
        }

        return $paramArray;
    }

    /**
     * @throws ProcedureNotFoundException
     * @throws Exception
     * @throws MessageBagException
     */
    public function generateOriginalStatementsDocx(string $procedureId): DocxExportResult
    {
        $rParams = [
            'filters'   => ['original' => 'IS NULL'],
            'request'   => ['limit' => 1_000_000],
            'items'     => [],
            'procedure' => $procedureId,
        ];

        $outputResult = $this->assessmentTableServiceOutput->getStatementListHandler(
            $procedureId,
            $rParams
        );

        $procedure = $this->procedureService->getProcedure($procedureId);
        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        $isPublicUser = $this->currentUser->getUser()->isPublicUser();
        $procedureName = $this->assessmentTableServiceOutput->selectProcedureName($procedure, $isPublicUser);

        $statementsAsArrays = $outputResult->getStatements();
        $statementsAsObjects = $this->elasticsearchStatementsToObjects($statementsAsArrays);
        $presentableOriginalStatements = array_map(
            $this->presentableOriginalStatementFactory->createFromStatement(...),
            $statementsAsObjects
        );

        $phpWord = $this->assessmentTableServiceOutput->buildOriginalStatementDocxExport($procedure, $presentableOriginalStatements);

        return new DocxExportResult(
            'Originalstellungnahmen_'.$procedureName.'.pdf',
            IOFactory::createWriter($phpWord, 'Word2007')
        );
    }

    /**
     * Takes the status attribute and creates filterhash-links.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/filterhash/ Wiki: Filterhashes
     *
     * @throws Exception
     */
    public function generateAssessmentTableFilterLinkFromExternId(Statement $statement): string
    {
        // First, the status is transformed into the right format for the following method.
        $filterArray = [
            'filter_externId' => [$statement->getExternId()],
        ];

        return $this->generateAssessmentTableFilterLink($statement->getProcedureId(), $filterArray);
    }

    /**
     * @param array<int,array<string,mixed>> $fragments
     *
     * @return array<int,array<string,mixed>>
     */
    public function sortFragmentArraysBySortIndex(array $fragments): array
    {
        return $this->statementFragmentService->sortFragmentArraysBySortIndex($fragments);
    }

    /**
     * @param array[] $statements
     *
     * @return Statement[]
     *
     * @see StatementService::elasticsearchStatementsToObjects()
     *
     * @throws Exception
     */
    protected function elasticsearchStatementsToObjects(array $statements): array
    {
        return $this->statementService->elasticsearchStatementsToObjects($statements);
    }

    /**
     * Export AssessmentTable as docx.
     *
     * @throws HandlerException
     * @throws MessageBagException
     */
    public function exportDocx(
        string $procedureId,
        array $requestPost,
        array $exportChoice,
        string $viewMode,
        bool $original = false,
    ): DocxExportResult {
        $outputResult = $this->prepareOutputResult($procedureId, $original, $requestPost);
        try {
            /**
             * Currently the orientation needs to be guessed from
             * $exportChoice['exportType']. This is ugly but currently the
             * only way to get the orientation until it is separated into
             * its own variable.
             * TODO: make the orientation independent from the template.
             *
             * Content $exportChoice:
             *
             * Example values robob:
             * Kompakte Ansicht Stellungnahmen Keine Gliederung
             * {"anonymous":false,"exportType":"statementsOnly","sortType":"default","template":"condensed"}
             * Kompakte Ansicht Datensätze Keine Gliederung
             * {"anonymous":false,"exportType":"statementsAndFragments","sortType":"default","template":"condensed"}
             * Kompakte Ansicht Stellungnahmen Mit Gliederung
             * {"anonymous":false,"exportType":"statementsOnly","sortType":"byParagraph","template":"condensed"}
             * Kompakte Ansicht Datensätze Mit Gliederung
             * {"anonymous":false,"exportType":"statementsAndFragments","sortType":"byParagraph","template":"condensed"}
             * Querformat
             * {"anonymous":false,"exportType":"statementsAndFragments","sortType":"byParagraph","template":"landscape"}
             *
             * Example values bobhh:
             * Gliederung nach Planunterlagen
             * {"anonymous":true,"exportType":"statementsOnly","sortType":"default","template":"landscape"}
             * Gliederung nach Schlagworten
             * {"anonymous":true,"exportType":"statementsOnly","sortType":"default","template":"landscape"}
             * Keine Gliederung
             * {"anonymous":true,"exportType":"statementsOnly","sortType":"default","template":"landscape"}
             */
            $viewOrientation = str_contains((string) $exportChoice['template'], 'landscape')
                ? ViewOrientation::createLandscape()
                : ViewOrientation::createPortrait();
            $objWriter = $this->assessmentTableServiceOutput->generateDocx(
                $outputResult,
                $exportChoice['template'],
                $exportChoice['anonymous'],
                $exportChoice['numberStatements'],
                $exportChoice['exportType'],
                $viewOrientation,
                $requestPost,
                $exportChoice['sortType'],
                $viewMode
            );
        } catch (Exception $e) {
            $this->getLogger()->warning($e);
            throw HandlerException::assessmentExportFailedException('docx');
        }

        return new DocxExportResult(
            sprintf(
                $this->translator->trans('considerationtable').'-%s.docx',
                Carbon::now('Europe/Berlin')->format('d-m-Y H:i')
            ),
            $objWriter
        );
    }

    /**
     * Creates filterhash-links from given array.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/filterhash/ Wiki: Filterhashes
     *
     * @throws Exception
     */
    public function generateAssessmentTableFilterLink(string $procedureId, array $filterArray): string
    {
        // The status isn't the only required variable. This function creates the entire set of required variables.
        $filterParameters = $this->createFilterSetParametersInArray($filterArray);

        // Here, the actual filterhash is created from the prepared parameters and put into an url
        $filterSet = $this->handleFilterHashWithoutRequest($filterParameters, $procedureId);
        $filterHash = $filterSet->getHash();

        return $this->router->generate(
            'dplan_assessmenttable_view_table',
            [
                'procedureId' => $procedureId,
                'filterHash'  => $filterHash,
            ]
        );
    }

    /**
     * @param string $procedureId
     * @param bool   $original
     * @param array  $requestPost
     *
     * @throws MessageBagException
     */
    public function prepareOutputResult($procedureId, $original, $requestPost): StatementHandlingResult
    {
        $requestPost['procedure'] = $procedureId;
        $requestPost['filters']['original'] = $original ? 'IS NULL' : 'IS NOT NULL';

        if (array_key_exists('items', $requestPost) && 0 < (is_countable($requestPost['items']) ? count($requestPost['items']) : 0)) {
            $requestPost['filters']['id'] = [];
            foreach ($requestPost['items'] as $statementIdOrFragmentId) {
                $requestPost['filters']['id'][] =
                    $this->getStatementIdFromStatementIdOrStatementFragmentId($statementIdOrFragmentId)['statementId'];
            }
        }
        $outputResult = $this->assessmentTableServiceOutput->getStatementListHandler(
            $procedureId,
            $requestPost
        );

        $statements = $outputResult->getStatements();
        $statements = $this->statementService->addSourceStatementAttachments($statements);

        // TODO: this seems to do nothing as the statement changed seems to be just a copy, please verify and delete code or falsify and explain with comment
        foreach ($statements as $statement) {
            // Ersetze die Phase, in der die SN eingegangen ist
            $statement['phase'] = $this->statementService->getInternalOrExternalPhaseName($statement);
        }

        return StatementHandlingResult::createCopyWithDifferentStatements($outputResult, $statements);
    }

    /**
     * Checks if an id is statement fragment id.
     * If it's a fragment id, the associated statement's id is returned.
     * If it's a statement id, the original input id is returned, assuming that it's a statement id.
     */
    public function getStatementIdFromStatementIdOrStatementFragmentId(string $statementOrStatementFragmentId): array
    {
        /** @var StatementFragment $fragment */
        $fragment = $this->statementFragmentService->getStatementFragment($statementOrStatementFragmentId);
        if (null === $fragment) {
            return [
                'statementId' => $statementOrStatementFragmentId,
                'fragmentId'  => null,
            ];
        }

        return [
            'statementId' => $fragment->getStatementId(),
            'fragmentId'  => $fragment->getId(),
        ];
    }

    /**
     * @see StatementService::getFormValues()
     *
     * @param array $rParams
     */
    public function getFormValues($rParams = []): array
    {
        return $this->statementService->getFormValues($rParams);
    }

    /**
     * See method handleFilterHashWithoutRequest(), which is a slightly modified clone of this method.
     * Please be sure to perform changes in both methods.
     *
     * @param string $procedureId
     * @param string $filterHash
     * @param bool   $original
     *
     * @throws Exception
     */
    public function handleFilterHash(Request $request, $procedureId, $filterHash = null, $original = false): HashedQuery
    {
        $filterSet = $this->handleFilterHashWithoutRequest($request->request->all(), $procedureId, $filterHash, $original);

        $this->updateHashListInSession($procedureId, $original, $filterSet, $request->query->all());

        return $filterSet;
    }

    /**
     * @param string $procedureId
     */
    public function updateHashListInSession($procedureId, bool $original, HashedQuery $filterSet, array $parameters)
    {
        // we need a hashList in the session that saves the last hash given to a procedure
        // @todo: maybe we can combine this with code in viewTableAction
        $hashList = $this->getSession()->get('hashList', []);
        if (!array_key_exists($procedureId, $hashList)) {
            $hashList[$procedureId] = [
                'assessment' => [
                    'page'    => 0,
                    'hash'    => null,
                    'r_limit' => 25,
                ],
                'original'   => [
                    'page'    => 0,
                    'hash'    => null,
                    'r_limit' => 25,
                ],
            ];
        }
        if ($original) {
            $hashList[$procedureId]['original']['hash'] = $filterSet->getHash();
            $hashList[$procedureId]['original']['page'] = (int) ($parameters['page']['number'] ?? 1);
        } else {
            $hashList[$procedureId]['assessment']['hash'] = $filterSet->getHash();
            $hashList[$procedureId]['assessment']['page'] = (int) ($parameters['page']['number'] ?? 1);
        }
        $this->getSession()->set('hashList', $hashList);
    }

    /**
     * This method handles the tasks to be done regardless of if a Request object was given. See {@see AssessmentHandler::handleFilterHash()} for
     * handling of a given Request object. It will take any $parameterArray, not necessary coming from an Request
     * object. It will use null as value for $flitherhash and false as value for $original as default values. The
     * filterhash is not saved in the cookie since it's auto-generated and doesn't reflect the users preferences.
     *
     * @param array  $parametersArray
     * @param string $procedureId
     * @param string $filterHash
     * @param bool   $original
     *
     * @throws Exception
     */
    public function handleFilterHashWithoutRequest($parametersArray, $procedureId, $filterHash = null, $original = false): HashedQuery
    {
        $formValues = $this->getFormValues($parametersArray);

        return $this->filterSetService->handleFilterHashWithoutRequest($formValues, $procedureId, $filterHash, $original);
    }

    /**
     * @see StatementService::getProcedureDefaultFilter()
     */
    public function getProcedureDefaultFilter(): array
    {
        return $this->statementService->getProcedureDefaultFilter();
    }

    /**
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function saveUserFilterSet(User $user, $procedureId, Request $request, HashedQuery $filterSet): bool
    {
        $name = $request->request->get('r_save_filter_set_name');
        $userId = $user->getId();

        return $this->getUserFilterSetService()->saveUserFilterSet($procedureId, $userId, $name, $filterSet);
    }

    /**
     * @throws Exception thrown if UserFilterSetService was not set
     */
    protected function getUserFilterSetService(): UserFilterSetService
    {
        if (!$this->userFilterSetService instanceof UserFilterSetService) {
            throw new Exception('userFilterSetService not set');
        }

        return $this->userFilterSetService;
    }
}
