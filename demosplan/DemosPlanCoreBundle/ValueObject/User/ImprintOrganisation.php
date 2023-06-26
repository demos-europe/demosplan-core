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

final class ImprintOrganisation
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $imprint;

    public function __construct(string $id, string $name, string $imprint)
    {
        $this->id = $id;
        $this->name = $name;
        $this->imprint = $imprint;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getImprint(): string
    {
        return $this->imprint;
    }
}
