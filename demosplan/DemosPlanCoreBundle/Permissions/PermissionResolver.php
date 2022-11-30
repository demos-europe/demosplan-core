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

use EDT\Querying\ConditionParsers\Drupal\DrupalFilterException;
use EDT\Querying\Contracts\FunctionInterface;
use Ramsey\Uuid\Type\TypeInterface;
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
 * @phpstan-import-type DrupalFilterGroup from DrupalFilterParser
 * @phpstan-import-type DrupalFilterCondition from DrupalFilterParser
 *
 * @phpstan-type ParameterCondition = array{
 *            path: non-empty-string,
 *            parameter: value-of<ResolvablePermission::PARAMETER_VALUES>,
 *            operator: non-empty-string,
 *            memberOf?: non-empty-string
 *          }
 * @phpstan-type CustomizedDrupalFilter = array{
 *            condition: DrupalFilterCondition
 *          }|array{
 *            group: DrupalFilterGroup
 *          }|array{
 *            parameterCondition: ParameterCondition
 *          }
 * @phpstan-type DrupalFilter = array{condition: DrupalFilterCondition}|array{group: DrupalFilterGroup}
 */
class PermissionResolver implements PermissionFilterValidator
{
    private const PARAMETER_CONDITION = 'parameterCondition';
    private const PARAMETER = 'parameter';

    private ConditionEvaluator $conditionEvaluator;

    /**
     * @var DrupalFilterParser<ClauseFunctionInterface<bool>>
     */
    private DrupalFilterParser $filterParser;

    private DrupalFilterValidator $filterValidator;

    public function __construct(
        ConditionEvaluator $conditionEvaluator,
        DqlConditionFactory $conditionFactory,
        ValidatorInterface $validator
    ) {
        $this->conditionEvaluator = $conditionEvaluator;
        $drupalConditionFactory = new PermissionDrupalConditionFactory($conditionFactory);
        $this->filterValidator = new DrupalFilterValidator($validator, $drupalConditionFactory);
        $this->filterParser = new DrupalFilterParser(
            $conditionFactory,
            new DrupalConditionParser($drupalConditionFactory),
            $this->filterValidator
        );
    }

    public function validateFilter($filter): void
    {
        try {
            $this->filterValidator->validateFilter($filter);
        } catch (DrupalFilterException $exception) {
            throw new PermissionFilterException('Invalid filter format', 0, $exception);
        }
    }

    /**
     * Currently this method parses and evaluates the filters in the given permission on every call,
     * which may slow down the application.
     * This needs to be done because the resulting {@link FunctionInterface} instances are not yet
     * cacheable and even if it were, the de-serialization would need to be written noticeable more
     * performant than the parsing currently done.
     *
     * The result this method (the evaluated boolean) can't be cached either, as the result may
     * differ if any property of the given context (user/customer/procedure) or any property of
     * their relationships (and the relationships of those) has been changed.
     *
     * As a future mitigation the addons may be limited to specific properties via
     * {@link TypeInterface} implementations, instead of allowing to directly access the entities.
     * This would allow to avoid the usage of performance heavy evaluations and may even allow to
     * dynamically determine which entity changes are relevant to cache the evaluation result.
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
     * @param array<non-empty-string, CustomizedDrupalFilter> $filterList
     * @param Customer|User|Procedure|null                    $evaluationTarget
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
     * Checks each condition in the list for `parameterCondition` entries. For each of them a new
     * `condition` entry will be created with the same settings, except that the value in the
     * `parameter` field will be used to get the ID of the given {@link User}, {@link Procedure}
     * or {@link Customer}, which will be used for the `value` field in the added `condition`
     * entry.
     *
     * If a `parameterCondition` entry needs the ID of an instance which is set to `null`, then
     * the special {@link PermissionDrupalConditionFactory::FALSE} condition will be used, which
     * always evaluates to `false`. This does not necessarily mean that the whole filter list
     * results in `false`, as the {@link PermissionDrupalConditionFactory::FALSE} condition may
     * be in a `OR` conjunct group.
     *
     * The `parameterCondition` entry will be removed (replaced with the created `condition` entry)
     * in the process.
     *
     * @param array<non-empty-string, CustomizedDrupalFilter> $filterList
     *
     * @return array<non-empty-string, DrupalFilter>
     */
    private function replaceParameterConditions(
        array $filterList,
        ?User $user,
        ?Procedure $procedure,
        ?Customer $customer
    ): array {
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
     * @param array{parameterCondition: ParameterCondition} $conditionWrapper
     * @param Procedure|Customer|User|null                  $entity
     *
     * @return array{condition: DrupalFilterCondition}
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
