<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;

class RoleHandler extends CoreHandler
{
    public function __construct(private readonly RoleService $roleService, MessageBag $messageBag)
    {
        parent::__construct($messageBag);
    }

    /**
     * @return Role|null
     */
    public function getRole(string $roleId)
    {
        return $this->roleService->getRole($roleId);
    }

    /**
     * @return Role[]
     */
    public function getRoles(): array
    {
        return $this->roleService->getRoles();
    }

    /**
     * @param string[] $roleIds
     *
     * @return Role[] The Role for each role ID provided. May contain null values where the Role was not found.
     */
    public function getRolesByIds($roleIds): array
    {
        if (!is_array($roleIds)) {
            throw new InvalidArgumentException('$roleIds must be an array of role IDs');
        }

        return $this->roleService->getUserRolesByIds($roleIds);
    }

    /**
     * @param string[] $codes
     *
     * @return Role[]
     */
    public function getUserRolesByCodes(array $codes): array
    {
        return $this->roleService->getUserRolesByCodes($codes);
    }

    /**
     * @param string[] $codes
     *
     * @return Role[]
     */
    public function getUserRolesByGroupCodes(array $codes): array
    {
        return $this->roleService->getUserRolesByGroupCodes($codes);
    }
}
