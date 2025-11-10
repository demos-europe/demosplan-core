<?php

declare(strict_types=1);

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
        bool $original = false,
    ): ?Response {
        $exportFormat = $request->request->get('r_export_format');
        // in case that only docx in elements view mode should be exportable override the view mode
        if ('docx' === $exportFormat && $permissions->hasPermission('feature_export_docx_elements_view_mode_only')) {
            $request->request->set('r_view_mode', AssessmentTableViewMode::ELEMENTS_VIEW);
        }
        $exportParameters = $this->getExportParameters($request, $procedureId, $original);
        try {
            $file = $assessmentExporter->export($exportFormat, $exportParameters);

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
    private function getExportParameters(Request $request, string $procedureId, bool $original): array
    {
        $parameters = $this->assessmentHandler->getFormValues($request->request->all());
        $parameters['request']['limit'] = 1_000_000;
        $parameters['searchFields'] = explode(',', (string) $request->request->get('searchFields'));
        $parameters['exportFormat'] = $request->request->get('r_export_format');
        $parameters['procedureId'] = $procedureId;
        $parameters['original'] = $original;
        $exportChoice = Json::decodeToArray($request->request->get('r_export_choice'));
        $parameters['anonymous'] = array_key_exists('anonymous', $exportChoice)
            ? $exportChoice['anonymous']
            : true;
        $parameters['newPagePerStn'] = array_key_exists('newPagePerStn', $exportChoice)
            ? $exportChoice['newPagePerStn']
            : false;
        $parameters['numberStatements'] = array_key_exists('numberStatements', $exportChoice)
            ? $exportChoice['numberStatements']
            : false;
        $parameters['exportType'] = array_key_exists('exportType', $exportChoice)
            ? $exportChoice['exportType']
            : 'statementsOnly';
        $parameters['template'] = array_key_exists('template', $exportChoice)
            ? $exportChoice['template']
            : 'portrait';
        $parameters['sortType'] = array_key_exists('sortType', $exportChoice)
            ? $exportChoice['sortType']
            : AssessmentTableServiceOutput::EXPORT_SORT_DEFAULT;
        try {
            $parameters['viewMode'] = $this->getStringParameter($request, 'r_view_mode');
        } catch (MissingPostParameterException) {
            $parameters['viewMode'] = AssessmentTableViewMode::DEFAULT_VIEW;
        }
        if (AssessmentTableViewMode::ELEMENTS_VIEW === $parameters['viewMode']) {
            $parameters['sort'] = ToBy::createArray('elementsView', 'desc');
        }

        $this->validateParameters($parameters, $procedureId);

        return $parameters;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateParameters(array $parameters, string $procedureId): void
    {
        $error = false;
        $expectedParameters = [
            'procedureId', 'anonymous', 'exportType', 'template', 'original', 'viewMode',
        ];
        foreach ($expectedParameters as $expectedParameter) {
            if (!isset($parameters[$expectedParameter])) {
                $this->logger->error("Missing parameter $expectedParameter");
            }
        }

        if ($parameters['procedureId'] !== $procedureId) {
            $msg = 'Received id #'.$parameters['procedureId'];
            $msg .= ' does not match current Procedure Id #'.$procedureId;
            $this->logger->error($msg);
        }

        if ($error) {
            throw new InvalidArgumentException('Internal error');
        }
    }
}
