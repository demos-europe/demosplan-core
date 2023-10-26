<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Exception\ClusterStatementCopyNotImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;
use Symfony\Component\Validator\Constraints\NotBlank;

abstract class AbstractStatementSpreadsheetImporter implements StatementSpreadsheetImporterInterface
{
    protected readonly NotBlank $notNullConstraint;

    /**
     * @var list<Statement>
     */
    protected array $generatedStatements = [];

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
        protected readonly CurrentUserInterface $currentUser,
        protected readonly ElementsService $elementsService,
        protected readonly OrgaService $orgaService,
        protected readonly StatementCopier $statementCopier,
        protected readonly StatementService $statementService,
        protected readonly TranslatorInterface $translator,
        protected readonly ValidatorInterface $validator,
    ) {
        $this->notNullConstraint = new NotBlank(['message' => 'segment.import.error.metadata.statement.id']);
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
        return $this->statementCopier->copyStatementObjectWithinProcedure(
            $generatedOriginalStatement,
            false,
            true,
            false
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

    protected function replaceLineBreak($value)
    {
        return str_replace(["_x000D_\n", "\n"], '<br>', strval($value));
    }

    /**
     * Add error-entries from constraintValidations in reference to current line number.
     */
    public function addImportViolations(ConstraintViolationListInterface $errors, int $currentLineNumber, string $currentWorksheetTitle): void
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

    protected function validateSubmitType(string $inputSubmitType, int $line, string $worksheetTitle): void
    {
        $violations = $this->validator->validate($inputSubmitType, $this->getSubmitTypeConstraint($inputSubmitType));
        if (0 !== $violations->count()) {
            $this->addImportViolations($violations, $line, $worksheetTitle);
        }
    }

    abstract protected function mapSubmitType(string $incomingSubmitType): string;

    abstract protected function getSubmitTypeConstraint(string $inputSubmitType): Constraint;
}
