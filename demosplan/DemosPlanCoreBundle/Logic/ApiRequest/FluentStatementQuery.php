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
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use EDT\Querying\FluentQueries\SliceDefinition;
use EDT\Querying\FluentQueries\SortDefinition;

/**
 * @template-extends DqlFluentQuery<Statement>
 */
class FluentStatementQuery extends DqlFluentQuery
{
    public function __construct(
        DqlConditionFactory $conditionFactory,
        SortMethodFactoryInterface $sortMethodFactory,
        DoctrineOrmEntityProvider $objectProvider,
    ) {
        parent::__construct(
            $objectProvider,
            new StatementConditionDefinition($conditionFactory, true),
            new SortDefinition($sortMethodFactory),
            new SliceDefinition()
        );
    }

    /**
     * @return array<int,Statement>
     */
    public function getEntities(): array
    {
        return parent::getEntities();
    }
}
