<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use demosplan\DemosPlanCoreBundle\Constraint\ProcedureTemplateMaillaneConnectionConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureRepository;

class ProcedureTemplateMaillaneConnectionConstraintValidator extends ConstraintValidator
{
    /**
     * @var ProcedureRepository
     */
    private $procedureRepository;
    public function __construct(ProcedureRepository $procedureRepository)
    {
        $this->procedureRepository = $procedureRepository;
    }
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(Procedure $procedure, ProcedureTemplateMaillaneConnectionConstraint $constraint): void
    {
        if ($procedure->getMaster() && null !== $this->procedureRepository->getMaillaneConnection($procedure->getId())) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
