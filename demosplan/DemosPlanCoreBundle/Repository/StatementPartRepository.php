<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementPart;
use demosplan\DemosPlanCoreBundle\EventListener\StatementSegmentsSynchronizerListener;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;

class StatementPartRepository extends FluentRepository
{
    public function __construct(
        DqlConditionFactory $conditionFactory,
        ManagerRegistry $registry,
        private readonly StatementService $statementService,
        SortMethodFactory $sortMethodFactory,
        string $entityClass
    ) {
        parent::__construct(
            $conditionFactory,
            $registry,
            $sortMethodFactory,
            $entityClass
        );
    }

    /**
     *
     * This method should only be used from @see StatementSegmentsSynchronizerListener
     * as StatementParts should never be modified manually, ony via @see Statement
     * It is no real upsert as existing data is always deleted
     */
    public function upsert(array $statementParts): array
    {
        $existingObjects = [];
        foreach ($statementParts as $statementPart) {
            $existingObject = $this->getEntityManager()->find(StatementPart::class, $statementPart->getId());
            if ($existingObject) {
                $existingObjects[] = $existingObject;
            }
        }

        $this->persistAndDelete([], $existingObjects);

        return $this->updateObjects($statementParts);
    }

    public function update(StatementPart $statementPart): StatementPart
    {

    }


}
