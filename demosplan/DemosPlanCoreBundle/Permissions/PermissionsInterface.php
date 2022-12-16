<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;

/**
 * Zentrale Berechtigungssteuerung fuer Funktionen.
 */
interface PermissionsInterface
{
    /**
     * Initialize all permissions that do not depend on a procedure.
     */
    public function initPermissions(User $user, array $context = null): Permissions;

    /**
     * Ist die Organisation des angemeldeten Nutzers Inhaberin des Verfahrens?
     */
    public function ownsProcedure(): bool;

    /**
     * Ist der User mit seiner Organisation beteiligt?
     */
    public function isMember(): bool;

    /**
     * Returns active permissionset.
     *
     * @param string $scope
     *
     * @return string
     */
    public function getPermissionset($scope);

    /**
     * Hat der User ein Permissionset Read?
     *
     * @param string|null $scope
     */
    public function hasPermissionsetRead($scope = null): bool;

    /**
     * Hat der User ein Permissionset Write?
     *
     * @param string|null $scope
     */
    public function hasPermissionsetWrite($scope = null): bool;

    /**
     * Setzt das Menue-Highlight eines einzelnen Permissions.
     *
     * @param string $permission
     */
    public function setMenuhighlighting($permission);

    /**
     * Infos zu einem bestimmten Permission
     * Liefert einen Array mit den Informationen zum Permission.
     *
     * @param string $permission
     *
     * @return Permission|false
     */
    public function getPermission($permission);

    /**
     * Array aller Permissions mit Name, Label, Enable-Status, und Hightlight-Status
     * Liefert einen Arraallen Permissions.
     */
    public function getPermissions(): array;

    /**
     * checked, ob der Zugriff auf ein konkretes Permission erlaubt ist
     * wenn nicht wird eine Exception geworfen.
     *
     * @param string $permission
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function checkPermission($permission);

    /**
     * Überprüfe mehrere Rechte.
     *
     * @param array|null $permissions
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function checkPermissions($permissions);

    /**
     * Prüfe, oder der User in das Verfahren darf.
     *
     * @throws AccessDeniedGuestException|AccessDeniedException
     */
    public function checkProcedurePermission(): void;

    /**
     * Hat der User die Permission?
     *
     * @param string $permission
     */
    public function hasPermission($permission): bool;

    /**
     * Hat der User die Permissions?
     *
     * @param string $operator AND or OR
     */
    public function hasPermissions(array $permissions, string $operator = 'AND'): bool;

    public function setProcedure(?Procedure $procedure);

    /**
     * Enable a set of permissions.
     *
     * @param array $permissions permission names
     */
    public function enablePermissions(array $permissions);

    /**
     * Disable a set of permissions.
     *
     * @param array $permissions permission names
     */
    public function disablePermissions(array $permissions);
}
