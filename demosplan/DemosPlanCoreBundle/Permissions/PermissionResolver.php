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

use function array_key_exists;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\Querying\ConditionParsers\Drupal\DrupalConditionParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterValidator;
use EDT\Querying\Utilities\ConditionEvaluator;
use InvalidArgumentException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
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
 * @psalm-type PsalmDrupalFilter = array{condition: PsalmDrupalFilterCondition}|array{group: PsalmDrupalFilterGroup}
 */
class PermissionResolver
{
    private const PARAMETER_CONDITION = 'parameterCondition';
    private const PARAMETER = 'parameter';

    private ConditionEvaluator $conditionEvaluator;

    /**
     * @var DrupalFilterParser<ClauseFunctionInterface<bool>>
     */
    private DrupalFilterParser $filterParser;

    /**
     * @param DrupalFilterParser<ClauseFunctionInterface<bool>> $filterParser
     */
    public function __construct(
        ConditionEvaluator $conditionEvaluator,
        DqlConditionFactory $conditionFactory,
        ValidatorInterface $validator
    ) {
        $this->conditionEvaluator = $conditionEvaluator;
        $drupalConditionFactory = new PermissionDrupalConditionFactory($conditionFactory);
        $this->filterParser = new DrupalFilterParser(
            $conditionFactory,
            new DrupalConditionParser($drupalConditionFactory),
            new DrupalFilterValidator($validator, $drupalConditionFactory)
        );
    }

    /**
     * FIXME: as we parse the Drupal filter on every call this method needs proper caching for each parameter combination.
     */
    public function isPermissionEnabled(
        ResolvablePermission $permission,
        ?User $user,
        ?Procedure $procedure,
        ?Customer $customer
    ): bool {
        return $this->evaluate($permission->getUserFilters(), $user, $user, $procedure, $customer)
            && $this->evaluate($permission->getProcedureFilters(), $procedure, $user, $procedure, $customer)
            && $this->evaluate($permission->getCustomerFilters(), $customer, $user, $procedure, $customer);
    }

    /**
     * @param array<non-empty-string, PsalmCustomizedDrupalFilter> $filterList
     * @param Customer|User|Procedure|null                         $evaluationTarget
     */
    protected function evaluate(
        array $filterList,
        ?UuidEntityInterface $evaluationTarget,
        ?User $user,
        ?Procedure $procedure,
        ?Customer $customer
    ): bool {
        $processedFilterList = $this->replaceParameterConditions($filterList, $user, $procedure, $customer);
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
     * Checks each condition if the list for `parameterCondition` entries. For each of them a new
     * `condition` entry will be created with the same settings, except that the value in the
     * `parameter` field will be used to get the ID of the given {@link User}, {@link Procedure}
     * or {@link Customer}, which will be used for the `value` field in the added `condition`
     * entry.
     *
     * If a `parameterCondition` entry needs the ID of an instance which is set to `null`, then
     *
     *
     *
     * The value will then be replaced with ID of the corresponding instances that were given as parameters.
     *
     * The `parameter` field will be removed in the process.
     *
     * @param array<non-empty-string, PsalmCustomizedDrupalFilter> $filterList
     *
     * @return array<non-empty-string, PsalmDrupalFilter>
     */
    private function replaceParameterConditions(array $filterList, ?User $user, ?Procedure $procedure, ?Customer $customer): array
    {
        foreach ($filterList as $filterName => $conditionWrapper) {
            if (array_key_exists(self::PARAMETER_CONDITION, $conditionWrapper)) {
                switch ($conditionWrapper[self::PARAMETER_CONDITION][self::PARAMETER]) {
                    case ResolvablePermission::CURRENT_CUSTOMER_ID:
                        $filterList[$filterName] = $this->adjustCondition($conditionWrapper, $customer);
                        break;
                    case ResolvablePermission::CURRENT_PROCEDURE_ID:
                        $filterList[$filterName] = $this->adjustCondition($conditionWrapper, $procedure);
                        break;
                    case ResolvablePermission::CURRENT_USER_ID:
                        $filterList[$filterName] = $this->adjustCondition($conditionWrapper, $user);
                        break;
                    default:
                        throw new InvalidArgumentException('Invalid value for parameter usage.');
                }
            }
        }

        return $filterList;
    }

    /**
     * @param array{parameterCondition: PsalmParameterCondition} $conditionWrapper
     * @param Procedure|Customer|User|null                       $entity
     *
     * @return array{condition: PsalmDrupalFilterCondition}
     */
    private function adjustCondition(array $conditionWrapper, ?UuidEntityInterface $entity): array
    {
        $condition = $conditionWrapper[self::PARAMETER_CONDITION];
        if (null === $entity) {
            $condition[DrupalFilterParser::OPERATOR] = PermissionDrupalConditionFactory::FALSE;
        } else {
            $condition[DrupalFilterParser::VALUE] = $entity->getId();
        }
        unset($condition[self::PARAMETER]);

        return [DrupalFilterParser::CONDITION => $condition];
    }
}
