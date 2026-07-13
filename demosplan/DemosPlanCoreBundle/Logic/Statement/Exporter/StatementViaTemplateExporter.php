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
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;
use Exception;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
 *
 * ### Image embedding
 *
 * Images in segment HTML are embedded using PhpWord's native `setImageValue()`.
 * Each `<img>` tag in the segment HTML is pre-processed before the TextRun is built:
 *
 *  1. The image src is resolved to a local file path via
 *     {@see FileService::ensureLocalFileFromHash()}.
 *  2. The `<img>` is replaced by a unique `${DPLANIMAGEn}` placeholder. The placeholder
 *     survives {@see TemplateProcessor::setComplexBlock()} intact (PhpWord's
 *     `fixBrokenMacros` reassembles any split `${…}` nodes before searching).
 *  3. After `setComplexBlock`, {@see TemplateProcessor::setImageValue()} locates each
 *     `${DPLANIMAGEn}` and replaces it with an embedded image run — one call per image,
 *     in document order.
 *
 * If an image URL cannot be resolved, the `<img>` is replaced with a visible
 * fallback line ("Bild konnte nicht geladen werden") so planners notice the gap.
 */
class StatementViaTemplateExporter
{
    // Maximum image dimensions for embedded images, in centimetres.
    // Width fits a standard A4 body with 2.5 cm margins; height caps a single image
    // at one full page so it never silently overflows into the next page.
    private const IMAGE_MAX_WIDTH_CM = 14.0;
    private const IMAGE_MAX_HEIGHT_CM = 24.7;

    // Monotonically increasing counter that generates unique ${DPLANIMAGEn} placeholder
    // names across all segments and rich-text fields within a single export call.
    private int $imageCounter = 0;

    private function getImageCounter(): int
    {
        return $this->imageCounter;
    }

    private function setImageCounter(int $imageCounter): void
    {
        $this->imageCounter = $imageCounter;
    }

    public function __construct(
        private readonly StatementTemplateValidator $validator,
        private readonly StatementTemplateDataBuilder $statementTemplateDataBuilder,
        private readonly HtmlHelper $htmlHelper,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly FileService $fileService,
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

        // PhpWord defaults to output escaping disabled, which allows raw `<`, `>`, and `&`
        // characters to be written verbatim into XML nodes — both via setValue (which checks
        // this flag internally) and via setComplexBlock (whose XMLWriter also respects it).
        // Enabling it for the full export scope ensures all placeholder values are properly
        // XML-escaped regardless of what user-supplied text they contain.
        $escapingWasEnabled = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        try {
            $this->fillSimplePlaceholders($templateProcessor, $allPlaceholdersData);
            $this->renderSegments($templateProcessor, $allPlaceholdersData->getSegments());
        } finally {
            Settings::setOutputEscapingEnabled($escapingWasEnabled);
        }

        return $templateProcessor;
    }

    private function fillSimplePlaceholders(TemplateProcessor $templateProcessor, StatementTemplateData $data): void
    {
        $values = [
            StatementTemplateValidator::PLACEHOLDER_NAME                  => $data->getSubmitterName(),
            StatementTemplateValidator::PLACEHOLDER_INSTITUTION           => $data->getSubmitterOrgaName(),
            StatementTemplateValidator::PLACEHOLDER_STREET                => $data->getSubmitterStreet(),
            StatementTemplateValidator::PLACEHOLDER_HOUSE_NUMBER          => $data->getSubmitterHouseNumber(),
            StatementTemplateValidator::PLACEHOLDER_POSTAL_CODE           => $data->getSubmitterPostalCode(),
            StatementTemplateValidator::PLACEHOLDER_CITY                  => $data->getSubmitterCity(),
            StatementTemplateValidator::PLACEHOLDER_STATEMENT_EXTERN_ID   => $data->getStatementExternId(),
            StatementTemplateValidator::PLACEHOLDER_STATEMENT_INTERN_ID   => $data->getStatementInternId(),
            StatementTemplateValidator::PLACEHOLDER_STATEMENT_SUBMIT_DATE => $data->getStatementSubmitDate(),
            StatementTemplateValidator::PLACEHOLDER_PROCEDURE_NAME        => $data->getProcedureName(),
            StatementTemplateValidator::PLACEHOLDER_TODAY_DATE            => $data->getTodayDate(),
        ];
        foreach ($values as $placeholder => $value) {
            $templateProcessor->setValue($placeholder, $value ?? '');
        }
    }

    /**
     * @param list<Segment> $segments
     */
    private function renderSegments(TemplateProcessor $templateProcessor, array $segments): void
    {
        if ([] === $segments) {
            return;
        }
        $templateProcessor->cloneBlock(StatementTemplateValidator::MARKER_SEGMENTS_OPEN, count($segments), true, true);

        $this->setImageCounter(0);
        $index = 1;
        foreach ($segments as $segment) {
            $this->fillSegmentPlaceholders($templateProcessor, $segment, $index);
            ++$index;
        }
    }

