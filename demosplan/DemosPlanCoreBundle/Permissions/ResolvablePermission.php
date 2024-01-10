<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Permission\PermissionCondition;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An instance of this class represents a permission and provides all information necessary to
 * determine if that permission should be enabled in a specific context (current user, current
 * customer, current procedure).
 *
 * Beside some basic permission information, instances of this class carry three filter array
 * properties:
 *
 * * {@link self::$customerFilters}
 * * {@link self::$userFilters}
 * * {@link self::$procedureFilters}
 *
 * Each filter will be evaluated against the current customer, user or procedure, respectively.
 * If all three match, then the permission represented by this instance will be considered enabled.
 *
 * Filters that are set to empty arrays will be handled as "there is no condition to be fulfilled
 * for the permission to be enabled". So if all filters are set to empty arrays,
 * the permission will always be enabled, regardless of the state of the application (i.e. the
 * current customer, current user and current procedure).
 */
class ResolvablePermission
{
    use Timestampable;

    final public const CURRENT_USER_ID = '$currentUserId';

    final public const CURRENT_CUSTOMER_ID = '$currentCustomerId';

    final public const CURRENT_PROCEDURE_ID = '$currentProcedureId';

    private const PARAMETER_VALUES = [self::CURRENT_USER_ID, self::CURRENT_CUSTOMER_ID, self::CURRENT_PROCEDURE_ID];

    /**
     * @var list<PermissionCondition>
     */
    private array $conditions;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $label
     */
    public function __construct(#[Assert\NotBlank(normalizer: 'trim', allowNull: false)]
        #[Assert\Type(type: 'string')]
        #[Assert\Regex(pattern: '/^[a-z]+(_[a-z]+)*$/')]
        private readonly string $name, #[Assert\NotBlank(normalizer: 'trim', allowNull: false)]
        #[Assert\Type(type: 'string')]
        private readonly string $label, #[Assert\NotNull]
        #[Assert\Type(type: 'string')]
        private readonly string $description, private readonly bool $exposed)
    {
        $now = Carbon::now();
        $this->setCreatedAt($now);
        $this->setUpdatedAt($now);
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return list<PermissionCondition>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @param list<PermissionCondition> $conditions
     */
    public function setConditions(array $conditions): void
    {
        $this->update();
        $this->conditions = $conditions;
    }

    public function isExposed(): bool
    {
        return $this->exposed;
    }

    protected function update(): void
    {
        $this->setUpdatedAt(Carbon::now());
    }
}
