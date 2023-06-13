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

use demosplan\DemosPlanCoreBundle\Constraint\FormDefinitionConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFieldDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @see CorrectDateOrderConstraint for usage as annotation
 */
// @improve T20550
class FormDefinitionConstraintValidator extends ConstraintValidator
{
    /**
     * @var string
     */
    private $message;

    /**
     * @param mixed $value
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof Statement && !$value instanceof DraftStatement) {
            throw new InvalidArgumentException('FormDefinitionConstraint validation currently possible on draftstatements and statements only');
        }

        if (!$constraint instanceof FormDefinitionConstraint) {
            throw new InvalidArgumentException('FormDefinitionConstraint was expected');
        }

        $this->message = $constraint->message;
        // Get Field Definitions for procedure
        $currentProcedure = $value->getProcedure();
        $formDefinition = $currentProcedure->getStatementFormDefinition();
        if (null !== $formDefinition) {
            collect($formDefinition->getFieldDefinitions())
                ->filter(static function (StatementFieldDefinition $field): bool {
                    // Check all field definitions for required ones
                    return $field->isRequired();
                })->each(function (StatementFieldDefinition $field) use ($value): void {
                    // Choose the correct validation function. Can be easily expanded in the future
                    switch ($field->getName()) {
                        case 'citizenXorOrgaAndOrgaName':
                            $this->validateOrgaName($value);
                            break;
                        default:
                            break;
                    }
                });
        }
    }

    /**
     * If the role is a public agency, the name is a required field.
     *
     * @param Statement|DraftStatement $value
     */
    private function validateOrgaName(object $value): void
    {
        if ($value instanceof Statement) {
            $miscData = $value->getMeta()->getMiscData();
        } elseif ($value instanceof DraftStatement) {
            $miscData = $value->getMiscData();
        } else {
            throw new InvalidArgumentException('Expected Statement or DraftStatement, got '.get_class($value));
        }

        $submitterRole = $miscData[StatementMeta::SUBMITTER_ROLE] ?? '';
        if (!in_array($submitterRole, ['citizen', 'publicagency'], true)) {
            $this->context->buildViolation('statement.submitter.citizenOrInstitution')
                ->atPath('meta.userOrganisation')
                ->addViolation();
        }

        $notUserOrganisation = '' === ($miscData[StatementMeta::USER_ORGANISATION] ?? '');
        if ('publicagency' === $submitterRole && $notUserOrganisation) {
            $this->context->buildViolation($this->message)
                ->atPath('meta.userOrganisation')
                ->addViolation();
        }
    }
}
