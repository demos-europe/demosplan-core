<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use function array_key_exists;
use ArrayAccess;
use const E_USER_DEPRECATED;
use RuntimeException;
use function trigger_error;

/**
 * Permission Value Object.
 *
 * A permission is a unit of function separation in demosplan. These exist in three flavours:
 *
 * 1) Area: Defines a large section like boilerplates for procedures or maps on public index
 * 2) Feature: Defines a small area inside a large section like autosaving on forms or allowing votes on statements
 * 3) Field: Defines the availability of a field in a form
 *
 * Permissions are defined in DemosPlanCoreBundle:Resource:config:permissions.yml in the following format:
 *
 * <code lang="yaml">
 * feature_name_in_camel_case:
 *     label: "A descriptive label for the permission"
 *     description: |
 *         A multiline description of the effects enabling
 *         this permission has
 *     loginRequired: <bool> # Base check for logged in users
 *     expose: <bool> # Make the permission available for JavaScript-based permission checks
 * </code>
 *
 * All of the permission options have fallback defaults which can be checked and changed
 * at `self::instanceFromArray()`.
 */
class Permission implements ArrayAccess
{
    private const FIELDS = ['label', 'enabled', 'expose', 'loginRequired'];

    private const MUTABLE_FIELDS = ['enabled', 'active', 'loginRequired'];

    /**
     * @var string name of the permission
     */
    protected $name = '';

    /**
     * @var string descriptive text for a permission
     */
    protected $label = '';

    /**
     * @var bool general "is this permission enabled" check
     */
    protected $enabled = false;

    /**
     * @var bool Used to control the menu bar
     */
    protected $active = false;

    /**
     * @var bool should this permission be available to the js frontend?
     */
    protected $expose = false;

    /**
     * @var bool does this permission require a logged in user?
     */
    protected $loginRequired = true;

    private string $description;

    /**
     * @param string $name
     * @param string $label
     * @param bool   $expose
     * @param bool   $loginRequired
     */
    protected function __construct($name, $label, $expose, $loginRequired, string $description)
    {
        $this->name = $name;
        $this->label = $label;
        $this->expose = $expose;
        $this->loginRequired = $loginRequired;
        $this->description = $description;
    }

    /**
     * Sets default values for a permission.
     *
     * @param string $name
     */
    public static function instanceFromArray($name, array $permission): Permission
    {
        $label = '';
        $expose = false;
        $loginRequired = true;
        $description = '';

        if (array_key_exists('label', $permission)) {
            $label = $permission['label'];
        }

        if (array_key_exists('expose', $permission)) {
            $expose = $permission['expose'];
        }

        if (array_key_exists('loginRequired', $permission)) {
            $loginRequired = $permission['loginRequired'];
        }

        if (array_key_exists('description', $permission)) {
            $description = $permission['description'];
        }

        if (array_key_exists('deprecated', $permission)) {
            trigger_error(
                "Permission {$name} is deprecated. Deprecation note: {$permission['deprecated']}",
                E_USER_DEPRECATED
            );
        }

        return new self($name, $label, $expose, $loginRequired, $description);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isExposed(): bool
    {
        return $this->expose;
    }

    public function isLoginRequired(): bool
    {
        return $this->loginRequired;
    }

    /**
     * Don't do the login check for a permission.
     */
    public function overlookSession(): void
    {
        $this->setLoginRequired(false);
    }

    public function requireLogin(): void
    {
        $this->setLoginRequired(true);
    }

    /**
     * @param bool $enabled
     *
     * @return Permission
     */
    public function setEnabled($enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Enable the permission.
     */
    public function enable(): void
    {
        $this->setEnabled(true);
    }

    /**
     * Disable the permission.
     */
    public function disable(): void
    {
        $this->setEnabled(false);
    }

    /**
     * @param bool $active
     *
     * @return Permission
     */
    public function setActive($active): self
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @param bool $loginRequired
     *
     * @return Permission
     */
    public function setLoginRequired($loginRequired): self
    {
        $this->loginRequired = $loginRequired;

        return $this;
    }

    public function offsetExists($offset): bool
    {
        return in_array($offset, self::FIELDS, true);
    }

    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * @param string $offset
     * @param mixed  $value
     *
     * @throws RuntimeException
     */
    public function offsetSet($offset, $value): void
    {
        if (!in_array($offset, self::MUTABLE_FIELDS, true)) {
            throw new RuntimeException("$offset is not mutable.");
        }

        // use setter instead of just setting value with $this->$value;
        $this->{'set'.ucfirst($offset)}($value);
    }

    /**
     * @param string $offset
     *
     * @throws RuntimeException
     */
    public function offsetUnset($offset): void
    {
        throw new RuntimeException('Method not allowed');
    }
}
