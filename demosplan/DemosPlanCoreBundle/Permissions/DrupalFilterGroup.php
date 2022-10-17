<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;

class DrupalFilterGroup
{
    /**
     * @var string
     */
    private $conjunction;

    /**
     * @var null|string
     */
    private $memberOf;

    public function __construct($conjunction, $memberOf)
    {
        $this->conjunction = $conjunction;
        $this->memberOf = $memberOf;
    }

    public function setMemberOf(string $memberOf): self
    {
        $this->memberOf = $memberOf;

        return $this;
    }

    public function setConjunction(string $conjunction): self
    {
        $this->conjunction = $conjunction;

        return $this;
    }

    public function getConjunction(): string
    {
        return $this->conjunction;
    }

    public function getMemberOf(): ?string
    {
        return $this->memberOf;
    }

    /**
     * @return array{conjunction: non-empty-string, memberOf?: non-empty-string}
     */
    public function toArray(): array
    {
        $filterGroupArray = [
            DrupalFilterParser::CONJUNCTION => $this->conjunction,
        ];

        if (null !== $this->memberOf) {
            $filterGroupArray[DrupalFilterParser::MEMBER_OF] = $this->memberOf;
        }

        return $filterGroupArray;
    }
}
