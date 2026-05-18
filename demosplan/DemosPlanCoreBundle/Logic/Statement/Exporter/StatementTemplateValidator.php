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

use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use ZipArchive;

/**
 * Validates a planner-uploaded DOCX template against the placeholder whitelist
 * and the segment-rendering-mode rules of {@see StatementViaTemplateExporter}.
 *
 * On any failure the validator composes the user-facing message itself (via the
 * injected {@see TranslatorInterface}) and throws {@see InvalidStatementTemplateException};
 * the caller surfaces `$exception->getMessage()` in the 422 response body.
 *
 * On success it returns the resolved segment-rendering mode constant
 * ({@see self::MODE_AS_PARAGRAPHS} / {@see self::MODE_WITHIN_TABLE}), or `null`
 * when the template has no per-segment placeholders at all.
 */
class StatementTemplateValidator
{
    public const MODE_AS_PARAGRAPHS = 'asParagraphs';
    public const MODE_WITHIN_TABLE = 'withinTable';

    public const MARKER_AS_PARAGRAPHS_OPEN = 'segmentsAsParagraphs';
    public const MARKER_AS_PARAGRAPHS_CLOSE = '/segmentsAsParagraphs';
    public const MARKER_WITHIN_TABLE = 'segmentsWithinTable';

    private const W_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const DOCUMENT_XML_PATH = 'word/document.xml';

    /**
     * Per-segment data placeholders. If any of these appears in the template,
     * a segment-rendering mode marker is required.
     *
     * @var list<string>
     */
    private const SEGMENT_DATA_PLACEHOLDERS = [
        'segmentExternId',
        'segmentText',
        'segmentRecommendation',
        'segmentPlace',
    ];

