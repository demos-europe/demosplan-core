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
     * @var null|DrupalFilter
     */
    private $customerCondition;

    /**
     * @var null|DrupalFilter
     */
    private $procedureCondition;

    /**
     * @var null|DrupalFilter
     */
    private $userCondition;

    public function __construct(Permission $permission)
    {
        $this->permission = $permission;
    }

    public function getPermission(): Permission
    {
        return $this->permission;
    }

    /**
     * @return null|array<non-empty-string, array{condition: array{operator: non-empty-string, path: non-empty-string, memberOf: null|non-empty-string, value: null|mixed}}|array{group: array{memberOf: null|non-empty-string, conjunction: non-empty-string}}
     */
    public function getCustomerCondition(): ?array
    {
        return $this->customerCondition;
    }

    /**
     * @return null|array<non-empty-string, array{condition: array{operator: non-empty-string, path: non-empty-string, memberOf: null|non-empty-string, value: null|mixed}}|array{group: array{memberOf: null|non-empty-string, conjunction: non-empty-string}}
     */
    public function getProcedureCondition(): ?array
    {
        return $this->procedureCondition;
    }

    /**
     * @return null|array<non-empty-string, array{condition: array{operator: non-empty-string, path: non-empty-string, memberOf: null|non-empty-string, value: null|mixed}}|array{group: array{memberOf: null|non-empty-string, conjunction: non-empty-string}}
     */
    public function getUserConditon(): ?array
    {
        return $this->userCondition;
    }

    public function addUserCondition(DrupalFilterCondition $filterCondition): self
    {
        return $this;
    }

    public function addUserGroup(DrupalFilterGroup $filterGroup): self
    {
        return $this;
    }

}
