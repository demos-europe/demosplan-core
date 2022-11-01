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
 * Filters that are set to empty arrays will be handled as "there is no condition to be fulfilled
 * for the permission to be enabled". So if all filters ({@link self::$customerFilters},
 * {@link self::$userFilters} and {@link self::$procedureFilters}) are set to empty arrays,
 * the permission will always be enabled, regardless of the state (current customer, user and
 * procedure) of the application.
 *
 * @psalm-type PsalmDrupalFilterGroup = array{
 *            conjunction: non-empty-string,
 *            memberOf?: non-empty-string
 *          }
 * @psalm-type PsalmDrupalFilterCondition = array{
 *            path: non-empty-string,
 *            value?: mixed,
 *            operator: non-empty-string,
 *            memberOf?: non-empty-string
 *          }
 * @psalm-type PsalmParameterCondition = array{
 *            path: non-empty-string,
 *            parameter: value-of<ResolvablePermission::PARAMETER_VALUES>,
 *            operator: non-empty-string,
 *            memberOf?: non-empty-string
 *          }
 * @psalm-type PsalmCustomizedDrupalFilter = array{condition: PsalmDrupalFilterCondition}|array{group: PsalmDrupalFilterGroup}|array{parameterCondition: PsalmParameterCondition}
 */
class ResolvablePermission
{
    public const CURRENT_USER_ID = '$currentUserId';

    public const CURRENT_CUSTOMER_ID = '$currentCustomerId';

    public const CURRENT_PROCEDURE_ID = '$currentProcedureId';

    private const PARAMETER_VALUES = [self::CURRENT_USER_ID, self::CURRENT_CUSTOMER_ID, self::CURRENT_PROCEDURE_ID];

    /**
     * @var non-empty-string
     */
    private string $name;

    /**
     * @var non-empty-string
     */
    private string $label;

    /**
     * @var non-empty-string
     */
    private string $description;

    /**
     * @var array<non-empty-string, PsalmCustomizedDrupalFilter>
     */
    private array $customerFilters = [];

    /**
     * @var array<non-empty-string, PsalmCustomizedDrupalFilter>
     */
    private array $userFilters = [];

    /**
     * @var array<non-empty-string, PsalmCustomizedDrupalFilter>
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
     * @return array<non-empty-string, PsalmCustomizedDrupalFilter>
     */
    public function getCustomerFilters(): array
    {
        return $this->customerFilters;
    }

    /**
     * @param array<non-empty-string, PsalmCustomizedDrupalFilter> $customerFilters
     */
    public function setCustomerFilters(array $customerFilters): void
    {
        $this->customerFilters = $customerFilters;
    }

    /**
     * @return array<non-empty-string, PsalmCustomizedDrupalFilter>
     */
    public function getUserFilters(): array
    {
        return $this->userFilters;
    }

    /**
     * @param array<non-empty-string, PsalmCustomizedDrupalFilter> $userFilters
     */
    public function setUserFilters(array $userFilters): void
    {
        $this->userFilters = $userFilters;
    }

    /**
     * @return array<non-empty-string, PsalmCustomizedDrupalFilter>
     */
    public function getProcedureFilters(): array
    {
        return $this->procedureFilters;
    }

    /**
     * @param array<non-empty-string, PsalmCustomizedDrupalFilter> $procedureFilters
     */
    public function setProcedureFilters(array $procedureFilters): void
    {
        $this->procedureFilters = $procedureFilters;
    }
}
