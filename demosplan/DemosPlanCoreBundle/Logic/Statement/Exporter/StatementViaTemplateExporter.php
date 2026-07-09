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

use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Exception\MalformedDocxException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsExporter;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;
use Exception;
use finfo;
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
 * Images in segment HTML are embedded using the standard OOXML relationship mechanism:
 * the image bytes are stored in `word/media/`, a relationship entry is added to
 * `word/_rels/document.xml.rels`, and a VML `<v:imagedata r:id="rIdN"/>` run references
 * the file by that relationship ID. Each `<img>` tag in the segment HTML is pre-processed
 * before the TextRun is built:
 *
 *  1. The image is fetched; its bytes, MIME type, and display dimensions are collected.
 *  2. The `<img>` is replaced by the sentinel {@see StatementTemplateProcessor::IMAGE_SENTINEL}.
 *  3. After {@see TemplateProcessor::setComplexBlock()} serialises the TextRun, the sentinel
 *     text appears inside a `<w:t>` element in `$tempDocumentMainPart`.
 *  4. {@see StatementTemplateProcessor::embedAndInjectNextImage()} embeds the image bytes
 *     into the ZIP archive, registers the relationship, and replaces the sentinel with the
 *     VML run — one image at a time in document order.
 *
 * If an image URL cannot be fetched, the `<img>` is replaced with a visible
 * fallback line ("Bild konnte nicht geladen werden") so planners notice the gap.
 */
class StatementViaTemplateExporter
{
    // Maximum image dimensions for embedded images, in centimetres.
    // Width fits a standard A4 body with 2.5 cm margins; height caps a single image
    // at one full page so it never silently overflows into the next page.
    private const IMAGE_MAX_WIDTH_CM = 14.0;
    private const IMAGE_MAX_HEIGHT_CM = 24.7;

    public function __construct(
        private readonly StatementTemplateValidator $validator,
        private readonly StatementTemplateDataBuilder $statementTemplateDataBuilder,
        private readonly HtmlHelper $htmlHelper,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly FileServiceInterface $fileService,
    ) {
    }

    /**
     * @param string $absolutePath local-disk path of the uploaded template,
     *                             obtained from {@see FileServiceInterface::ensureLocalFileFromHash()}
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
            $templateProcessor = new StatementTemplateProcessor($absolutePath);
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
    private function renderSegments(StatementTemplateProcessor $templateProcessor, array $segments): void
    {
        if ([] === $segments) {
            return;
        }
        $templateProcessor->cloneBlock(StatementTemplateValidator::MARKER_SEGMENTS_OPEN, count($segments), true, true);

        $index = 1;
        foreach ($segments as $segment) {
            $this->fillSegmentPlaceholders($templateProcessor, $segment, $index);
            ++$index;
        }
    }

    private function fillSegmentPlaceholders(StatementTemplateProcessor $templateProcessor, Segment $segment, int $index): void
    {
        $templateProcessor->setValue(StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'#'.$index, $segment->getExternId());
        $this->setRichTextField($templateProcessor, StatementTemplateValidator::PLACEHOLDER_SEGMENT_TEXT.'#'.$index, $segment->getText());
        $this->setRichTextField($templateProcessor, StatementTemplateValidator::PLACEHOLDER_SEGMENT_RECOMMENDATION.'#'.$index, $segment->getRecommendation());
    }

    /**
     * Builds a rich-text run from $html, sets it on the given placeholder, then embeds
     * any images collected during the build into the DOCX archive in document order.
     *
     * Called once per rich-text field per segment: each segment carries an independent
     * `text` field (the original statement excerpt) and a `recommendation` field (the
     * planner's response), both of which may contain images and occupy separate placeholders
     * in the template.
     */
    private function setRichTextField(StatementTemplateProcessor $templateProcessor, string $placeholder, string $html): void
    {
        [$textRun, $imageInfoList] = $this->buildRichTextAndImageRuns($html);
        $templateProcessor->setComplexBlock($placeholder, $textRun);
        foreach ($imageInfoList as ['imageData' => $imageData, 'mimeType' => $mimeType, 'widthPt' => $widthPt, 'heightPt' => $heightPt]) {
            $templateProcessor->embedAndInjectNextImage($imageData, $mimeType, $widthPt, $heightPt);
        }
    }

