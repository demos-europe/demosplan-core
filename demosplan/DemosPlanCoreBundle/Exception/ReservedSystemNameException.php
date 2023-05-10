<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class ReservedSystemNameException extends InvalidArgumentException
{
    /** @var string */
    private $name;

    /**
     * @return static
     */
    public static function createFromName(string $name): self
    {
        $reservedSystemNameException = new self("The chosen name \"{$name}\" is an reserved by the system and can not be used as custom name.");
        $reservedSystemNameException->setName($name);

        return $reservedSystemNameException;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