    private function fillSegmentPlaceholders(TemplateProcessor $templateProcessor, Segment $segment, int $index): void
    {
        $templateProcessor->setValue(StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'#'.$index, $segment->getExternId());
        $this->setRichTextField($templateProcessor, StatementTemplateValidator::PLACEHOLDER_SEGMENT_TEXT.'#'.$index, $segment->getText());
        $this->setRichTextField($templateProcessor, StatementTemplateValidator::PLACEHOLDER_SEGMENT_RECOMMENDATION.'#'.$index, $segment->getRecommendation());
    }

    /**
     * Builds a rich-text run from $html, sets it on the given placeholder via setComplexBlock,
     * then uses setImageValue to embed any images in the correct positions.
     *
     * Each `<img>` in $html is replaced with a unique `${DPLANIMAGEn}` placeholder before the
     * TextRun is built. After setComplexBlock serialises the TextRun into the document XML,
     * setImageValue locates each placeholder and replaces it with an embedded image run.
     * PhpWord's fixBrokenMacros ensures the placeholder is found even when the XML serialiser
     * splits it across multiple `<w:t>` nodes.
     */
    private function setRichTextField(TemplateProcessor $templateProcessor, string $placeholder, string $html): void
    {
        [$textRun, $imageList] = $this->buildRichTextWithImagePlaceholders($html);
        $templateProcessor->setComplexBlock($placeholder, $textRun);
        foreach ($imageList as ['placeholder' => $imagePlaceholder, 'localPath' => $localPath, 'widthPx' => $widthPx, 'heightPx' => $heightPx]) {
            $templateProcessor->setImageValue($imagePlaceholder, ['path' => $localPath, 'width' => $widthPx, 'height' => $heightPx]);
        }
    }

    /**
     * Builds a {@see TextRun} from a segment HTML string, replacing each `<img>` tag with a
     * `${DPLANIMAGEn}` placeholder and collecting the resolved image metadata for later embedding.
     *
     * `<p>` tags are flattened to `<br/>` line breaks before handoff to {@see Html::addHtml()}
     * because a {@see TextRun} is itself a paragraph and PhpWord refuses to nest a paragraph
     * inside it ("Cannot add TextRun in TextRun"). Inline formatting (bold, italic, colour,
     * links) survives; paragraph breaks become line breaks.
     *
     * @return array{0: TextRun, 1: list<array{placeholder: string, localPath: string, widthPx: int, heightPx: int}>}
     */
    private function buildRichTextWithImagePlaceholders(string $html): array
    {
        $imageList = [];
        $htmlWithPlaceholders = preg_replace_callback(
            '#<img[^>]*/?>|<img[^>]*>.*?</img>#is',
            function (array $matches) use (&$imageList): string {
                $imageInfo = $this->resolveImageInfo($matches[0]);
                if (null === $imageInfo) {
                    return '<br>'.$this->translator->trans('export.image.load.error').'<br>';
                }
                $this->setImageCounter($this->getImageCounter() + 1);
                $imagePlaceholder = 'DPLANIMAGE'.$this->getImageCounter();
                $imageList[] = ['placeholder' => $imagePlaceholder, ...$imageInfo];

                return '${'.$imagePlaceholder.'}';
            },
            $html
        ) ?? $html;

        $textRun = new TextRun();
        // Remove STX (chr 2) and ETX (chr 3) control characters that can appear in
        // rich-text editor output; they are invalid in XML and would corrupt the DOCX.
        $cleaned = str_replace([chr(2), chr(3)], '', $htmlWithPlaceholders);
        $cleaned = $this->flattenBlockElementsToBr($cleaned);
        Html::addHtml($textRun, $this->htmlHelper->getHtmlValidText($cleaned), false, false);

        return [$textRun, $imageList];
    }

    /**
     * Resolves an `<img>` tag to a local file path and display dimensions.
     *
     * Only dplan file-hash URLs (`/file/{procedureId}/{hash}`) are supported — the editor
     * produces exclusively this format when images are inserted via "Bild einfügen".
     * Tags with any other src (e.g. external URLs pasted as raw HTML) are skipped with a warning,
     * as is a hash-shaped src that {@see FileService::ensureLocalFileFromHash()} fails to resolve
     * (deleted file, storage failure, …). In both cases the caller falls back to a visible
     * "image could not be loaded" line instead of the export failing outright.
     *
     * @return array{localPath: string, widthPx: int, heightPx: int}|null
     */
    private function resolveImageInfo(string $imgTag): ?array
    {
        // Without a src attribute there is nothing to embed.
        if (!preg_match('/src=["\']([^"\']+)["\']/', $imgTag, $srcMatches)) {
            return null;
        }

        // Only dplan file-hash URLs are supported.
        // Two legacy-compatible forms exist:
        //   /file/{hash}                  (old format — no procedureId)
        //   /file/{procedureId}/{hash}    (current format)
        if (!preg_match('#/file/(?:[^/]+/)?([0-9a-f]{32,64})(?:[/?#]|$)#', $srcMatches[1], $hashMatch)) {
            $this->logger->warning(
                'Unrecognised image src in segment HTML — only dplan file-hash URLs are supported',
                ['src' => $srcMatches[1]]
            );

            return null;
        }

        try {
            $localPath = $this->fileService->ensureLocalFileFromHash($hashMatch[1]);
        } catch (Exception $exception) {
            // The hash-shaped src looked valid but the file is gone (deleted, storage
            // failure, …). Fall back the same way as an unrecognised src, rather than
            // letting the exception abort the whole export.
            $this->logger->warning(
                'Failed to resolve local file for image src in segment HTML',
                ['src' => $srcMatches[1], 'exception' => $exception]
            );

            return null;
        }
        $sizeInfo = getimagesize($localPath);

        [$widthPx, $heightPx] = $this->computeDocumentDimensions(
            false !== $sizeInfo ? $sizeInfo : null,
            self::IMAGE_MAX_WIDTH_CM,
            self::IMAGE_MAX_HEIGHT_CM,
        );

        return ['localPath' => $localPath, 'widthPx' => $widthPx, 'heightPx' => $heightPx];
    }

