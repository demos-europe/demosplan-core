<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic\AssessmentTableExporter;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ViewOrientation;
use demosplan\DemosPlanStatementBundle\Exception\HandlerException;
use Exception;

class AssessmentTableDocxExporter extends AssessmentTableFileExporterAbstract
{
    /** @var array */
    private $supportedTypes = ['doc', 'docx'];

    public function supports(string $format): bool
    {
        return in_array($format, $this->supportedTypes, true);
    }

    /**
     * @throws HandlerException
     * @throws MessageBagException
     */
    public function __invoke(array $parameters): array
    {
        $procedureId = $parameters['procedureId'];
        $original = $parameters['original'];
        $viewMode = $parameters['viewMode'];

        $parameters = $this->addStatementsFromCurrentQueryHashToFilter($parameters, $procedureId, $original);
        $outputResult = $this->assessmentHandler->prepareOutputResult($procedureId, $original, $parameters);
        try {
            $viewOrientation = false !== \strpos($parameters['template'], 'landscape')
                ? ViewOrientation::createLandscape()
                : ViewOrientation::createPortrait();
            $objWriter = $this->assessmentTableOutput->generateDocx(
                $outputResult,
                $parameters['template'],
                $parameters['anonymous'],
                $parameters['exportType'],
                $viewOrientation,
                $parameters,
                $parameters['sortType'],
                $viewMode
            );

            $fileName = sprintf(
                $this->translator->trans('considerationtable').'-%s.docx',
                Carbon::now()->format('d-m-Y-H:i')
            );

            $file = [
                'filename' => $fileName,
                'writer'   => $objWriter,
            ];

            return $file;
        } catch (Exception $e) {
            $this->logger->warning($e);
            throw HandlerException::assessmentExportFailedException('docx');
        }
    }
}