    /**
     * Builds a {@see TextRun} from a segment HTML string and collects VML image run XMLs
     * for any `<img>` tags found, mirroring the sanitize-then-{@see Html::addHtml} sequence
     * used in {@see SegmentsExporter::addSegmentHtmlCell()}.
     *
     * Images are fetched; each `<img>` is replaced with the sentinel
     * {@see StatementTemplateProcessor::IMAGE_SENTINEL} so that the caller can embed and
     * inject the images at the correct positions after serialisation. Images that cannot
     * be fetched are replaced with a visible fallback line so planners notice the gap.
     *
     * `<p>` tags are flattened to `<br/>` line breaks before handoff to
     * {@see Html::addHtml()} because a {@see TextRun} is itself a paragraph
     * and PhpWord refuses to nest a paragraph inside it ("Cannot add TextRun
     * in TextRun"). Inline formatting (bold, italic, color, links) survives;
     * paragraph breaks become line breaks.
     *
     * @return array{0: TextRun, 1: list<array{imageData: string, mimeType: string, widthPt: string, heightPt: string}>}
     */
    private function buildRichTextAndImageRuns(string $html): array
    {
        // Walk every <img> in the HTML: fetch the image bytes and replace the tag with the
        // sentinel string. The sentinel survives into the serialised OOXML (as plain text
        // inside a <w:t> node) so that embedAndInjectNextImage() can locate and replace it
        // with the proper VML run after setComplexBlock() has written the TextRun to XML.
        // Images that cannot be fetched get a visible fallback line so planners notice the gap.
        $imageInfoList = [];
        $htmlWithSentinels = preg_replace_callback(
            '#<img[^>]*/?>|<img[^>]*>.*?</img>#is',
            function (array $matches) use (&$imageInfoList): string {
                $imageInfo = $this->resolveImageInfo($matches[0]);
                if (null === $imageInfo) {
                    return '<br>'.$this->translator->trans('export.image.load.error').'<br>';
                }
                $imageInfoList[] = $imageInfo;

                return StatementTemplateProcessor::IMAGE_SENTINEL;
            },
            $html
        ) ?? $html;

        $textRun = new TextRun();
        // Remove STX (chr 2) and ETX (chr 3) control characters that can appear in
        // rich-text editor output; they are invalid in XML and would corrupt the DOCX.
        $cleaned = str_replace([chr(2), chr(3)], '', $htmlWithSentinels);
        $cleaned = $this->flattenBlockElementsToBr($cleaned);
        Html::addHtml($textRun, $this->htmlHelper->getHtmlValidText($cleaned), false, false);

        return [$textRun, $imageInfoList];
    }

    /**
     * Fetches the image referenced by an `<img>` tag and returns the raw bytes, MIME type,
     * and display dimensions as a tuple for later embedding via
     * {@see StatementTemplateProcessor::embedAndInjectNextImage()}.
     *
     * Returns null if the src attribute is missing or the image cannot be fetched,
     * in which case the caller should strip the `<img>` rather than inserting a sentinel.
     *
     * @return array{imageData: string, mimeType: string, widthPt: string, heightPt: string}|null
     */
    private function resolveImageInfo(string $imgTag): ?array
    {
        // Without a src attribute there is nothing to embed.
        if (!preg_match('/src=["\']([^"\']+)["\']/', $imgTag, $srcMatches)) {
            return null;
        }

        $rawImage = $this->resolveImageSource($srcMatches[1]);
        if (null === $rawImage) {
            return null;
        }

        // Derive display dimensions in document points, capped to fit within the page body.
        $sizeInfo = getimagesizefromstring($rawImage['imageData']);
        [$widthPt, $heightPt] = $this->computeDocumentDimensions(
            $sizeInfo ?: null,
            self::IMAGE_MAX_WIDTH_CM,
            self::IMAGE_MAX_HEIGHT_CM,
        );

        return [
            'imageData' => $rawImage['imageData'],
            'mimeType'  => $rawImage['mimeType'],
            'widthPt'   => $widthPt,
            'heightPt'  => $heightPt,
        ];
    }

    /**
     * Resolves an image src to raw bytes and MIME type.
     *
     * Handles two source types:
     *  - Inline data URI (`data:{mimeType};base64,{payload}`): decoded directly without a
     *    network call. A malformed URI (missing comma or invalid base64) returns null.
     *  - Regular URL: fetched via {@see self::fetchImageBytes()}; the MIME type is detected
     *    from magic bytes rather than the URL extension, which may be absent or misleading.
     *
     * @return array{imageData: string, mimeType: string}|null
     */
    private function resolveImageSource(string $src): ?array
    {
        if (str_starts_with($src, 'data:')) {
            // Format: data:{mimeType};base64,{payload} — decode payload after the comma.
            // Combine the two failure cases (no comma, bad base64) into a single null check.
            $commaPosition = strpos($src, ',');
            $imageData = false !== $commaPosition
                ? base64_decode(substr($src, $commaPosition + 1), true)
                : false;
            if (false === $imageData) {
                return null;
            }

            return ['imageData' => $imageData, 'mimeType' => substr($src, 5, strpos($src, ';') - 5)];
        }

        // Regular URL — fetchImageBytes() handles both dplan file-hash paths and plain HTTP URLs.
        $imageData = $this->fetchImageBytes($src);

        return null !== $imageData
            ? ['imageData' => $imageData, 'mimeType' => $this->detectMimeType($imageData)]
            : null;
    }

    /**
     * Fetches raw image bytes for the given src.
     *
     * Handles two source types:
     *  - Relative dplan file URLs (`/…/file/{procedureId}/{hash}`): the file hash is extracted
     *    and resolved to a local path via {@see FileServiceInterface::ensureLocalFileFromHash()}.
     *  - Absolute HTTP/HTTPS URLs: fetched directly with file_get_contents.
     *
     * Returns null and logs a warning when the image cannot be read.
     */
    private function fetchImageBytes(string $src): ?string
    {
        if (preg_match('#/file/[^/]+/([0-9a-f]{32,64})#', $src, $hashMatch)) {
            return $this->fetchLocalFileBytes($hashMatch[1], $src);
        }

        $imageData = file_get_contents($src);
        if (false !== $imageData && '' !== $imageData) {
            return $imageData;
        }

        $this->logger->warning('Could not fetch image for template export', ['src' => $src]);

        return null;
    }