    /**
     * Scales pixel dimensions from {@see getimagesize()} to fit within the given maximum
     * dimensions in centimetres, preserving the aspect ratio.
     *
     * The image is first scaled to fit the width limit; if the result is still taller than the
     * height limit it is scaled down again. Assumes 96 DPI for the cm → px conversion.
     *
     * When no dimension information is available a fallback of maxWidth × maxWidth/2 pixels
     * is returned, keeping a 2:1 landscape placeholder ratio.
     *
     * @param array<int|string, int|string>|null $imageInfo result of getimagesize(), or null on failure
     *
     * @return array{0: int, 1: int} width and height in pixels
     */
    private function computeDocumentDimensions(
        ?array $imageInfo,
        float $imageMaxWidthCm,
        float $imageMaxHeightCm,
    ): array {
        $maxWidthPx = (int) round($imageMaxWidthCm * 96 / 2.54);
        $maxHeightPx = (int) round($imageMaxHeightCm * 96 / 2.54);

        if (null === $imageInfo || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
            return [$maxWidthPx, (int) round($maxWidthPx / 2)];
        }

        $widthPx = (int) $imageInfo[0];
        $heightPx = (int) $imageInfo[1];

        if ($widthPx > $maxWidthPx) {
            $heightPx = (int) round($heightPx * $maxWidthPx / $widthPx);
            $widthPx = $maxWidthPx;
        }

        if ($heightPx > $maxHeightPx) {
            $widthPx = (int) round($widthPx * $maxHeightPx / $heightPx);
            $heightPx = $maxHeightPx;
        }

        return [$widthPx, $heightPx];
    }

    /**
     * Converts block-level HTML elements and unsupported content to inline-safe markup
     * before handing HTML to {@see Html::addHtml()} with a {@see TextRun} container.
     *
     * PhpWord's HTML parser calls {@see AbstractContainer::addTable()},
     * {@see AbstractContainer::addListItem()}, and {@see AbstractContainer::addTextRun()}
     * when it encounters `<table>`, list, and heading nodes respectively.
     * {@see TextRun} is an inline element — those element types are not in its valid-container
     * list and throw {@see BadMethodCallException} at runtime. Lists additionally call
     * {@see PhpWord\PhpWord::addNumberingStyle()} via {@see AbstractContainer::getPhpWord()},
     * which returns null on a TextRun and causes a fatal error.
     *
     * `<img>` tags are stripped as a safety net here; they should already have been replaced
     * by `${DPLANIMAGEn}` placeholders in {@see self::buildRichTextWithImagePlaceholders()}
     * before this method is called. Any that reach this point could not be resolved and should
     * be removed rather than passed to PhpWord.
     *
     * Inline formatting (bold, italic, underline, strikethrough, links) is handled purely
     * as font style on Text elements and is unaffected.
     */
    private function flattenBlockElementsToBr(string $html): string
    {
        // Safety net: remove any <img> tags not already replaced by a placeholder.
        $html = preg_replace('#<img[^>]*/?>|<img[^>]*>.*?</img>#i', '', $html) ?? $html;

        // Lists: each item becomes a bullet line; wrapper tags are stripped.
        $html = preg_replace('#<li[^>]*>#i', '<br/>• ', $html) ?? $html;
        $html = preg_replace('#</li>|</?[uo]l[^>]*>#i', '', $html) ?? $html;

        // Tables: cell boundaries become spaces, row boundaries become line breaks, wrapper tags stripped.
        $html = preg_replace('#</t[dh]>#i', ' ', $html) ?? $html;
        $html = str_ireplace('</tr>', '<br/>', $html);
        $html = preg_replace('#</?t(?:able|head|body|foot|r|[dh])[^>]*>#i', '', $html) ?? $html;

        // Headings: closing tag becomes a line break to separate content; opening tag is stripped.
        $html = preg_replace('#</h[1-6]>#i', '<br/>', $html) ?? $html;
        $html = preg_replace('#<h[1-6][^>]*>#i', '', $html) ?? $html;

        // Paragraphs: adjacent paragraph boundary becomes a line break; remaining tags stripped.
        $html = preg_replace('#</p>\s*<p[^>]*>#i', '<br/>', $html) ?? $html;

        return preg_replace('#</?p[^>]*>#i', '', $html) ?? $html;
    }
}
