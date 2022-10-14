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
class ConditionalPermission
{
    /**
     * @var Permission
     */
    private $permission;

    /**
     * @var list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    private $customerCondition;

    /**
     * @var list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    private $procedureCondition;

    /**
     * @var list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    private $userCondition;

    public function __construct(Permission $permission)
    {
        $this->permission = $permission;
    }

    /**
     * @param mixed $value
     */
    public function addUserCondition(string $path, string $operator, $value, string $memberOf = null): self
    {
        $this->userCondition = $this->userCondition ?? [];
        $this->userCondition[]['condition'] = (new DrupalFilterCondition($path, $operator, $value, $memberOf))->toArray();

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function addProcedureCondition(string $path, string $operator, $value, string $memberOf = null): self
    {
        $this->procedureCondition = $this->procedureCondition ?? [];
        $this->procedureCondition[]['condition'] = (new DrupalFilterCondition($path, $operator, $value, $memberOf))->toArray();

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function addCustomerCondition(string $path, string $operator, $value, string $memberOf = null): self
    {
        $this->customerCondition = $this->customerCondition ?? [];
        $this->customerCondition[]['condition'] = (new DrupalFilterCondition($path, $operator, $value, $memberOf))->toArray();

        return $this;
    }
    public function addUserGroup($conjunction = 'AND', $memberOf = null): self
    {
        $this->userCondition = $this->userCondition ?? [];
        $this->userCondition[]['group'] = (new DrupalFilterGroup($conjunction, $memberOf))->toArray();

        return $this;
    }

    public function addProcedureGroup($conjunction = 'AND', $memberOf = null): self
    {
        $this->procedureCondition = $this->procedureCondition ?? [];
        $this->procedureCondition[]['group'] = (new DrupalFilterGroup($conjunction, $memberOf))->toArray();

        return $this;
    }

    public function addCustomerGroup($conjunction = 'AND', $memberOf = null): self
    {
        $this->customerCondition = $this->customerCondition ?? [];
        $this->customerCondition[]['group'] = (new DrupalFilterGroup($conjunction, $memberOf))->toArray();

        return $this;
    }

    public function getPermission(): Permission
    {
        return $this->permission;
    }

    /**
     * @return list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    public function getCustomerCondition(): ?array
    {
        return $this->customerCondition;
    }

    /**
     * @return list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    public function getProcedureCondition(): ?array
    {
        return $this->procedureCondition;
    }

    /**
     * @return list<array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    public function getUserConditon(): ?array
    {
        return $this->userCondition;
    }
}
