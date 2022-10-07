<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\DqlFluentQuery;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\ConditionFactoryInterface;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use EDT\Querying\FluentQueries\ConditionDefinition;
use EDT\Querying\FluentQueries\FluentQuery;
use EDT\Querying\FluentQueries\SliceDefinition;
use EDT\Querying\FluentQueries\SortDefinition;

/**
 * @template T of object
 *
 * @template-extends CoreRepository<T>
 */
abstract class FluentRepository extends CoreRepository
{
    /**
     * @var ConditionFactoryInterface
     */
    protected $conditionFactory;

    /**
     * @var SortMethodFactoryInterface
     */
    protected $sortMethodFactory;

    /**
     * @var DoctrineOrmEntityProvider
     */
    protected $objectProvider;

    public function __construct(
        DqlConditionFactory $dqlConditionFactory,
        ManagerRegistry $registry,
        SortMethodFactory $sortMethodFactory,
        string $entityClass
    ) {
        parent::__construct($registry, $entityClass);

        $this->conditionFactory = $dqlConditionFactory;
        $this->sortMethodFactory = $sortMethodFactory;

        $this->objectProvider = new DoctrineOrmEntityProvider(
            $entityClass,
            $this->getEntityManager()
        );
    }

    public function createFluentQuery(): FluentQuery
    {
        return new DqlFluentQuery(
            $this->objectProvider,
            new ConditionDefinition($this->conditionFactory, true),
            new SortDefinition($this->sortMethodFactory),
            new SliceDefinition()
        );
    }
}
