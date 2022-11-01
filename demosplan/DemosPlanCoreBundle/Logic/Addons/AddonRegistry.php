<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Addons;

use demosplan\DemosPlanCoreBundle\Entity\DplanAddon;
use demosplan\DemosPlanCoreBundle\Permissions\ResolvablePermission;
use demosplan\DemosPlanCoreBundle\Repository\DplanAddonRepository;
use InvalidArgumentException;

class AddonRegistry
{
    private DplanAddonRepository $addonRepository;

    public function __construct(DplanAddonRepository $addonRepository)
    {
        $this->addonRepository = $addonRepository;
    }

    /**
     * @return array<non-empty-string, array<non-empty-string, ResolvablePermission>>
     */
    public function getAllAddonPermissions(): array
    {
        return collect($this->getActivatedPackageNames())
            ->mapWithKeys(function (string $packageName): array {
                return [$packageName => $this->getAddonPermissions($packageName)];
            })
            ->all();
    }

    /**
     * @return list<non-empty-string>
     */
    protected function getActivatedPackageNames(): array
    {
        // FIXME
        return [];
    }

    /**
     * @param non-empty-string $packageName
     *
     * @return array<non-empty-string, ResolvablePermission>
     */
    protected function getAddonPermissions(string $packageName): array
    {
        $activator = $this->getAddonActivator($packageName);
        $permissions = $activator->getAddonPermissionsWithDefaults();

        $keyedPermissions = collect($permissions)
            ->mapWithKeys(fn (ResolvablePermission $conditionalPermission): array => [$conditionalPermission->getName() => $conditionalPermission])
            ->all();

        if (count($keyedPermissions) !== count($permissions)) {
            throw new InvalidArgumentException('Addon returned at least one permission multiple times.');
        }

        return $keyedPermissions;
    }

    protected function getAddonActivator(string $packageName): AddonActivatorInterface
    {
        // FIXME
    }

    /**
     * @param non-empty-string $packageName
     */
    public function activateAddon(string $packageName)
    {
        $dplanAddon = new DplanAddon($packageName);
        $this->addonRepository->persistAndFlush($dplanAddon);
    }
}
