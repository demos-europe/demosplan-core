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

use demosplan\DemosPlanCoreBundle\Exception\IncompleteSegmentMarkersException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Exception\MalformedDocxException;
use demosplan\DemosPlanCoreBundle\Exception\MissingSegmentBlockException;
use demosplan\DemosPlanCoreBundle\Exception\UnknownPlaceholdersException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;

/**
 * Validates a planner-uploaded DOCX template against the placeholder whitelist
 * and the segment-block marker rules of {@see StatementViaTemplateExporter}.
 *
 * The template has exactly one repeatable region — paragraphs between
 * `${AbschnitteAlsAbsätze}` and `${/AbschnitteAlsAbsätze}` — which PhpWord
 * clones via `cloneBlock` per segment.
 *
 * On any failure the validator throws a typed subclass of
 * {@see InvalidStatementTemplateException}; the catching boundary translates
 * the error and surfaces it via the message bag.
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

    /**
     * @param string $absolutePath local-disk path of the uploaded template,
     *                             obtained from {@see \demosplan\DemosPlanCoreBundle\Logic\FileService::ensureLocalFileFromHash()}
     *
     * @throws InvalidStatementTemplateException
     */
    public function validate(string $absolutePath): void
    {
        $templateProcessor = $this->openTemplate($absolutePath);
        $variableCount = $templateProcessor->getVariableCount();
        $variables = array_keys($variableCount);

        $this->rejectUnknownPlaceholders($variables);
        $this->rejectIncompleteSegmentMarkerPair($variableCount);
        $this->rejectSegmentDataWithoutBlock($variables);
    }

    /**
     * @throws MalformedDocxException
     */
    private function openTemplate(string $absolutePath): TemplateProcessor
    {
        try {
            return new TemplateProcessor($absolutePath);
        } catch (Throwable $exception) {
            throw new MalformedDocxException('', 0, $exception);
        }
    }

    /**
     * @param list<string> $variables
     *
     * @throws UnknownPlaceholdersException
     */
    private function rejectUnknownPlaceholders(array $variables): void
    {
        $unknown = array_values(array_diff($variables, self::WHITELIST));
        if ([] === $unknown) {
            return;
        }
        throw new UnknownPlaceholdersException($unknown);
    }

    /**
     * @param array<string, int> $variableCount result of {@see TemplateProcessor::getVariableCount()}
     *
     * @throws IncompleteSegmentMarkersException
     */
    private function rejectIncompleteSegmentMarkerPair(array $variableCount): void
    {
        $openCount = $variableCount[self::MARKER_SEGMENTS_OPEN] ?? 0;
        $closeCount = $variableCount[self::MARKER_SEGMENTS_CLOSE] ?? 0;
        // either both appear exactly once or neither appears
        if ($openCount === $closeCount && $openCount <= 1) {
            return;
        }
        throw new IncompleteSegmentMarkersException('Incomplete segment markers in template detected.');
    }

    /**
     * @param list<string> $variables
     *
     * @throws MissingSegmentBlockException
     */
    private function rejectSegmentDataWithoutBlock(array $variables): void
    {
        $hasSegmentData = [] !== array_intersect(self::SEGMENT_DATA_PLACEHOLDERS, $variables);
        if (!$hasSegmentData) {
            return;
        }
        if ($this->hasCompleteSegmentMarkerPair($variables)) {
            return;
        }
        throw new MissingSegmentBlockException();
    }

    /**
     * @param list<string> $variables
     */
    private function hasCompleteSegmentMarkerPair(array $variables): bool
    {
        return in_array(self::MARKER_SEGMENTS_OPEN, $variables, true)
            && in_array(self::MARKER_SEGMENTS_CLOSE, $variables, true);
    }
}
