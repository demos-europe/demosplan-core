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

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Constraint\DateStringConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use Parsedown;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function is_string;

/**
 * Contains the details how to handle each cell in a row during the import of a statement via a spreadsheet.
 *
 * Each cell is handled via a dedicated (setter) method. When the statement is build via
 * {@link self::buildStatementAndReset()}, additional adjustment may be done that do
 * not correspond to a single cell.
 *
 * Neither the setter methods nor the {@link self::buildStatementAndReset()} method must throw an exception in case of invalid
 * cell values or an invalid statement state. Instead, non-empty {@link ConstraintViolationListInterface} instances are
 * to be returned. In valid cases an empty instance or `null` may be returned. {@link \Throwable} instances indicating
 * other problems are still to be thrown though. This approach was chosen to avoid exception creation (which is costly
 * due to the collected stack trace). Otherwise, the impact may be quite noticeable in case of mostly invalid and large
 * input spreadsheets. This is because all rows will be processed regardless of errors.
 *
 * A builder approach is preferred over the alternatives:
 * 1. processing each cell individually (with no context of the other cells) and directly setting its value into a
 *    statement: to set some statement values the content of multiple cells is needed
 * 2. processing all cells in one big method: possible, but more messy than spreading the logic over multiple,
 *    specialized methods
 */
class StatementFromRowBuilder extends AbstractStatementFromRowBuilder
{
    protected DateTime $now;

    protected Cell $planningDocumentCategoryTitle;

    protected Cell $paragraphTitle;

    protected Cell $planningDocumentTitle;

    /**
     * @param callable(string): string $textPostValidationProcessing
     */
    public function __construct(
        protected readonly ValidatorInterface $validator,
        protected readonly Procedure $procedure,
        protected readonly User $importingUser,
        protected readonly Orga $anonymousOrga,
        protected readonly ElementsService $planningCategoryService,
        protected readonly Constraint $textConstraint,
        protected readonly mixed $textPostValidationProcessing
    ) {
        parent::__construct();
        $this->now = Carbon::now();
    }

    public function setExternId(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->setExternId($cell->getValue());

        return null;
    }

    public function setText(Cell $cell): ?ConstraintViolationListInterface
    {
        $statementText = $cell->getValue();

        $parsedown = new Parsedown();
        // parse inline markdown
        $statementText = $parsedown->line($statementText);

        $violations = $this->validator->validate($statementText, $this->textConstraint);
        if (0 !== $violations->count()) {
            return $violations;
        }
        $this->statement->setText(($this->textPostValidationProcessing)($statementText));

        return null;
    }

    /**
     * Search and set PlanningDocumentCategory if found at lease one(!), otherwise just set the cellvalue as title
     * and try to guess the related PlanningDocumentCategory later on.
     *
     * Searching for an existing PlanningDocumentCategory by given cell value as title.
     * In case of a PlanningDocumentCategory was found, the relation will be set, otherwise,
     * only the name will be set, and the PlanningDocumentCategory will be guessed later by guessCategoryType().
     *
     * Attention: Searching for the PlanningDocumentCategory by title, can lead to a wrong result, because
     * titles of the PlanningDocumentCategory are not unique!
     * Also it is possible to name a PlanningDocumentCategory with a complete misleading name, which leads to find
     * a wrong category at all.
     */
    public function setPlanningDocumentCategoryTitle(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->planningDocumentCategoryTitle = $cell;

        return null;
    }

    public function setPlanningDocumentTitle(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->planningDocumentTitle = $cell;

        return null;
    }

    public function setParagraphTitle(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->paragraphTitle = $cell;

        return null;
    }

    public function setOrgaName(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setOrgaName($cell->getValue() ?? '');

        return null;
    }

    public function setDepartmentName(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setOrgaDepartmentName($cell->getValue() ?? '');

        return null;
    }

    public function setAuthorName(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setAuthorName($cell->getValue() ?? '');

        return null;
    }

    public function setSubmitterName(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setSubmitName($cell->getValue() ?? '');

        return null;
    }

    public function setSubmiterEmailAddress(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setOrgaEmail($cell->getValue() ?? '');

        return null;
    }

    public function setSubmitterStreetName(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setOrgaStreet($cell->getValue() ?? '');

        return null;
    }

    public function setSubmitterHouseNumber(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setHouseNumber((string) ($cell->getValue() ?? ''));

        return null;
    }

    public function setSubmitterPostalCode(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setOrgaPostalCode($cell->getValue() ?? '');

        return null;
    }

    public function setSubmitterCity(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->getMeta()->setOrgaCity($cell->getValue() ?? '');

        return null;
    }

    public function setSubmitDate(Cell $cell): ?ConstraintViolationListInterface
    {
        $date = $this->getDate($cell);
        if ($date instanceof ConstraintViolationListInterface) {
            return $date;
        }

        $this->statement->setSubmit($date);

        return null;
    }

    public function setAuthoredDate(Cell $cell): ?ConstraintViolationListInterface
    {
        $date = $this->getDate($cell);
        if ($date instanceof ConstraintViolationListInterface) {
            return $date;
        }

        $this->statement->getMeta()->setAuthoredDate($date);

        return null;
    }

    public function setInternId(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->setInternId($cell->getValue());

        return null;
    }

    public function setMemo(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->setMemo($cell->getValue() ?? '');

        return null;
    }

    public function setFeedback(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->setFeedback($cell->getValue() ?? '');

        return null;
    }

    public function getInternId(): ?string
    {
        return $this->statement->getInternId();
    }

    public function getExternId(): string
    {
        return $this->statement->getExternId();
    }

    public function setNumberOfAnonymVotes(Cell $cell): ?ConstraintViolationListInterface
    {
        $this->statement->setNumberOfAnonymVotes($cell->getValue() ?? 0);

        return null;
    }

