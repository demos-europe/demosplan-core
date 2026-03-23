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

use demosplan\DemosPlanCoreBundle\Constraint\ProcedureInCoupleAlreadyUsedConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureCoupleTokenRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Error;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @see ProcedureInCoupleAlreadyUsedConstraint
 */
class ProcedureInCoupleAlreadyUsedConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly ProcedureCoupleTokenRepository $tokenRepository)
    {
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ProcedureInCoupleAlreadyUsedConstraint) {
            throw new InvalidArgumentException('ProcedureInCoupleAlreadyUsedConstraint was expected');
        }

        if (null !== $value) {
            if (!$value instanceof ProcedureCoupleToken) {
                throw new InvalidArgumentException('ProcedureCoupleToken was expected');
            }

            // Skip validation if entity is not fully hydrated yet
            // During Doctrine entity loading, required relationships might not be available
            try {
                $sourceProcedure = $value->getSourceProcedure();
                $this->validateTyped($sourceProcedure, $value->getId(), $constraint->sourceProcedureMessage);
                $this->validateTyped($value->getTargetProcedure(), $value->getId(), $constraint->targetProcedureMessage);
            } catch (Error) {
                // Skip validation if entity relationships are not fully loaded
                // This can happen during Doctrine hydration when sourceProcedure is null
                return;
            }
        }
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    private function validateTyped(
        ?Procedure $procedure,
        ?string $tokenId,
        string $constraintMessage,
    ): void {
        if (!$procedure instanceof Procedure) {
            return;
        }

        $tokenCount = $this->tokenRepository->getTokenCountWithProcedure($procedure, $tokenId);
        if (0 !== $tokenCount) {
            $this->context->buildViolation($constraintMessage)
                ->setParameter('{intendedProcedureId}', $procedure->getId())
                ->addViolation();
        }
    }
}
