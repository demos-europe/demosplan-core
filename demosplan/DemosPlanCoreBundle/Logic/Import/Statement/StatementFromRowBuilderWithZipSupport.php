<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;


use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Geocoder\Assert;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StatementFromRowBuilderWithZipSupport extends StatementFromRowBuilder
{
    public function __construct(ValidatorInterface $validator, Procedure $procedure, User $importingUser, Orga $anonymousOrga, Elements $statementElement, Constraint $textConstraint, mixed $textPostValidationProcessing, protected readonly array $fileMap)
    {
        parent::__construct($validator, $procedure, $importingUser, $anonymousOrga, $statementElement, $textConstraint, $textPostValidationProcessing);
    }

    public function setFileReferences(Cell $cell): ?ConstraintViolationListInterface
    {
//        $violation = new ConstraintViolation();
        // fixme add violation if reasonable
        $references = explode(', ', $cell->getValue());
//       $keyConstraint = new Collection(
//           array_fill_keys($references, []),
//           null,
//           null,
//           true,
//           false
//       );
//        $violations = $this->validator->validate(
//            $this->fileMap,
//            [$keyConstraint]
//        );
//        if (0 !== $violations->count()) {
//            return $violations;
//        }
        foreach ($references as $fileMapKey) {
            // fixme use the correct method - attachement or files
//            $this->statement->addAttachment($this->fileMap[$fileMapKey]);
        }

        return null;
    }
}
