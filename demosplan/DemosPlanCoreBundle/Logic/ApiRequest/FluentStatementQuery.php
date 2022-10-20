<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\ConditionFactory\ConditionFactoryInterface;
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
     * @param DoctrineOrmEntityProvider<Statement> $objectProvider
     */
    public function __construct(
        ConditionFactoryInterface $conditionFactory,
        SortMethodFactoryInterface $sortMethodFactory,
        DoctrineOrmEntityProvider $objectProvider
    ) {
        parent::__construct(
            $objectProvider,
            new StatementConditionDefinition($conditionFactory, true),
            new SortDefinition($sortMethodFactory),
            new SliceDefinition()
        );
        $this->objectProvider = $objectProvider;
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
