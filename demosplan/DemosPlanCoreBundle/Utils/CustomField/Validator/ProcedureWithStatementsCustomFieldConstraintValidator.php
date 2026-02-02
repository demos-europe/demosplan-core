<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator;

use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint\ProcedureWithStatementsCustomFieldConstraint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProcedureWithStatementsCustomFieldConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ProcedureWithStatementsCustomFieldConstraint) {
            throw new InvalidArgumentException('ProcedureWithStatementsCustomFieldConstraint was expected');
        }

        if (!$value instanceof CustomFieldConfiguration) {
            return;
        }

        if ('PROCEDURE' !== $value->getSourceEntityClass()
            && 'STATEMENT' !== $value->getTargetEntityClass()) {
            return;
        }

        $procedure = $this->entityManager
            ->getRepository(Procedure::class)
            ->find($value->getSourceEntityId());

        // Check if procedure has statements
        if ($procedure->getStatements()->count() > 0) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{procedureId}', $procedure->getId())
                ->addViolation();
        }
    }
}

