<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\User;

class DataProtectionOrganisation
{
    public function __construct(private readonly string $id, private readonly string $name, private readonly string $dataProtection)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDataProtection(): string
    {
        return $this->dataProtection;
    }
}