    /**
     * Reads image bytes from a local file resolved from a dplan file hash.
     * Returns null and logs a warning when the file cannot be read.
     */
    private function fetchLocalFileBytes(string $hash, string $src): ?string
    {
        $localPath = $this->fileService->ensureLocalFileFromHash($hash);
        $imageData = file_get_contents($localPath);
        if (false !== $imageData && '' !== $imageData) {
            return $imageData;
        }

        $this->logger->warning('Could not read resolved image file for template export', ['src' => $src, 'localPath' => $localPath]);

        return null;
    }

    /**
     * Converts pixel dimensions from getimagesizefromstring() to document points,
     * scaling proportionally so the image fits within the given maximum dimensions.
     *
     * $imageMaxWidthCm and $imageMaxHeightCm are the hard limits in centimetres.
     * The image is first scaled to fit the width limit; if the result is still taller
     * than the height limit it is scaled down again — preserving the aspect ratio in
     * both passes. Assumes 96 DPI screen resolution (1 px = 72/96 pt).
     *
     * When no dimension information is available a fallback of maxWidth × maxWidth/2
     * is returned, keeping the same 2:1 ratio as an uncropped landscape placeholder.
     *
     * @param array<int|string, int|string>|null $imageInfo result of getimagesizefromstring(), or null on failure
     *
     * @return array{0: string, 1: string} width and height as CSS pt strings (e.g. "300pt")
     */
    private function computeDocumentDimensions(
        ?array $imageInfo,
        float $imageMaxWidthCm,
        float $imageMaxHeightCm,
    ): array {
        $maxWidthPt = $imageMaxWidthCm * 72 / 2.54;
        $maxHeightPt = $imageMaxHeightCm * 72 / 2.54;

        if (null === $imageInfo || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
            return [(int) round($maxWidthPt).'pt', (int) round($maxWidthPt / 2).'pt'];
        }

        $widthPt = (int) round((int) $imageInfo[0] * 72 / 96);
        $heightPt = (int) round((int) $imageInfo[1] * 72 / 96);

        if ($widthPt > $maxWidthPt) {
            $heightPt = (int) round($heightPt * $maxWidthPt / $widthPt);
            $widthPt = (int) round($maxWidthPt);
        }

        if ($heightPt > $maxHeightPt) {
            $widthPt = (int) round($widthPt * $maxHeightPt / $heightPt);
            $heightPt = (int) round($maxHeightPt);
        }

        return [$widthPt.'pt', $heightPt.'pt'];
    }

    /**
     * Detects the MIME type of image data using PHP's fileinfo extension.
     * Falls back to `image/png` for unrecognised formats.
     */
    private function detectMimeType(string $imageData): string
    {
        return (new finfo(FILEINFO_MIME_TYPE))->buffer($imageData) ?: 'image/png';
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
     * by {@see StatementTemplateProcessor::IMAGE_SENTINEL} in {@see self::buildRichTextAndImageRuns()}
     * before this method is called. Any that reach this point could not be resolved and should
     * be removed rather than passed to PhpWord.
     *
     * Inline formatting (bold, italic, underline, strikethrough, links) is handled purely
     * as font style on Text elements and is unaffected.
     */
    private function flattenBlockElementsToBr(string $html): string
    {
        // Safety net: remove any <img> tags not already replaced by the sentinel.
        $html = preg_replace('#<img[^>]*/?>|<img[^>]*>.*?</img>#i', '', $html) ?? $html;

        // Lists: each item becomes a bullet line; wrapper tags are stripped.
        $html = preg_replace('#<li[^>]*>#i', '<br/>• ', $html) ?? $html;
        $html = preg_replace('#</li>|</?[uo]l[^>]*>#i', '', $html) ?? $html;

        // Tables: cell boundaries become spaces, row boundaries become line breaks, wrapper tags stripped.
        $html = preg_replace('#</t[dh]>#i', ' ', $html) ?? $html;
        $html = preg_replace('#</tr>#i', '<br/>', $html) ?? $html;
        $html = preg_replace('#</?t(?:able|head|body|foot|r|[dh])[^>]*>#i', '', $html) ?? $html;

        // Headings: closing tag becomes a line break to separate content; opening tag is stripped.
        $html = preg_replace('#</h[1-6]>#i', '<br/>', $html) ?? $html;
        $html = preg_replace('#<h[1-6][^>]*>#i', '', $html) ?? $html;

        // Paragraphs: adjacent paragraph boundary becomes a line break; remaining tags stripped.
        $html = preg_replace('#</p>\s*<p[^>]*>#i', '<br/>', $html) ?? $html;

        return preg_replace('#</?p[^>]*>#i', '', $html) ?? $html;
    }
}
