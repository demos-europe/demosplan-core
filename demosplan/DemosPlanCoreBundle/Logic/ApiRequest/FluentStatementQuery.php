<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\DqlFluentQuery;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use EDT\ConditionFactory\PathsBasedConditionFactoryInterface;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use EDT\Querying\FluentQueries\ConditionDefinition;
use EDT\Querying\FluentQueries\SliceDefinition;
use EDT\Querying\FluentQueries\SortDefinition;

/**
 * @template-extends DqlFluentQuery<Statement>
 */
class FluentStatementQuery extends DqlFluentQuery
{
    /**
     * @param PathsBasedConditionFactoryInterface<ClauseFunctionInterface<bool>>                              $conditionFactory
     * @param DoctrineOrmEntityProvider<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, Statement> $objectProvider
     */
    public function __construct(
        PathsBasedConditionFactoryInterface $conditionFactory,
        SortMethodFactoryInterface $sortMethodFactory,
        DoctrineOrmEntityProvider $objectProvider
    ) {
        parent::__construct(
            $objectProvider,
            new StatementConditionDefinition($conditionFactory, true),
            new SortDefinition($sortMethodFactory),
            new SliceDefinition()
        );
    }

    /**
     * @return StatementConditionDefinition
     */
    public function getConditionDefinition(): ConditionDefinition
    {
        return parent::getConditionDefinition();
    }

    /**
     * @return array<int,Statement>
     */
    public function getEntities(): array
    {
        return parent::getEntities();
    }
}
