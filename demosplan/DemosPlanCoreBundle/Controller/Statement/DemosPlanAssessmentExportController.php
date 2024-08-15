<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\AssessmentTableZipExportException;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidPostParameterTypeException;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableExporterStrategy;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\ExportParameters;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Exception;
use Psr\Log\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use function array_key_exists;

/**
 * Assessment Table export.
 */
class DemosPlanAssessmentExportController extends BaseController
{
    public function __construct(private readonly AssessmentHandler $assessmentHandler)
    {
    }

    /**
     * An Assessment table export Action that can handle all types of exports
     * specified in the export options yml.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @throws Exception
     */
    #[Route(
        path: '/verfahren/abwaegung/export/{procedureId}',
        name: 'DemosPlan_assessment_table_export',
        options: ['expose' => true],
        methods: ['POST', 'GET']
    )]
    #[Route(path: '/verfahren/abwaegung/original/export/{procedureId}',
        name: 'DemosPlan_assessment_table_original_export',
        options: ['expose' => true],
        defaults: ['original' => true]
    )]
    public function exportAction(
        Request $request,
        AssessmentTableExporterStrategy $assessmentExporter,
        FileResponseGeneratorStrategy $responseGenerator,
        PermissionsInterface $permissions,
        string $procedureId,
        bool $original = false
    ): ?Response {
        $exportFormat = $request->request->get('r_export_format');
        // in case that only docx in elements view mode should be exportable override the view mode
        if ('docx' === $exportFormat && $permissions->hasPermission('feature_export_docx_elements_view_mode_only')) {
            $request->request->set('r_view_mode', AssessmentTableViewMode::ELEMENTS_VIEW);
        }
        $exportParameters = $this->getExportParameters($request, $procedureId, $original);
        try {
            $file = $assessmentExporter->export($exportFormat, $exportParameters->toArray());

            $response = $responseGenerator($exportFormat, $file);
        } catch (AssessmentTableZipExportException $e) {
            $this->getMessageBag()->add($e->getLevel(), $e->getUserMsg());

            return $this->redirectBack($request);
        } catch (DemosException $e) {
            $this->getMessageBag()->add('warning', $e->getUserMsg());

            return $this->redirectBack($request);
        }

        return $response;
    }

    /**
     * @throws InvalidPostParameterTypeException
     * @throws JsonException
     */
    private function getExportParameters(Request $request, string $procedureId, bool $original): ExportParameters
    {
        $exportParameters = new ExportParameters();
        $exportParameters->setFormValues($this->assessmentHandler->getFormValues($request->request->all()));
        $exportParameters->setRequestLimit(1_000_000);
        $exportParameters->setSearchFields(
            explode(',', (string) $request->request->get('searchFields'))
        );
        $exportParameters->setExportFormat($request->request->get('r_export_format'));
        $exportParameters->setProcedureId($procedureId);
        $exportParameters->setIsOriginalStatementExport($original);
        $exportChoice = Json::decodeToArray($request->request->get('r_export_choice'));
        if (array_key_exists('anonymous', $exportChoice)) {
            $exportParameters->setAnonymous($exportChoice['anonymous']);
        }
        if (array_key_exists('exportType', $exportChoice)) {
            $exportParameters->setExportType($exportChoice['exportType']);
        }
        if (array_key_exists('template', $exportChoice)) {
            $exportParameters->setTemplate($exportChoice['template']);
        }
        if (array_key_exists('sortType', $exportChoice)) {
            $exportParameters->setSortType($exportChoice['sortType']);
        }
        try {
            $viewMode = $this->getStringParameter($request, 'r_view_mode');
        } catch (MissingPostParameterException) {
            $viewMode = AssessmentTableViewMode::DEFAULT_VIEW;
        }
        if (AssessmentTableViewMode::ELEMENTS_VIEW === $viewMode) {
            $exportParameters->setSort(ToBy::createArray('elementsView', 'desc'));
        }

        $exportParameters->setViewMode($viewMode);

        return $exportParameters->lock();
    }
}
