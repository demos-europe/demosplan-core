<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter;

use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\ExportTemplateData;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Interface AssessmentTableFileExporterInterface.
 */
abstract class AssessmentTableFileExporterAbstract
{
    /** @var CurrentProcedureService */
    protected $currentProcedureService;

    /** @var AssessmentHandler */
    protected $assessmentHandler;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var AssessmentTableServiceOutput */
    protected $assessmentTableOutput;

    /** @var LoggerInterface */
    protected $logger;

    /** @var Session */
    protected $session;

    /**
     * @var StatementHandler
     */
    protected $statementHandler;

    public function __construct(
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CurrentProcedureService $currentProcedureService,
        AssessmentHandler $assessmentHandler,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        RequestStack $requestStack,
        StatementHandler $statementHandler
    ) {
        $this->assessmentHandler = $assessmentHandler;
        $this->assessmentTableOutput = $assessmentTableServiceOutput;
        $this->currentProcedureService = $currentProcedureService;
        $this->logger = $logger;
        $this->session = $requestStack->getSession();
        $this->translator = $translator;
        $this->statementHandler = $statementHandler;
    }

    /**
     * Generates an array implementing the file for the supported formats.
     */
    abstract public function __invoke(array $parameters): array;

    /**
     * Check whether the implementation can generate the Response object for the given format.
     */
    abstract public function supports(string $format): bool;

    /**
     * @param bool   $original
     * @param string $template
     * @param bool   $anonymous
     */
    protected function selectExportTemplateData($original, $template, $anonymous): ExportTemplateData
    {
        $translator = $this->translator;

        // define various variables based on cases
        if ($original) {
            switch ($template) {
                case 'condensed':
                    $filenamePrefix = 'Abwaegungstabelle';
                    $templateName = 'export_condensed';
                    $title = 'statements.original';
                    break;
                case 'landscape':
                case 'portrait':
                default:
                    $filenamePrefix = 'Originalstellungnahmen';
                    $templateName = 'export_original';
                    $title = 'statements.original';
                    break;
            }
        } elseif ($anonymous) {
            $templateName = 'export_anonymous';
            switch ($template) {
                case 'condensed':
                    $filenamePrefix = $translator->trans('considerationtable').'_ohneNamen';
                    $templateName = 'export_condensed_anonymous';
                    $title = 'assessment.table';
                    break;
                case 'portraitWithFrags':
                case 'landscapeWithFrags':
                    $templateName = 'export_fragments_anonymous';
                    // no break
                case 'landscape':
                case 'portrait':
                default:
                    $filenamePrefix = $translator->trans('considerationtable').'_ohneNamen';
                    $title = 'assessment.table';
            }
        } else {
            switch ($template) {
                case 'condensed':
                    $filenamePrefix = $translator->trans('considerationtable');
                    $templateName = 'export_condensed';
                    $title = 'assessment.table';
                    break;
                case 'landscape':
                case 'portrait':
                default:
                    $filenamePrefix = $translator->trans('considerationtable');
                    $templateName = 'export';
                    $title = 'assessment.table';
            }
        }

        $result = new ExportTemplateData();
        $result->setTemplateName($templateName);
        $result->setFileNamePrefix($filenamePrefix);
        $result->setTitle($title);
        $result->lock();

        return $result;
    }

    /**
     * Add statementIds to current Filter that result from current filterHash.
     *
     * @return array<string|mixed>
     */
    protected function addStatementsFromCurrentQueryHashToFilter(array $requestPost, string $procedureId, $isOriginal = false): array
    {
        if (!array_key_exists('sort', $requestPost) || 0 === count($requestPost['sort'])) {
            $requestPost['sort'] = ToBy::createArray('submitDate', 'desc');
        }
        if ($this->session->has('hashList')) {
            $type = $isOriginal ? 'original' : 'assessment';
            $filterHash = $this->session->get(
                'hashList'
            )[$procedureId][$type]['hash'];
            $outputResult = $this->statementHandler->getResultsByFilterSetHash(
                $filterHash,
                $procedureId
            );

            if (0 < count($outputResult)) {
                $requestPost['filters']['id'] = array_merge(array_keys($outputResult));
            }
        }

        return $requestPost;
    }
}