    /**
     * Every placeholder the planner is allowed to use in the uploaded
     * template. Anything outside this list is a validation error.
     *
     * @var list<string>
     */
    private const WHITELIST = [
        // Submitter address block
        'submitterName',
        'submitterOrgaName',
        'submitterStreet',
        'submitterPostalCode',
        'submitterCity',
        'submitterEmail',
        // Statement / procedure / sender metadata
        'statementExternId',
        'statementSubmitDate',
        'procedureName',
        'procedureExternId',
        'todayDate',
        'planningAgencyName',
        'planner',
        // Per-segment data
        'segmentExternId',
        'segmentText',
        'segmentRecommendation',
        'segmentPlace',
        // Segment-rendering mode markers
        'segmentsAsParagraphs',
        '/segmentsAsParagraphs',
        'segmentsWithinTable',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $absolutePath local-disk path of the uploaded template,
     *                             obtained from {@see \demosplan\DemosPlanCoreBundle\Logic\FileService::ensureLocalFileFromHash()}
     *
     * @return string|null one of self::MODE_* on success, or null when the template has no per-segment placeholders
     *
     * @throws InvalidStatementTemplateException
     */
    public function validate(string $absolutePath): ?string
    {
        $templateProcessor = $this->openTemplate($absolutePath);
        $variables = array_values($templateProcessor->getVariables());

        $this->rejectUnknownPlaceholders($variables);
        $this->rejectIncompleteAsParagraphsPair($variables);
        $this->rejectBothModes($variables);
        $this->rejectSegmentDataWithoutMode($variables);

        if (in_array(self::MARKER_WITHIN_TABLE, $variables, true)) {
            $this->rejectWithinTableMarkerNotInTable($absolutePath);

            return self::MODE_WITHIN_TABLE;
        }
        if (in_array(self::MARKER_AS_PARAGRAPHS_OPEN, $variables, true)) {
            return self::MODE_AS_PARAGRAPHS;
        }

        return null;
    }

    /**
     * @throws InvalidStatementTemplateException
     */
    private function openTemplate(string $absolutePath): TemplateProcessor
    {
        try {
            return new TemplateProcessor($absolutePath);
        } catch (Throwable $exception) {
            throw new InvalidStatementTemplateException(
                $this->trans('docx.export.via_template.error.malformed_docx'),
                0,
                $exception
            );
        }
    }

    /**
     * @param list<string> $variables
     *
     * @throws InvalidStatementTemplateException
     */
    private function rejectUnknownPlaceholders(array $variables): void
    {
        $unknown = array_values(array_diff($variables, self::WHITELIST));
        if ([] === $unknown) {
            return;
        }
        throw new InvalidStatementTemplateException(
            $this->trans(
                'docx.export.via_template.error.unknown_placeholder',
                ['placeholders' => implode(', ', $unknown)]
            )
        );
    }

    /**
     * @param list<string> $variables
     *
     * @throws InvalidStatementTemplateException
     */
    private function rejectIncompleteAsParagraphsPair(array $variables): void
    {
        $hasOpen = in_array(self::MARKER_AS_PARAGRAPHS_OPEN, $variables, true);
        $hasClose = in_array(self::MARKER_AS_PARAGRAPHS_CLOSE, $variables, true);
        if ($hasOpen === $hasClose) {
            return;
        }
        throw new InvalidStatementTemplateException(
            $this->trans('docx.export.via_template.error.as_paragraphs_marker_incomplete')
        );
    }

    /**
     * @param list<string> $variables
     *
     * @throws InvalidStatementTemplateException
     */
    private function rejectBothModes(array $variables): void
    {
        $hasParagraphs = in_array(self::MARKER_AS_PARAGRAPHS_OPEN, $variables, true);
        $hasTable = in_array(self::MARKER_WITHIN_TABLE, $variables, true);
        if (!$hasParagraphs || !$hasTable) {
            return;
        }
        throw new InvalidStatementTemplateException(
            $this->trans('docx.export.via_template.error.both_modes_present')
        );
    }

    /**
     * @param list<string> $variables
     *
     * @throws InvalidStatementTemplateException
     */
    private function rejectSegmentDataWithoutMode(array $variables): void
    {
        $hasSegmentData = [] !== array_intersect(self::SEGMENT_DATA_PLACEHOLDERS, $variables);
        if (!$hasSegmentData) {
            return;
        }
        $hasMode = in_array(self::MARKER_AS_PARAGRAPHS_OPEN, $variables, true)
            || in_array(self::MARKER_WITHIN_TABLE, $variables, true);
        if ($hasMode) {
            return;
        }
        throw new InvalidStatementTemplateException(
            $this->trans('docx.export.via_template.error.segment_data_without_mode')
        );
    }

    /**
     * @throws InvalidStatementTemplateException
     */
    private function rejectWithinTableMarkerNotInTable(string $absolutePath): void
    {
        $documentXml = $this->readDocumentXml($absolutePath);
        if (null !== $documentXml && $this->everyMarkerOccurrenceIsInATable($documentXml)) {
            return;
        }
        throw new InvalidStatementTemplateException(
            $this->trans('docx.export.via_template.error.within_table_not_in_table')
        );
    }

    private function readDocumentXml(string $absolutePath): ?string
    {
        $zip = new ZipArchive();
        if (true !== $zip->open($absolutePath)) {
            return null;
        }
        try {
            $contents = $zip->getFromName(self::DOCUMENT_XML_PATH);
        } finally {
            $zip->close();
        }

        return false === $contents ? null : $contents;
    }

    private function everyMarkerOccurrenceIsInATable(string $documentXml): bool
    {
        $nodes = $this->findMarkerOccurrences($documentXml);
        if (null === $nodes) {
            return false;
        }
        if (0 === $nodes->length) {
            return false;
        }

        return $this->allHaveTableAncestor($nodes);
    }

    private function findMarkerOccurrences(string $documentXml): ?DOMNodeList
    {
        $document = new DOMDocument();
        if (!@$document->loadXML($documentXml)) {
            return null;
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W_NAMESPACE);
        $query = sprintf('//w:t[contains(text(), "${%s}")]', self::MARKER_WITHIN_TABLE);
        $result = $xpath->query($query);

        return false === $result ? null : $result;
    }

    private function allHaveTableAncestor(DOMNodeList $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$this->hasTableAncestor($node)) {
                return false;
            }
        }

        return true;
    }

    private function hasTableAncestor(DOMNode $node): bool
    {
        $ancestor = $node->parentNode;
        while (null !== $ancestor) {
            if ('w:tbl' === $ancestor->nodeName) {
                return true;
            }
            $ancestor = $ancestor->parentNode;
        }

        return false;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}
