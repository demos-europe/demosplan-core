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
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Validates a planner-uploaded DOCX template against the placeholder whitelist
 * and the segment-block marker rules of {@see StatementViaTemplateExporter}.
 *
 * The template has exactly one repeatable region — paragraphs between
 * `${AbschnitteAlsAbsätze}` and `${/AbschnitteAlsAbsätze}` — which PhpWord
 * clones via `cloneBlock` per segment.
 *
 * On any failure the validator composes the user-facing message itself (via the
 * injected {@see TranslatorInterface}) and throws {@see InvalidStatementTemplateException};
 * the caller surfaces `$exception->getMessage()` in the 422 response body.
 */
class StatementTemplateValidator
{
    public const MARKER_SEGMENTS_OPEN = 'AbschnitteAlsAbsätze';
    public const MARKER_SEGMENTS_CLOSE = '/AbschnitteAlsAbsätze';

    // Submitter address block
    public const PLACEHOLDER_NAME = 'Name';
    public const PLACEHOLDER_INSTITUTION = 'Institution';
    public const PLACEHOLDER_STREET = 'Straße';
    public const PLACEHOLDER_HOUSE_NUMBER = 'Hausnummer';
    public const PLACEHOLDER_POSTAL_CODE = 'Postleitzahl';
    public const PLACEHOLDER_CITY = 'Ort';

    // Statement / procedure / sender metadata
    public const PLACEHOLDER_STATEMENT_EXTERN_ID = 'Stellungnahme-ID';
    public const PLACEHOLDER_STATEMENT_INTERN_ID = 'Eingangsnummer';
    public const PLACEHOLDER_STATEMENT_SUBMIT_DATE = 'Einreichungsdatum';
    public const PLACEHOLDER_PROCEDURE_NAME = 'Verfahrensname';
    public const PLACEHOLDER_TODAY_DATE = 'Datum';

    // Per-segment data
    public const PLACEHOLDER_SEGMENT_EXTERN_ID = 'Abschnitts-ID';
    public const PLACEHOLDER_SEGMENT_TEXT = 'Abschnittstext';
    public const PLACEHOLDER_SEGMENT_RECOMMENDATION = 'Erwiderung';

    /**
     * Per-segment data placeholders. If any of these appears in the template,
     * the `${AbschnitteAlsAbsätze}` … `${/AbschnitteAlsAbsätze}` block must
     * wrap them.
     *
     * @var list<string>
     */
    private const SEGMENT_DATA_PLACEHOLDERS = [
        self::PLACEHOLDER_SEGMENT_EXTERN_ID,
        self::PLACEHOLDER_SEGMENT_TEXT,
        self::PLACEHOLDER_SEGMENT_RECOMMENDATION,
    ];

    /**
     * Every placeholder the planner is allowed to use in the uploaded
     * template. Anything outside this list is a validation error.
     *
     * @var list<string>
     */
    private const WHITELIST = [
        // Submitter address block
        self::PLACEHOLDER_NAME,
        self::PLACEHOLDER_INSTITUTION,
        self::PLACEHOLDER_STREET,
        self::PLACEHOLDER_HOUSE_NUMBER,
        self::PLACEHOLDER_POSTAL_CODE,
        self::PLACEHOLDER_CITY,
        // Statement / procedure / sender metadata
        self::PLACEHOLDER_STATEMENT_EXTERN_ID,
        self::PLACEHOLDER_STATEMENT_INTERN_ID,
        self::PLACEHOLDER_STATEMENT_SUBMIT_DATE,
        self::PLACEHOLDER_PROCEDURE_NAME,
        self::PLACEHOLDER_TODAY_DATE,
        // Per-segment data
        self::PLACEHOLDER_SEGMENT_EXTERN_ID,
        self::PLACEHOLDER_SEGMENT_TEXT,
        self::PLACEHOLDER_SEGMENT_RECOMMENDATION,
        // Segment-block markers
        self::MARKER_SEGMENTS_OPEN,
        self::MARKER_SEGMENTS_CLOSE,
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $absolutePath local-disk path of the uploaded template,
     *                             obtained from {@see \demosplan\DemosPlanCoreBundle\Logic\FileService::ensureLocalFileFromHash()}
     *
     * @throws InvalidStatementTemplateException
     */
    public function validate(string $absolutePath): void
    {
        $templateProcessor = $this->openTemplate($absolutePath);
        $variables = array_values($templateProcessor->getVariables());

        $this->rejectUnknownPlaceholders($variables);
        $this->rejectIncompleteSegmentMarkerPair($variables);
        $this->rejectSegmentDataWithoutBlock($variables);
    }

    /**
     * @throws InvalidStatementTemplateException
     */
    private function openTemplate(string $absolutePath): TemplateProcessor
    {
        try {
            return new TemplateProcessor($absolutePath);
        } catch (Throwable $exception) {
            throw new InvalidStatementTemplateException($this->trans('docx.export.via_template.error.malformed_docx'), 0, $exception);
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
        throw new InvalidStatementTemplateException($this->trans('docx.export.via_template.error.unknown_placeholder', ['placeholders' => implode(', ', $unknown)]));
    }

    /**
     * @param list<string> $variables
     *
     * @throws InvalidStatementTemplateException
     */
    private function rejectIncompleteSegmentMarkerPair(array $variables): void
    {
        $hasOpen = in_array(self::MARKER_SEGMENTS_OPEN, $variables, true);
        $hasClose = in_array(self::MARKER_SEGMENTS_CLOSE, $variables, true);
        if ($hasOpen === $hasClose) {
            return;
        }
        throw new InvalidStatementTemplateException($this->trans('docx.export.via_template.error.segments_marker_incomplete'));
    }

    /**
     * @param list<string> $variables
     *
     * @throws InvalidStatementTemplateException
     */
    private function rejectSegmentDataWithoutBlock(array $variables): void
    {
        $hasSegmentData = [] !== array_intersect(self::SEGMENT_DATA_PLACEHOLDERS, $variables);
        if (!$hasSegmentData) {
            return;
        }
        $hasMarkers = in_array(self::MARKER_SEGMENTS_OPEN, $variables, true)
            && in_array(self::MARKER_SEGMENTS_CLOSE, $variables, true);
        if ($hasMarkers) {
            return;
        }
        throw new InvalidStatementTemplateException($this->trans('docx.export.via_template.error.segment_data_without_block'));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}
