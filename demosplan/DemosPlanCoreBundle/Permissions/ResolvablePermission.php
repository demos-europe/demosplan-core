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

use EDT\Querying\ConditionParsers\Drupal\DrupalFilterValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Beside some basic permission information, instances of this class carry three filter array
 * properties:.
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
 * the permission will always be enabled, regardless of the state of the application (current
 * customer, current user and current procedure).
 *
 * @phpstan-import-type CustomizedDrupalFilter from PermissionResolver
 */
class ResolvablePermission
{
    public const CURRENT_USER_ID = '$currentUserId';

    public const CURRENT_CUSTOMER_ID = '$currentCustomerId';

    public const CURRENT_PROCEDURE_ID = '$currentProcedureId';

    private const PARAMETER_VALUES = [self::CURRENT_USER_ID, self::CURRENT_CUSTOMER_ID, self::CURRENT_PROCEDURE_ID];

    /**
     * @var non-empty-string
     *
     * @Assert\NotBlank(normalizer="trim", allowNull=false)
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[a-z]+(_[a-z]+)*$/")
     */
    private string $name;

    /**
     * @var non-empty-string
     *
     * @Assert\NotBlank(normalizer="trim", allowNull=false)
     * @Assert\Type(type="string")
     */
    private string $label;

    /**
     * @var non-empty-string
     *
     * @Assert\NotNull()
     * @Assert\Type(type="string")
     */
    private string $description;

    /**
     * @var array<non-empty-string, CustomizedDrupalFilter>
     *
     * @Assert\NotNull()
     * @Assert\Type(type="array")
     * TODO: add format validation annotation (can be based on {@link DrupalFilterValidator})
     */
    private array $customerFilters = [];

    /**
     * @var array<non-empty-string, CustomizedDrupalFilter>
     *
     * @Assert\NotNull()
     * @Assert\Type(type="array")
     * TODO: add format validation annotation (can be based on {@link DrupalFilterValidator})
     */
    private array $userFilters = [];

    /**
     * @var array<non-empty-string, CustomizedDrupalFilter>
     *
     * @Assert\NotNull()
     * @Assert\Type(type="array")
     * TODO: add format validation annotation (can be based on {@link DrupalFilterValidator})
     */
    private array $procedureFilters = [];

    /**
     * @param non-empty-string $name
     * @param non-empty-string $label
     * @param non-empty-string $description
     */
    public function __construct(string $name, string $label, string $description)
    {
        $this->name = $name;
        $this->label = $label;
        $this->description = $description;
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

    /**
     * @return non-empty-string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<non-empty-string, CustomizedDrupalFilter>
     */
    public function getCustomerFilters(): array
    {
        return $this->customerFilters;
    }

    /**
     * @param array<non-empty-string, CustomizedDrupalFilter> $customerFilters
     */
    public function setCustomerFilters(array $customerFilters): void
    {
        $this->customerFilters = $customerFilters;
    }

    /**
     * @return array<non-empty-string, CustomizedDrupalFilter>
     */
    public function getUserFilters(): array
    {
        return $this->userFilters;
    }

    /**
     * @param array<non-empty-string, CustomizedDrupalFilter> $userFilters
     */
    public function setUserFilters(array $userFilters): void
    {
        $this->userFilters = $userFilters;
    }

    /**
     * @return array<non-empty-string, CustomizedDrupalFilter>
     */
    public function getProcedureFilters(): array
    {
        return $this->procedureFilters;
    }

    /**
     * @param array<non-empty-string, CustomizedDrupalFilter> $procedureFilters
     */
    public function setProcedureFilters(array $procedureFilters): void
    {
        $this->procedureFilters = $procedureFilters;
    }
}
