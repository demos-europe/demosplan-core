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
use demosplan\DemosPlanCoreBundle\Exception\MalformedDocxException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsExporter;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;
use Exception;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;
use Psr\Log\LoggerInterface;

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
        private readonly StatementTemplateDataBuilder $statementTemplateDataBuilder,
        private readonly HtmlHelper $htmlHelper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string $absolutePath local-disk path of the uploaded template,
     *                             obtained from {@see FileService::ensureLocalFileFromHash()}
     *
     * @throws InvalidStatementTemplateException
     */
    public function export(
        Procedure $procedure,
        Statement $statement,
        string $absolutePath,
    ): TemplateProcessor {
        $this->validator->validate($absolutePath);
        $allPlaceholdersData = $this->statementTemplateDataBuilder->build($procedure, $statement);

        try {
            $templateProcessor = new TemplateProcessor($absolutePath);
        } catch (CreateTemporaryFileException|CopyFileException|Exception $exception) {
            $this->logger->error(
                'Failed to open uploaded DOCX template for export',
                ['absolutePath' => $absolutePath, 'exception' => $exception]
            );
            throw new MalformedDocxException('', 0, $exception);
        }
        $this->fillSimplePlaceholders($templateProcessor, $allPlaceholdersData);
        $this->renderSegments($templateProcessor, $allPlaceholdersData->getSegments());

        return $templateProcessor;
    }

    private function fillSimplePlaceholders(TemplateProcessor $templateProcessor, StatementTemplateData $data): void
    {
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_NAME,
            $data->getSubmitterName() ?? ''
        );
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_INSTITUTION,
            $data->getSubmitterOrgaName() ?? ''
        );
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_STREET,
            $data->getSubmitterStreet() ?? ''
        );
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_HOUSE_NUMBER,
            $data->getSubmitterHouseNumber() ?? '');
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_POSTAL_CODE,
            $data->getSubmitterPostalCode() ?? ''
        );
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_CITY,
            $data->getSubmitterCity() ?? ''
        );
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_STATEMENT_EXTERN_ID,
            $data->getStatementExternId() ?? ''
        );
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_STATEMENT_INTERN_ID,
            $data->getStatementInternId() ?? ''
        );
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_STATEMENT_SUBMIT_DATE,
            $data->getStatementSubmitDate() ?? ''
        );
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_PROCEDURE_NAME,
            $data->getProcedureName() ?? '');
        $templateProcessor->setValue(
            StatementTemplateValidator::PLACEHOLDER_TODAY_DATE,
            $data->getTodayDate() ?? ''
        );
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

        $index = 1;
        foreach ($segments as $segment) {
            $this->fillSegmentPlaceholders($templateProcessor, $segment, $index);
            ++$index;
        }
    }

    private function fillSegmentPlaceholders(TemplateProcessor $templateProcessor, Segment $segment, int $index): void
    {
        $templateProcessor->setValue(StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'#'.$index, $segment->getExternId());
        $templateProcessor->setComplexBlock(
            StatementTemplateValidator::PLACEHOLDER_SEGMENT_TEXT.'#'.$index,
            $this->buildRichTextFromHtml($segment->getText())
        );
        $templateProcessor->setComplexBlock(
            StatementTemplateValidator::PLACEHOLDER_SEGMENT_RECOMMENDATION.'#'.$index,
            $this->buildRichTextFromHtml($segment->getRecommendation())
        );
    }

    /**
     * Builds a {@see TextRun} from a segment HTML string, mirroring the
     * sanitize-then-{@see Html::addHtml} sequence used in
     * {@see SegmentsExporter::addSegmentHtmlCell()}.
     *
     * `<p>` tags are flattened to `<br/>` line breaks before handoff to
     * {@see Html::addHtml()} because a {@see TextRun} is itself a paragraph
     * and PhpWord refuses to nest a paragraph inside it ("Cannot add TextRun
     * in TextRun"). Inline formatting (bold, italic, color, links) survives;
     * paragraph breaks become line breaks.
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
