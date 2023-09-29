<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class ValidatableValueObject.
 *
 * @method bool isValidated()
 */
class ValidatableValueObject extends ValueObject
{
    /** @var bool */
    private $validated = false;

    /**
     * @param ValidatorInterface $validator will be used automatically when accessing get methods of this instance
     */
    public function __construct(#[Assert\NotNull]
    private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function setProperty($name, $value): ValueObject
    {
        $this->verifySettability($name);

        if (true !== $this->validated) {
            $this->doValidation();
        }

        $this->{$name} = $value;

        return $this;
    }

    protected function doValidation()
    {
        $violations = $this->validator->validate($this);

        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $this->validated = true;
    }
}
