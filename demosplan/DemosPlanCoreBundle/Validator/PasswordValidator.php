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

use Rollerworks\Component\PasswordStrength\Validator\Constraints\PasswordRequirements;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PasswordValidator
{
    /**
     * @var int
     */
    protected $passwordMinLength;

    /**
     * @var bool
     */
    protected $passwordRequireCaseDiff;

    /**
     * @var bool
     */
    protected $passwordRequireNumbers;

    public function __construct(private readonly ValidatorInterface $validator, ParameterBagInterface $parameterBag)
    {
        $this->passwordMinLength = $parameterBag->get('password_min_length');
        $this->passwordRequireCaseDiff = $parameterBag->get('password_require_case_diff');
        $this->passwordRequireNumbers = $parameterBag->get('password_require_numbers');
    }

    public function validate(string $password): ConstraintViolationListInterface
    {
        $passwordRequirements = new PasswordRequirements(null,
            null,
            null,
            $this->passwordMinLength,
            true,
            $this->passwordRequireCaseDiff,
            $this->passwordRequireNumbers
        );

        return $this->validator->validate($password, $passwordRequirements);
    }
}