    /**
     * Returns the statement that was created and filled since the last call of this method or a list of violations
     * due to invalid values/state of the statement.
     */
    public function buildStatementAndReset(): Statement|ConstraintViolationListInterface
    {
        $newOriginalStatement = $this->statement;
        $newStatementMeta = $newOriginalStatement->getMeta();

        // set orga/department related values
        if (User::ANONYMOUS_USER_ORGA_NAME === $newStatementMeta->getOrgaName()) {
            $newOriginalStatement->setPublicStatement(StatementInterface::EXTERNAL);
            $newOriginalStatement->setOrganisation($this->anonymousOrga);
            $newStatementMeta->setSubmitUId(User::ANONYMOUS_USER_ID);
            $newStatementMeta->setMiscDataValue(StatementMeta::SUBMITTER_ROLE, 'citizen');
        } else {
            $newStatementMeta->setMiscDataValue(StatementMeta::SUBMITTER_ROLE, 'publicagency');
        }

        $submitDate = $newOriginalStatement->getSubmitObject();
        if (null === $submitDate) {
            $newOriginalStatement->setSubmit($this->now);
        }
        $authoredDate = $newStatementMeta->getAuthoredDateObject();
        if (null === $authoredDate) {
            $newStatementMeta->setAuthoredDate($this->now);
        }

        // set gdpr consent
        $gdprConsent = new GdprConsent();
        $gdprConsent->setStatement($newOriginalStatement);
        $newOriginalStatement->setGdprConsent($gdprConsent);

        // set other static values
        $newOriginalStatement->setManual();
        $newOriginalStatement->setProcedure($this->procedure);
        $newStatementMeta->setSubmitOrgaId($this->importingUser->getOrganisationId());
        $newOriginalStatement->setPhase($this->procedure->getPhase());
        $newOriginalStatement->setPublicVerified(Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED);

        $violations = $this->findOrCreatePlanningCategory($newOriginalStatement);
        if (0 !== $violations->count()) {
            return $violations;
        }

        // validate
        $violations = $this->validator->validate(
            $newOriginalStatement,
            null,
            [StatementInterface::IMPORT_VALIDATION]
        );
        if (0 !== $violations->count()) {
            return $violations;
        }

        // reset builder state
        $this->resetStatement();

        return $newOriginalStatement;
    }

    public function resetStatement(): void
    {
        $this->statement = new Statement();
    }

    /**
     * Handles three cases
     * * empty cell: use the current date
     * * normal string: determine format and use it
     * (see {@link https://php.net/manual/en/datetime.formats.php Date and Time Formats})
     * * number, i.e. date formatted cell: convert from exel number to {@link DateTime}.
     */
    protected function getDate(Cell $cell): DateTime|ConstraintViolationListInterface
    {
        $value = $cell->getValue();
        if (is_string($value) && '' !== $value) {
            $violations = $this->validator->validate($value, [
                new DateStringConstraint(),
                new NotBlank(allowNull: false),
            ]);
            if (0 === $violations->count()) {
                return Carbon::parse($value)->toDate();
            }
        } elseif (null === $value || '' === $value) {
            return $this->now;
        } else {
            // in Excel the DateTime format counts the days starting at the year 1900,
            // so we validate the expected integer for at least technical validity
            $violations = $this->validator->validate($value, new Range([
                'min'               => 1,
                'max'               => 2_958_465,
                'notInRangeMessage' => 'The value {{ value }} is not a valid Excel date.',
            ]));
            if (0 === $violations->count()) {
                return Date::excelToDateTimeObject($value);
            }
        }

        return $violations;
    }

    /**
     * Also validates the combination of planningDocumentCategoryTitle, planningDocumentTitle and paragraphTitle.
     */
    private function findOrCreatePlanningCategory(Statement $originalStatement): ConstraintViolationListInterface
    {
        // 1. guess category type
        $foundCategoryTitleOrViolationList = $this->planningCategoryService->guessSystemCategoryType(
            $this->planningDocumentCategoryTitle->getValue() ?? '',
            $this->planningDocumentTitle->getValue() ?? '',
            $this->paragraphTitle->getValue() ?? ''
        );

        // illicit combination of paragraphTitle and planningDocumentTitle
        if ($foundCategoryTitleOrViolationList instanceof ConstraintViolationListInterface
            && 0 !== $foundCategoryTitleOrViolationList->count()) {
            return $foundCategoryTitleOrViolationList;
        }

        // matching system category type was found, search for it in the DB
        if (is_string($foundCategoryTitleOrViolationList)) {
            // find existing element by title and categorytype
            $planningCategory = $this->planningCategoryService->getPlanningDocumentCategoryByTitleAndCategoryType(
                $this->procedure->getId(),
                $this->planningDocumentCategoryTitle->getValue() ?? '',
                $foundCategoryTitleOrViolationList
            );

            // set if found one:
            if ($planningCategory instanceof Elements) {
                $this->statement->setElement($planningCategory);

                return new ConstraintViolationList();
            }
        }

        // no matching system category type was found, or planningDocumentCategory could not be found, create new one.
        $planningCategory = new Elements();
        $planningCategory->setCategory($this->planningDocumentCategoryTitle->getValue() ?? '');

        $planningCategory->setProcedure($this->procedure);
        $nextOrderIndex = $this->planningCategoryService->getNextFreeOrderIndex($this->procedure);
        $planningCategory->setOrder($nextOrderIndex);
        $planningCategory->setEnabled(false);

        $this->planningCategoryService->addEntity($planningCategory);
        $this->procedure->getElements()->add($planningCategory);

        $originalStatement->setElement($planningCategory);

        return new ConstraintViolationList();
    }
}
