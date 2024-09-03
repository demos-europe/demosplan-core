<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Exception\ClusterStatementCopyNotImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

abstract class AbstractStatementSpreadsheetImporter implements StatementSpreadsheetImporterInterface
{
    protected readonly NotBlank $notNullConstraint;

    /**
     * Non-original {@link Statement} entities that do not yet exist in the database and were created during the import.
     * The instances contain a reference to their original statement, which will automatically cascade persisted, when
     * the statement copy is persisted.
     *
     * @var list<Statement>
     */
    protected array $generatedStatements = [];

    /**
     * @var array<non-empty-string, int<0, max>>
     */
    protected array $skippedStatements = [];

    /**
     * {@link Tag} entities that do not yet exist in the database and were created during the import.
     *
     * @var list<Tag>
     */
    protected array $generatedTags = [];

    /**
     * @var list<ImportError>
     */
    protected array $errors = [];

    public function __construct(
        protected readonly CurrentProcedureService $currentProcedureService,
        protected readonly CurrentUserService $currentUser,
        protected readonly ElementsService $elementsService,
        protected readonly OrgaService $orgaService,
        protected readonly StatementCopier $statementCopier,
        protected readonly StatementService $statementService,
        protected readonly TranslatorInterface $translator,
        protected readonly ValidatorInterface $validator,
    ) {
        $this->notNullConstraint = new NotBlank(['message' => 'segment.import.error.metadata.statement.id']);
    }

    public function getSkippedStatements(): array
    {
        return $this->skippedStatements;
    }

    /**
     * @return array<int, Worksheet>
     */
    protected function extractWorksheets(SplFileInfo $workbookFile, int $requiredWorksheets): array
    {
        $workbook = IOFactory::load($workbookFile->getPathname());

        $worksheets = $workbook->getAllSheets();
        Assert::greaterThanEq(count($worksheets), $requiredWorksheets, 'Expected at least %2$s worksheets, only found %s instead.');

        return $worksheets;
    }

    /**
     * @throws CopyException
     * @throws ClusterStatementCopyNotImplementedException
     */
    public function createCopy(Statement $generatedOriginalStatement): Statement
    {
        return $this->statementCopier->copyStatementObjectWithinProcedureWithRelatedFiles(
            $generatedOriginalStatement,
            false,
            true
        );
    }

    public function getErrorsAsArray(): array
    {
        $errorArray = [];
        foreach ($this->errors as $key => $error) {
            $errorArray[] = $error->toArray($key);
        }

        return $errorArray;
    }

    /**
     * @return array<int, ImportError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return 0 !== count($this->errors);
    }

    protected function getStatementTextConstraint(): Constraint
    {
        return new NotBlank(
            null,
            $this->translator->trans('error.text'),
            false,
            'trim'
        );
    }

    public function replaceLineBreak($value)
    {
        return str_replace(["_x000D_\n", "\n"], '<br>', strval($value));
    }

    /**
     * Add error-entries from constraintValidations in reference to current line number.
     */
    public function addImportViolations(
        ConstraintViolationListInterface $errors,
        int $currentLineNumber,
        string $currentWorksheetTitle
    ): void
    {
        // $currentLineNumber is the index of the statement/segment array derived from the xlsx. +2 is needed to
        // compensate for arrays starting at 0 (while xslx tables start at 1) and also the first line being the headings
        $currentLineNumber += 2;
        foreach ($errors as $error) {
            $this->errors[] = new ImportError($error, $currentLineNumber, $currentWorksheetTitle);
        }
    }

    public function getGeneratedStatements(): array
    {
        return $this->generatedStatements;
    }

    public function getGeneratedTags(): array
    {
        return $this->generatedTags;
    }
}
