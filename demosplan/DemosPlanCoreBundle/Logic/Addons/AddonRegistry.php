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
use demosplan\DemosPlanCoreBundle\Permissions\EvaluatablePermission;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionDecision;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\DplanAddonRepository;
use EDT\DqlQuerying\PropertyAccessors\ProxyPropertyAccessor;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Utilities\ConditionEvaluator;

class AddonRegistry
{
    /**
     * @var DplanAddonRepository
     */
    private $addonRepository;

    /**
     * @var ConditionEvaluator
     */
    private $conditionEvaluator;

    /**
     * @var DrupalFilterParser<FunctionInterface<bool>>
     */
    private $filterParser;

    public function __construct(DplanAddonRepository $addonRepository, Permissions $permissions, DrupalFilterParser $filterParser)
    {
        $this->addonRepository = $addonRepository;
        $this->permissions = $permissions;
        $propertyAccessor = new ProxyPropertyAccessor($managerRegistry->getManager());
        $this->conditionEvaluator = new ConditionEvaluator($propertyAccessor);
        $this->filterParser = $filterParser;
    }

    /**
     * @return array<non-empty-string, array<non-empty-string, EvaluatablePermission>>
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
     * @return array<non-empty-string, EvaluatablePermission>
     */
    protected function getAddonPermissions(string $packageName): array
    {
        $activator = $this->getAddonActivator($packageName);
        $permissions = $activator->getAddonPermissionsWithDefaults();

        return collect($permissions)
            ->mapWithKeys(function (PermissionDecision $conditionalPermission): array {
                $permissionMetadata = $conditionalPermission->getPermission();
                $evaluatablePermission = new EvaluatablePermission(
                    $conditionalPermission,
                    $this->conditionEvaluator,
                    $this->filterParser
                );

                return [$permissionMetadata->getName() => $evaluatablePermission];
            })
            ->all();
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
