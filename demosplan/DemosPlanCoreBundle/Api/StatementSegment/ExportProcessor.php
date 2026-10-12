<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\FileNameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentExportInfoExtractor;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Answers the `export` operation of the StatementSegment resource with a file: the segments the
 * provider found (the same query as the list, without paging) are written by the existing exporter.
 *
 * @implements ProcessorInterface<list<Segment>, StreamedResponse>
 */
final class ExportProcessor implements ProcessorInterface
{
    private const OUTPUT_DESTINATION = 'php://output';

    private const FORMATS = [
        'csv'  => [
            'permission'  => 'feature_segments_list_export_csv',
            'contentType' => 'text/csv; charset=utf-8',
        ],
        'xlsx' => [
            'permission'  => 'feature_segments_list_export_xlsx',
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8',
        ],
    ];

    public function __construct(
        private readonly FileNameGenerator $fileNameGenerator,
        private readonly NameGenerator $nameGenerator,
        private readonly PermissionsInterface $permissions,
        private readonly ProcedureHandler $procedureHandler,
        private readonly SegmentExportInfoExtractor $infoExtractor,
        private readonly SegmentsByStatementsExporter $exporter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $format = $context['request']->getRequestFormat();
        $settings = self::FORMATS[$format] ?? throw new BadRequestHttpException(sprintf('Unsupported export format "%s".', $format));
        $this->permissions->checkPermission($settings['permission']);

        $info = $this->infoExtractor->extract();
        $response = new StreamedResponse(match ($format) {
            'csv'  => function () use ($info, $data): void {
                echo $this->exporter->exportSegmentsCsv($info, ...$data);
            },
            'xlsx' => function () use ($info, $data): void {
                $this->exporter->exportSegmentsXlsx($info, ...$data)->save(self::OUTPUT_DESTINATION);
            },
        });

        $procedureId = $operation->getParameters()->get(Resource::PROCEDURE_PARAMETER)->getValue();
        $fileName = $this->fileNameGenerator->getSegmentsExportFileName(
            $this->procedureHandler->getProcedureWithCertainty($procedureId),
            $format,
            $info->getIsFiltered()
        );
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Content-Type', $settings['contentType']);
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename($fileName));

        return $response;
    }
}
