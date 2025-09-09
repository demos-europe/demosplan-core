<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelImporter;
use demosplan\DemosPlanCoreBundle\Validator\MatchingStatementIdValidator;
use Symfony\Component\Validator\Constraint;

class MatchingFieldValueInSegments extends Constraint
{
    /**
     * @var string
     */
    public $message = 'segment.import.error.matching.statement.id';

    /**
     * @var string
     */
    public $statementWorksheetTitle;

    /**
     * @var string
     */
    public $segmentWorksheetTitle;

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    public $segments;

    /**
     * @var string
     */
    public $statementIdIdentifier;

    /**
     * @param array<string, array<int, array<string, mixed>>> $segments
     */
    public function __construct(
        array $segments,
        string $statementWorksheetTitle,
        string $segmentWorksheetTitle,
        $options = null,
        array $groups = null,
        $payload = null
    ) {
        parent::__construct($options, $groups, $payload);
        $this->statementWorksheetTitle = $statementWorksheetTitle;
        $this->segmentWorksheetTitle = $segmentWorksheetTitle;
        $this->statementIdIdentifier = ExcelImporter::STATEMENT_ID;
        $this->segments = $segments;
    }

    public function validatedBy(): string
    {
        return MatchingStatementIdValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
