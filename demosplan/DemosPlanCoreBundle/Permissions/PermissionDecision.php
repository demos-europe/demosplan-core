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

use InvalidArgumentException;
use function array_key_exists;

class PermissionDecision
{
    public const PARAMETER = 'parameter';

    /**
     * @var Permission
     */
    private $permission;

    /**
     * @var list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string, parameter: bool}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    private $customerCondition = [];

    /**
     * @var int
     */
    private $customerConditionCounter = 0;

    /**
     * @var list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string, parameter: bool}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    private $procedureCondition = [];

    /**
     * @var int
     */
    private $procedureConditionCounter = 0;

    /**
     * @var list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string, parameter: bool}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    private $userCondition = [];

    /**
     * @var int
     */
    private $userConditionCounter = 0;

    public function __construct(Permission $permission)
    {
        $this->permission = $permission;
    }

    /**
     * @param non-empty-string      $path
     * @param non-empty-string      $operator
     * @param mixed                 $value
     * @param non-empty-string|null $memberOf
     *
     * @return $this
     */
    public function addUserCondition(
        string $path,
        string $operator,
        $value,
        string $memberOf = null,
        bool $parameter = false
    ): self {
        $filterName = "filter_condition_$this->userConditionCounter";
        $this->userCondition[$filterName]['condition'] = (new DrupalFilterCondition(
            $path,
            $operator,
            $value,
            $memberOf,
            $parameter
        ))->toArray();
        $this->userConditionCounter += 1;

        return $this;
    }

    /**
     * @param non-empty-string      $path
     * @param non-empty-string      $operator
     * @param mixed                 $value
     * @param non-empty-string|null $memberOf
     *
     * @return $this
     */
    public function addProcedureCondition(
        string $path,
        string $operator,
        $value,
        string $memberOf = null,
        bool $parameter = false
    ): self {
        $filterName = "filter_condition_$this->procedureConditionCounter";
        $this->procedureCondition[$filterName]['condition'] = (new DrupalFilterCondition(
            $path,
            $operator,
            $value,
            $memberOf,
            $parameter
        ))->toArray();
        $this->procedureConditionCounter += 1;

        return $this;
    }

    /**
     * @param non-empty-string      $path
     * @param non-empty-string      $operator
     * @param mixed                 $value
     * @param non-empty-string|null $memberOf
     *
     * @return $this
     */
    public function addCustomerCondition(
        string $path,
        string $operator,
        $value,
        string $memberOf = null,
        bool $parameter = false
    ): self {
        $filterName = "filter_condition_$this->customerConditionCounter";
        $this->customerCondition[$filterName]['condition'] = (new DrupalFilterCondition(
            $path,
            $operator,
            $value,
            $memberOf,
            $parameter
        ))->toArray();
        $this->customerConditionCounter += 1;

        return $this;
    }

    /**
     * @param non-empty-string      $name
     * @param non-empty-string      $conjunction
     * @param non-empty-string|null $memberOf
     *
     * @return $this
     */
    public function addUserGroup(string $name, string $conjunction = 'AND', string $memberOf = null): self
    {
        if (array_key_exists($name, $this->customerCondition)) {
            throw new InvalidArgumentException("A group or condition with the name `$name` was already set in the procedure filter.");
        }

        $this->userCondition[$name]['group'] = (new DrupalFilterGroup($conjunction, $memberOf))->toArray();

        return $this;
    }

    /**
     * @param non-empty-string      $name
     * @param non-empty-string      $conjunction
     * @param non-empty-string|null $memberOf
     *
     * @return $this
     */
    public function addProcedureGroup(string $name, string $conjunction = 'AND', string $memberOf = null): self
    {
        if (array_key_exists($name, $this->customerCondition)) {
            throw new InvalidArgumentException("A group or condition with the name `$name` was already set in the procedure filter.");
        }

        $this->procedureCondition[$name]['group'] = (new DrupalFilterGroup($conjunction, $memberOf))->toArray();

        return $this;
    }

    /**
     * @param non-empty-string      $name
     * @param non-empty-string      $conjunction
     * @param non-empty-string|null $memberOf
     *
     * @return $this
     */
    public function addCustomerGroup(string $name, string $conjunction = 'AND', string $memberOf = null): self
    {
        if (array_key_exists($name, $this->customerCondition)) {
            throw new InvalidArgumentException("A group or condition with the name `$name` was already set in the procedure filter.");
        }

        $this->customerCondition[$name]['group'] = (new DrupalFilterGroup($conjunction, $memberOf))->toArray();

        return $this;
    }

    public function getPermission(): Permission
    {
        return $this->permission;
    }

    /**
     * @return array<non-empty-string, array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string, parameter: bool}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    public function getCustomerFilters(): array
    {
        return $this->customerCondition;
    }

    /**
     * @return array<non-empty-string, array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string, parameter: bool}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    public function getProcedureFilters(): array
    {
        return $this->procedureCondition;
    }

    /**
     * @return array<non-empty-string, array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string, parameter: bool}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    public function getUserFilters(): array
    {
        return $this->userCondition;
    }
}
