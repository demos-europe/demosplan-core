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

/**
 * ...
 */
class DrupalFilterCondition
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $operator;

    /**
     * @var ?mixed
     */
    private $value;

    /**
     * @var ?string
     */
    private $memberOf;

    /**
     * @param mixed $value
     */
    public function __construct(string $path, string $operator, $value, string $memberOf)
    {
        $this->path = $path;
        $this->operator = $operator;
        $this->value = $value;
        $this->memberOf = $memberOf;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function setOperator(string $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value): self
    {
        $this->value = $value;

        return $this;
    }

    public function setMemberOf(string $memberOf): self
    {
        $this->memberOf = $memberOf;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return null|mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getMemberOf(): string
    {
        return $this->memberOf;
    }

    /**
     * @return array{path: non-empty-string, operator: non-empty-string, value: mixed, memberOf: non-empty-string}
     */
    public function toArray()
    {
        return [
            'path'     => $this->path,
            'operator' => $this->operator,
            'value'    => $this->value,
            'memberOf' => $this->memberOf,
        ];
    }
}
