<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * T17387
 * Class StatementStatistic.
 *
 * Holds statistically data of statements in relation to procedures.
 * Most of the Data will be calculated at creation of new instance of this class.
 */
class StatementStatistic extends ValueObject
{
    /** @var int */
    protected $totalAmountOfProcedures = 0;

    /** @var int */
    protected $totalAmountOfStatements = 0;

    /** @var int */
    protected $totalAmountOfCitizenStatements = 0;

    /** @var int */
    protected $totalAmountOfGuestStatements = 0;

    /** @var int */
    protected $totalAmountOfInstitutionStatements = 0;

    /** @var int[] */
    protected $amountOfCitizenStatementsOfProcedure = [];

    /** @var int[] */
    protected $amountOfGuestStatementsOfProcedure = [];

    /** @var int[] */
    protected $amountOfInstitutionStatementsOfProcedure = [];

    /** @var float */
    protected $averageAmountOfStatementsPerProcedure = 0;

    /** @var float */
    protected $averageAmountOfCitizenStatementsPerProcedure = 0;

    /** @var float */
    protected $averageAmountOfGuestStatementsPerProcedure = 0;

    /** @var float */
    protected $averageAmountOfInstitutionStatementsPerProcedure = 0;

    /**
     * Constructor.
     *
     * @param array[] $allOriginalStatements
     */
    public function __construct(array $allOriginalStatements, int $amountOfProcedures)
    {
        $this->totalAmountOfStatements = count($allOriginalStatements);
        $this->totalAmountOfProcedures = $amountOfProcedures;

        foreach ($allOriginalStatements as $originalStatement) {
            // guestStatements:
            if (null === $this->getSubmitterId($originalStatement)) {
                ++$this->totalAmountOfGuestStatements;

                if (!array_key_exists($originalStatement['procedureId'], $this->amountOfGuestStatementsOfProcedure)) {
                    $this->amountOfGuestStatementsOfProcedure[$originalStatement['procedureId']] = 0;
                }
                ++$this->amountOfGuestStatementsOfProcedure[$originalStatement['procedureId']];

            // InstitutionStatements:
            } elseif ($this->isCreatedByInvitableInstitution($originalStatement)) {
                ++$this->totalAmountOfInstitutionStatements;

                if (!array_key_exists($originalStatement['procedureId'], $this->amountOfInstitutionStatementsOfProcedure)) {
                    $this->amountOfInstitutionStatementsOfProcedure[$originalStatement['procedureId']] = 0;
                }
                ++$this->amountOfInstitutionStatementsOfProcedure[$originalStatement['procedureId']];

            // citizenStatements:
            } elseif ($this->isCreatedByCitizen($originalStatement)) {
                ++$this->totalAmountOfCitizenStatements;

                if (!array_key_exists($originalStatement['procedureId'], $this->amountOfCitizenStatementsOfProcedure)) {
                    $this->amountOfCitizenStatementsOfProcedure[$originalStatement['procedureId']] = 0;
                }
                ++$this->amountOfCitizenStatementsOfProcedure[$originalStatement['procedureId']];
            }
        }

        if (0 !== $this->totalAmountOfProcedures) {
            $this->averageAmountOfStatementsPerProcedure = $this->totalAmountOfStatements / $this->totalAmountOfProcedures;
            $this->averageAmountOfCitizenStatementsPerProcedure = $this->totalAmountOfCitizenStatements / $this->totalAmountOfProcedures;
            $this->averageAmountOfGuestStatementsPerProcedure = $this->totalAmountOfGuestStatements / $this->totalAmountOfProcedures;
            $this->averageAmountOfInstitutionStatementsPerProcedure = $this->totalAmountOfInstitutionStatements / $this->totalAmountOfProcedures;
        }

        $this->lock();
    }

    public function getTotalAmountOfProcedures(): int
    {
        return $this->totalAmountOfProcedures;
    }

    public function getTotalAmountOfStatements(): int
    {
        return $this->totalAmountOfStatements;
    }

    public function getTotalAmountOfCitizenStatements(): int
    {
        return $this->totalAmountOfCitizenStatements;
    }

    public function getTotalAmountOfGuestStatements(): int
    {
        return $this->totalAmountOfGuestStatements;
    }

    public function getTotalAmountOfInstitutionStatements(): int
    {
        return $this->totalAmountOfInstitutionStatements;
    }

    public function getAmountOfCitizenStatementsOfProcedure(string $procedureId): int
    {
        return $this->amountOfCitizenStatementsOfProcedure[$procedureId] ?? 0;
    }

    public function getAmountOfGuestStatementsOfProcedure(string $procedureId): int
    {
        return $this->amountOfGuestStatementsOfProcedure[$procedureId] ?? 0;
    }

    public function getAmountOfInstitutionStatementsOfProcedure(string $procedureId): int
    {
        return $this->amountOfInstitutionStatementsOfProcedure[$procedureId] ?? 0;
    }

    public function getAverageAmountOfInstitutionStatementsPerProcedure(int $precision = 2): float
    {
        return round($this->averageAmountOfInstitutionStatementsPerProcedure, $precision);
    }

    public function getAverageAmountOfGuestStatementsPerProcedure(int $precision = 2): float
    {
        return round($this->averageAmountOfGuestStatementsPerProcedure, $precision);
    }

    public function getAverageAmountOfCitizenStatementsPerProcedure(int $precision = 2): float
    {
        return round($this->averageAmountOfCitizenStatementsPerProcedure, $precision);
    }

    public function getAverageAmountOfStatementsPerProcedure(int $precision = 2): float
    {
        return round($this->averageAmountOfStatementsPerProcedure, $precision);
    }

    public function getTotalAmountOfPublicStatements(): int
    {
        return $this->getTotalAmountOfGuestStatements() + $this->getTotalAmountOfCitizenStatements();
    }

    public function getStatisticDataForProcedure(string $procedureId): array
    {
        return [
            'amountOfCitizenStatementsOfProcedure' => $this->getAmountOfCitizenStatementsOfProcedure($procedureId),
            'amountOfGuestStatementsOfProcedure'   => $this->getAmountOfGuestStatementsOfProcedure($procedureId),
            'amountOfToebStatementsOfProcedure'    => $this->getAmountOfInstitutionStatementsOfProcedure($procedureId),
        ];
    }

    /**
     * Duplicate of.
     *
     * @see Statement::getSubmitterId()
     */
    protected function getSubmitterId(array $statementData): ?string
    {
        // internal:
        if (Statement::INTERNAL === $statementData['publicStatement']) {
            // on internal statements, submitUId on meta should be always filled.
            return $statementData['submitUId'];
        }

        // external:
        // on external statements, the author is always the submitter
        return User::ANONYMOUS_USER_ID === $statementData['userId'] ? null : $statementData['userId'];
    }

    /**
     * Duplicate of.
     *
     * @see Statement::isCreatedByInvitableInstitution()
     */
    protected function isCreatedByInvitableInstitution(array $statementData): bool
    {
        return !$statementData['isManual'] && Statement::INTERNAL === $statementData['publicStatement'];
    }

    /**
     * Duplicate of.
     *
     * @see Statement::isCreatedByCitizen()
     */
    protected function isCreatedByCitizen(array $statementData): bool
    {
        return !$statementData['isManual'] && Statement::INTERNAL !== $statementData['publicStatement'];
    }
}
