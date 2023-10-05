<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use Psr\Container\ContainerInterface;
use Twig\TwigFunction;

/**
 * Prüft, ob der User das Recht an einem permission hat.
 *
 * Synonyme Aufrufe mit hasPermission und isEnabled, weil semanteisch bei area_* und features_*
 * geprüft wird, ob das Recht vorhanden ist, bei field_* ist die Frage, ob das Feld
 * für das Projekt aktiviert ist.
 */
class HasPermissionExtension extends ExtensionBase
{
    public function __construct(ContainerInterface $container, private readonly PermissionsInterface $permissions)
    {
        parent::__construct($container);
    }

    /* (non-PHPdoc)
     * @see AbstractExtension::getFilters()
     */
    public function getFunctions(): array
    {
        // Die Twig-Funktion kann via hasPermission und isEnabled aufgerufen werden
        return [
            new TwigFunction('hasPermission', $this->hasPermission(...)),
            new TwigFunction('isEnabled', $this->hasPermission(...)),
            new TwigFunction('hasOneOfPermissions', $this->hasOneOfPermissions(...)),
        ];
    }

    /**
     * Prüfe die Permission.
     *
     * @param string|array $permissionToTest
     *
     * @return bool
     */
    public function hasPermission($permissionToTest)
    {
        //  If no permission to test have been given nothing could be tested
        if ((is_array($permissionToTest) && 0 === count($permissionToTest))
            || (is_string($permissionToTest) && 0 == mb_strlen($permissionToTest))) {
            return false;
        }

        foreach ($this->formatPermission($permissionToTest) as $permission) {
            if (false === $this->permissions->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prüfe, ob mindestens eine Permission true ist.
     *
     * @param string|array $permissionsToTest
     *
     * @return bool
     */
    public function hasOneOfPermissions($permissionsToTest = [])
    {
        //  If no permission to test have been given nothing could be tested
        if ((is_array($permissionsToTest) && 0 === count($permissionsToTest))
            || (is_string($permissionsToTest) && 0 == mb_strlen($permissionsToTest))) {
            return false;
        }

        foreach ($this->formatPermission($permissionsToTest) as $permission) {
            if (true === $this->permissions->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    private function formatPermission($permission)
    {
        if (!is_array($permission)) {
            return [$permission];
        }

        return $permission;
    }
}
