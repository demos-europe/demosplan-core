<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Exception\RowAwareViolationsException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedWorksheetNameException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use Symfony\Component\Finder\SplFileInfo;

interface StatementSpreadsheetImporterInterface
{
    /**
     * Generates statements from the incoming file, including validation.
     * This method does not persist or flush the generated Statements.
     *
     * @param SplFileInfo $workbook identifies the file which contains the worksheets with data of statements to create
     *
     * @throws CopyException
     * @throws InvalidDataException
     * @throws MissingPostParameterException
     * @throws StatementElementNotFoundException
     * @throws UserNotFoundException
     * @throws RowAwareViolationsException
     * @throws UnexpectedWorksheetNameException
     * @throws MissingDataException
     */
    public function process(SplFileInfo $workbook): void;

    /**
     * @return list<EntityInterface>
     */
    public function getGeneratedTags(): array;

    /**
     * @return list<Statement>
     */
    public function getGeneratedStatements(): array;

    /**
     * @return list<array{id: int, currentWorksheet: string, lineNumber: int, message: string}>
     */
    public function getErrorsAsArray(): array;

    public function hasErrors(): bool;
}
