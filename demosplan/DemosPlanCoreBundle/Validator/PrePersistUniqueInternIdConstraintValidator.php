<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\PrePersistUniqueInternIdConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PrePersistUniqueInternIdConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly StatementService $statementService)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        $value = $this->validateType($value, $constraint);
        $occupiedInSavedEntities = $this->isIdOccupiedInSavedEntities($value);
        $occupiedByLoadedEntities = $this->isIdOccupiedByLoadedEntities($value->getInternId(false), $value);
        if ($occupiedInSavedEntities || $occupiedByLoadedEntities) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ internId }}', $value->getInternId())
                ->addViolation();
        }
    }

    private function validateType(Statement $value, PrePersistUniqueInternIdConstraint $constraint): Statement
    {
        if (!$value instanceof Statement) {
            throw new InvalidArgumentException('PrePersistUniqueInternIdConstraint validation currently possible on statements only');
        }

        if (!$constraint instanceof PrePersistUniqueInternIdConstraint) {
            throw new InvalidArgumentException('PrePersistUniqueInternIdConstraint was expected');
        }

        return $value;
    }

    /**
     * @return bool true in case of $internId is already used, otherwise false
     */
    private function isIdOccupiedByLoadedEntities(?string $internId, Statement $excludeStatement): bool
    {
        if (null === $internId) {
            return false;
        }

        $identityMap = $this->entityManager->getUnitOfWork()->getIdentityMap();

        if (\array_key_exists(Statement::class, $identityMap)) {
            $occupyingStatements = array_filter(
                $identityMap[Statement::class],
                static fn (Statement $statement) => !($statement instanceof Segment)
                    && !$statement->isOriginal()
                    && $internId === $statement->getInternId()
                    && $statement->getId() !== $excludeStatement->getId()
                    && $statement->getProcedureId() === $excludeStatement->getProcedureId()
            );

            return 0 !== count((array) $occupyingStatements);
        }

        return false;
    }

    /**
     * @return bool true in case of $internId is already used, otherwise false
     */
    private function isIdOccupiedInSavedEntities(Statement $value): bool
    {
        return !$this->statementService->isInternIdUniqueForProcedure(
            $value->getInternId(false),
            $value->getProcedureId()
        );
    }
}
