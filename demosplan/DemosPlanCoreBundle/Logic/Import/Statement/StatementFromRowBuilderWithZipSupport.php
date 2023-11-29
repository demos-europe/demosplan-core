<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;


use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
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
        // early return in case no file-reference is found
        $cellValue = $cell->getValue();
        if (null === $cellValue || '' === $cellValue) {
            return null;
        }

        $references = explode(', ', $cell->getValue());

        $collectionContent = array_fill_keys(
            $references,
            new Required(
                new Type(File::class)
            )
        );
        $keyConstraint = new Collection(
            $collectionContent,
            null,
            null,
            true,
            false
       );
        $violations = $this->validator->validate(
            $this->fileMap,
            [$keyConstraint]
        );
        if (0 !== $violations->count()) {
            return $violations;
        }

        foreach ($references as $fileMapKey) {
            // fixme use the correct method - attachement or files
//            $this->statement->addAttachment($this->fileMap[$fileMapKey]);
        }

        return null;
    }
}
