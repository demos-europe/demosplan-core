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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Utilities\ConditionEvaluator;
use InvalidArgumentException;

class EvaluatablePermission
{
    public const CURRENT_USER_ID = '$currentUserId';
    public const CURRENT_CUSTOMER_ID = '$currentCustomerId';
    public const CURRENT_PROCEDURE_ID = '$currentProcedureId';

    /**
     * @var ConditionEvaluator
     */
    private $conditionEvaluator;

    /**
     * @var PermissionDecision
     */
    private $permissionDecision;

    /**
     * @var DrupalFilterParser
     */
    private $filterParser;

    /**
     * Conditions that are set to `null` will be handled as "there is no condition to be fulfilled
     * for the permission to be enabled". So if all conditions are set to `null`, the permission
     * will always be enabled, regardless of the state of the application.
     *
     * @param DrupalFilterParser<FunctionInterface<bool>> $filterParser
     */
    public function __construct(
        PermissionDecision $conditionalPermission,
        ConditionEvaluator $conditionEvaluator,
        DrupalFilterParser $filterParser
    ) {
        $this->conditionEvaluator = $conditionEvaluator;
        $this->permissionDecision = $conditionalPermission;
        $this->filterParser = $filterParser;
    }

    /**
     * FIXME: as we parse the Drupal filter on every call this method needs proper caching for each parameter combination
     */
    public function isPermissionEnabled(?User $user, ?Procedure $procedure, ?Customer $customer): bool
    {
        return $this->evaluate($this->permissionDecision->getUserFilters(), $user, $user, $procedure, $customer)
            && $this->evaluate($this->permissionDecision->getProcedureFilters(), $procedure, $user, $procedure, $customer)
            && $this->evaluate($this->permissionDecision->getCustomerFilters(), $customer, $user, $procedure, $customer);
    }

    public function getPermissionMetadata(): Permission
    {
        return $this->permissionDecision->getPermission();
    }

    /**
     * @param array<non-empty-string, array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string, parameter: bool}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}> $filterList
     * @param Customer|User|Procedure|null                                                                                                                                                                                                    $evaluationTarget
     */
    protected function evaluate(array $filterList, ?object $evaluationTarget, ?User $user, ?Procedure $procedure, ?Customer $customer): bool
    {
        $processedFilterList = $this->replaceParameters($filterList, $user, $procedure, $customer);
        if (false === $processedFilterList) {
            return false;
        }

        $conditions = $this->filterParser->parseFilter($processedFilterList);

        // If there is no target (e.g. no procedure because the request has no procedure context)
        // then we only evaluate to `true` if there are no conditions a procedure would need to
        // fulfill.
        if (null === $evaluationTarget) {
            return [] === $conditions;
        }

        foreach ($conditions as $condition) {
            if (!$this->conditionEvaluator->evaluateCondition($evaluationTarget, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks each condition if the `parameter` field is set to `true`. If this is the case, then
     * the value in the `value` field must be {@link EvaluatablePermission::CURRENT_CUSTOMER_ID},
     * {@link EvaluatablePermission::CURRENT_PROCEDURE_ID} or {@link EvaluatablePermission::CURRENT_USER_ID}.
     *
     * The value will then be replaced with ID of the corresponding instances that were given as parameters.
     *
     * The `parameter` field will be removed in the process.
     *
     * @param array<non-empty-string, array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string, parameter: bool}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}> $filterList
     *
     * @return false|array<non-empty-string, array{condition: array{path: non-empty-string, operator: non-empty-string,value?: mixed, memberOf?: non-empty-string}}|array{group: array{conjunction: non-empty-string, memberOf?: non-empty-string}}>
     */
    private function replaceParameters(array $filterList, ?User $user, ?Procedure $procedure, ?Customer $customer)
    {
        foreach ($filterList as $filterName => $conditionOrGroup) {
            if ($conditionOrGroup[DrupalFilterParser::CONDITION][PermissionDecision::PARAMETER] ?? false) {
                $value = $conditionOrGroup[DrupalFilterParser::CONDITION][DrupalFilterParser::VALUE] ?? '';
                switch ($value) {
                    case EvaluatablePermission::CURRENT_CUSTOMER_ID:
                        if (null === $customer) {
                            return false;
                        }
                        $filterList[$filterName][DrupalFilterParser::CONDITION][DrupalFilterParser::VALUE] = $customer->getId();
                        break;
                    case EvaluatablePermission::CURRENT_PROCEDURE_ID:
                        if (null === $procedure) {
                            return false;
                        }
                        $filterList[$filterName][DrupalFilterParser::CONDITION][DrupalFilterParser::VALUE] = $procedure->getId();
                        break;
                    case EvaluatablePermission::CURRENT_USER_ID:
                        if (null === $user) {
                            return false;
                        }
                        $filterList[$filterName][DrupalFilterParser::CONDITION][DrupalFilterParser::VALUE] = $user->getId();
                        break;
                    default:
                        throw new InvalidArgumentException('Invalid value for parameter usage.');
                }
                unset($filterList[$filterName][DrupalFilterParser::CONDITION][PermissionDecision::PARAMETER]);
            }
        }

        return $filterList;
    }
}
