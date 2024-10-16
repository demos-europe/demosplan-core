<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\CustomFactory;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Exception\DataProviderException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFragmentService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Doctrine\Persistence\ManagerRegistry;
use Illuminate\Support\Collection;

class StatementFragmentFactory extends FactoryBase
{
    /**
     * @var StatementService
     */
    protected $statementService;

    /**
     * @var Statement
     */
    private $statement;

    public function __construct(
        ManagerRegistry $registry,
        PermissionsInterface $permissions,
        private readonly StatementFragmentService $statementFragmentService,
        StatementService $statementService
    ) {
        $this->statementService = $statementService;

        parent::__construct($registry, $permissions);
    }

    /**
     * @param array $options
     *
     * @throws DataProviderException
     */
    public function configure(...$options): void
    {
        parent::configure(...$options);

        $this->permissions->disablePermissions(
            [
                'feature_statement_assignment',
            ]
        );

        $this->parseOptions($options[0]);
    }

    protected function parseOptions(array $options)
    {
        if (array_key_exists('statementId', $options)) {
            $this->statement = $this->statementService->getStatement($options['statementId']);
        }
    }

    /**
     * Create and persist entity instances.
     */
    public function make(int $amount = 1, int $batchSize = 10): Collection
    {
        return collect(range(1, $amount))->map(
            function ($offset) use ($batchSize) {
                $fragment = $this->generateFragment($this->statement);
                $this->clearEntityManager($offset, $batchSize);

                call_user_func($this->getProgressCallback(), $offset, 'not yet implemented');

                return $fragment->getId();
            });
    }

    /**
     * @throws DataProviderException
     */
    protected function generateFragment(Statement $statement): StatementFragment
    {
        $fragmentData = [
            'statementId' => $statement->getId(),
            'procedureId' => $statement->getProcedure()->getId(),
            'text'        => $this->faker->text($this->faker->numberBetween(200, 2000)),
        ];

        $statementFragment = $this->statementFragmentService->createStatementFragment($fragmentData);

        if (null === $statementFragment) {
            throw new DataProviderException('Failed generating a statement fragment');
        }

        return $statementFragment;
    }
}
