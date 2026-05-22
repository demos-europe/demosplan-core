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
 * Validation is delegated to {@see StatementTemplateValidator}; the placeholder
 * → value mapping comes from {@see StatementTemplateDataBuilder}. This class
 * owns only the PhpWord-side orchestration: opening the template, filling the
 * simple placeholders, cloning the `${AbschnitteAlsAbsätze}` block per segment,
 * and returning the populated {@see TemplateProcessor} for the caller to stream
 * via `saveAs('php://output')`.
 *
 * The English → German placeholder mapping is applied at the setValue boundary
 * here; the rest of the codebase (VO, builder, tests) keeps the English field
 * names so internal types stay readable.
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
        $this->validator->validate($absolutePath);
        $data = $this->dataBuilder->build($procedure, $statement);

        $templateProcessor = new TemplateProcessor($absolutePath);
        $this->fillSimplePlaceholders($templateProcessor, $data);
        $this->renderSegments($templateProcessor, $data->getSegments());

        return $templateProcessor;
    }

    private function fillSimplePlaceholders(TemplateProcessor $templateProcessor, StatementTemplateData $data): void
    {
        $templateProcessor->setValue('Name', $data->getSubmitterName() ?? '');
        $templateProcessor->setValue('Institution', $data->getSubmitterOrgaName() ?? '');
        $templateProcessor->setValue('Straße', $data->getSubmitterStreet() ?? '');
        $templateProcessor->setValue('Hausnummer', $data->getSubmitterHouseNumber() ?? '');
        $templateProcessor->setValue('Postleitzahl', $data->getSubmitterPostalCode() ?? '');
        $templateProcessor->setValue('Ort', $data->getSubmitterCity() ?? '');
        $templateProcessor->setValue('Stellungnahme-ID', $data->getStatementExternId() ?? '');
        $templateProcessor->setValue('Eingangsnummer', $data->getStatementInternId() ?? '');
        $templateProcessor->setValue('Einreichungsdatum', $data->getStatementSubmitDate() ?? '');
        $templateProcessor->setValue('Verfahrensname', $data->getProcedureName() ?? '');
        $templateProcessor->setValue('Datum', $data->getTodayDate() ?? '');
    }

    /**
     * @param list<Segment> $segments
     */
    private function renderSegments(TemplateProcessor $templateProcessor, array $segments): void
    {
        if ([] === $segments) {
            return;
        }
        $count = count($segments);
        $templateProcessor->cloneBlock(StatementTemplateValidator::MARKER_SEGMENTS_OPEN, $count, true, true);

        $index = 0;
        foreach ($segments as $segment) {
            ++$index;
            $this->fillSegmentPlaceholders($templateProcessor, $segment, $index);
        }
    }

    private function fillSegmentPlaceholders(TemplateProcessor $templateProcessor, Segment $segment, int $index): void
    {
        $templateProcessor->setValue('Abschnitts-ID#'.$index, $segment->getExternId());
        $templateProcessor->setComplexBlock(
            'Abschnittstext#'.$index,
            $this->buildRichTextFromHtml($segment->getText() ?? '')
        );
        $templateProcessor->setComplexBlock(
            'Erwiderung#'.$index,
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
