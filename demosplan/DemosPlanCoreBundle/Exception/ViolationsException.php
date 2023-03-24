<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use DemosEurope\DemosplanAddon\Contracts\Exceptions\ViolationsExceptionInterface;

use function implode;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

class ViolationsException extends InvalidArgumentException implements ViolationsExceptionInterface
{
    /**
     * @var ConstraintViolationListInterface
     */
    private $violations;

    private function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function fromConstraintViolationList(ConstraintViolationListInterface $violationList): self
    {
        $instance = new self();
        $instance->setViolations($violationList);
        $instance->setMessage();

        return $instance;
    }

    public function setViolations(ConstraintViolationListInterface $violations): void
    {
        $this->violations = $violations;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public function setMessage(): void
    {
        $violationMessages = [];
        foreach ($this->violations ?? [] as $violation) {
            /* @var ConstraintViolationInterface $violation */
            $violationMessages[] = sprintf('- %s', $violation->getMessage());
        }

        $this->message = implode("\n", $violationMessages);
    }

    public function getViolationsAsStrings(): array
    {
        return collect($this->getViolations())
            ->map(static function (ConstraintViolationInterface $violation): string {
                return $violation->getMessage();
            })->all();
    }
}
