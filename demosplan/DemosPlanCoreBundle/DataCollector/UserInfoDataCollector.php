<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataCollector;

use function compact;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class UserInfoDataCollector extends DataCollector
{
    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    public function __construct(CurrentUserInterface $currentUser)
    {
        $this->currentUser = $currentUser;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $permissions = [];
        $permissionStats = null;
        try {
            $roles = $this->currentUser->getUser()->getDplanRolesArray();
            $permissions = collect($this->currentUser->getPermissions()->getPermissions());

            $enabledPermissions = $permissions->filter->isEnabled();

            $permissionStats = [
                'total'   => $permissions->count(),
                'enabled' => $enabledPermissions->count(),
                'exposed' => $enabledPermissions->filter->isExposed()->count(),
            ];

            $permissions = $enabledPermissions->toArray();
        } catch (UserNotFoundException $e) {
            $roles = [];
            $permissions = [];
            $permissionStats = null;
        } catch (CustomerNotFoundException $e) {
            $roles = [];
        }

        $this->data = compact('roles', 'permissions', 'permissionStats');
    }

    public function getName(): string
    {
        return 'app.user_info_collector';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getRoles(): array
    {
        return $this->data['roles'];
    }

    public function getPermissions(): array
    {
        return $this->data['permissions'];
    }

    public function getPermissionStats(): array
    {
        return $this->data['permissionStats'];
    }
}
