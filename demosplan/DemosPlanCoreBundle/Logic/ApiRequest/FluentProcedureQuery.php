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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\Querying\Contracts\ConditionFactoryInterface;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use EDT\Querying\FluentQueries\ConditionDefinition;
use EDT\Querying\FluentQueries\SliceDefinition;
use EDT\Querying\FluentQueries\SortDefinition;

/**
 * @template-extends DqlFluentQuery<Procedure>
 */
class FluentProcedureQuery extends DqlFluentQuery
{
    /**
     * @param DoctrineOrmEntityProvider<Procedure> $objectProvider
     */
    public function __construct(
        ConditionFactoryInterface $conditionFactory,
        SortMethodFactoryInterface $sortMethodFactory,
        DoctrineOrmEntityProvider $objectProvider
    ) {
        parent::__construct(
            $objectProvider,
            new ProcedureConditionDefinition($conditionFactory, true),
            new SortDefinition($sortMethodFactory),
            new SliceDefinition()
        );
    }

    /**
     * @return ProcedureConditionDefinition
     */
    public function getConditionDefinition(): ConditionDefinition
    {
        return parent::getConditionDefinition();
    }

    /**
     * @return array<int,Procedure>
     */
    public function getEntities(): array
    {
        return parent::getEntities();
    }
}
