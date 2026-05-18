<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Renders a planner-uploaded DOCX template against a single Statement.
 *
 * Validation + segment-mode resolution are delegated to
 * {@see StatementTemplateValidator}; the placeholder → value mapping comes from
 * {@see StatementTemplateDataBuilder}. This class owns only the PHPWord-side
 * orchestration: opening the template, dispatching `cloneBlock`/`cloneRow`
 * according to the resolved mode, filling the per-segment placeholders, and
 * returning the populated {@see TemplateProcessor} for the caller to stream
 * via `saveAs('php://output')`.
 *
 * Throws {@see InvalidStatementTemplateException} (raised by the validator)
 * if the template can not be rendered.
 */
class StatementViaTemplateExporter
{
    public function __construct(
        private readonly StatementTemplateValidator $validator,
        private readonly StatementTemplateDataBuilder $dataBuilder,
        private readonly HtmlHelper $htmlHelper,
    ) {
    }

    /**
     * @param string $absolutePath local-disk path of the uploaded template,
     *                             obtained from {@see \demosplan\DemosPlanCoreBundle\Logic\FileService::ensureLocalFileFromHash()}
     *
     * @throws InvalidStatementTemplateException
     */
    public function export(
        Procedure $procedure,
        Statement $statement,
        string $absolutePath,
    ): TemplateProcessor {
        $mode = $this->validator->validate($absolutePath);
        $data = $this->dataBuilder->build($procedure, $statement);

        $templateProcessor = new TemplateProcessor($absolutePath);
        $this->fillSimplePlaceholders($templateProcessor, $data);
        $this->renderSegments($templateProcessor, $mode, $data->getSegments());

        return $templateProcessor;
    }

    private function fillSimplePlaceholders(TemplateProcessor $templateProcessor, StatementTemplateData $data): void
    {
        $templateProcessor->setValue('submitterName', $data->getSubmitterName() ?? '');
        $templateProcessor->setValue('submitterOrgaName', $data->getSubmitterOrgaName() ?? '');
        $templateProcessor->setValue('submitterStreet', $data->getSubmitterStreet() ?? '');
        $templateProcessor->setValue('submitterPostalCode', $data->getSubmitterPostalCode() ?? '');
        $templateProcessor->setValue('submitterCity', $data->getSubmitterCity() ?? '');
        $templateProcessor->setValue('submitterEmail', $data->getSubmitterEmail() ?? '');
        $templateProcessor->setValue('statementExternId', $data->getStatementExternId() ?? '');
        $templateProcessor->setValue('statementSubmitDate', $data->getStatementSubmitDate() ?? '');
        $templateProcessor->setValue('procedureName', $data->getProcedureName() ?? '');
        $templateProcessor->setValue('procedureExternId', $data->getProcedureExternId() ?? '');
        $templateProcessor->setValue('todayDate', $data->getTodayDate() ?? '');
        $templateProcessor->setValue('planningAgencyName', $data->getPlanningAgencyName() ?? '');
        $templateProcessor->setValue('planner', $data->getPlanner() ?? '');
    }

    /**
     * @param list<Segment> $segments
     */
    private function renderSegments(TemplateProcessor $templateProcessor, ?string $mode, array $segments): void
    {
        if (null === $mode) {
            return;
        }

        $count = count($segments);
        if (StatementTemplateValidator::MODE_AS_PARAGRAPHS === $mode) {
            $templateProcessor->cloneBlock(StatementTemplateValidator::MARKER_AS_PARAGRAPHS_OPEN, $count, true, true);
        } else {
            $templateProcessor->cloneRow(StatementTemplateValidator::MARKER_WITHIN_TABLE, $count);
        }

        $index = 0;
        foreach ($segments as $segment) {
            ++$index;
            $this->fillSegmentPlaceholders($templateProcessor, $segment, $index);
            if (StatementTemplateValidator::MODE_WITHIN_TABLE === $mode) {
                $templateProcessor->setValue(StatementTemplateValidator::MARKER_WITHIN_TABLE.'#'.$index, '');
            }
        }
    }

    private function fillSegmentPlaceholders(TemplateProcessor $templateProcessor, Segment $segment, int $index): void
    {
        $templateProcessor->setValue('segmentExternId#'.$index, $segment->getExternId());
        $templateProcessor->setValue('segmentPlace#'.$index, $segment->getPlace()->getName());
        $templateProcessor->setComplexBlock(
            'segmentText#'.$index,
            $this->buildRichTextFromHtml($segment->getText() ?? '')
        );
        $templateProcessor->setComplexBlock(
            'segmentRecommendation#'.$index,
            $this->buildRichTextFromHtml($segment->getRecommendation() ?? '')
        );
    }

    /**
     * Builds a {@see TextRun} from a segment HTML string, mirroring the
     * sanitize-then-{@see Html::addHtml} sequence used in
     * {@see \demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsExporter::addSegmentHtmlCell()}.
     *
     * `<p>` tags are flattened to `<br/>` line breaks before handoff to
     * {@see Html::addHtml()} because a {@see TextRun} is itself a paragraph
     * and PhpWord refuses to nest a paragraph inside it ("Cannot add TextRun
     * in TextRun"). Inline formatting (bold, italic, color, links) survives;
     * paragraph breaks become line breaks — acceptable v1 fidelity.
     */
    private function buildRichTextFromHtml(string $html): TextRun
    {
        $textRun = new TextRun();
        $cleaned = str_replace([chr(2), chr(3)], '', $html);
        $cleaned = $this->flattenParagraphsToBr($cleaned);
        Html::addHtml($textRun, $this->htmlHelper->getHtmlValidText($cleaned), false, false);

        return $textRun;
    }

    private function flattenParagraphsToBr(string $html): string
    {
        $html = preg_replace('#</p>\s*<p[^>]*>#i', '<br/>', $html) ?? $html;

        return preg_replace('#</?p[^>]*>#i', '', $html) ?? $html;
    }
}
