<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementMetaInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\StatementMetaMiscResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

final class StatementMetaMiscResourceType extends DplanResourceType {

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(StatementMetaMiscResourceConfigBuilder::class);
        $configBuilder->id
            ->readable()
            ->sortable()
            ->filterable();
        $configBuilder->submitterRole
            ->readable(false, static function (StatementMeta $statementMeta): ?string {
                $miscData = $statementMeta->getMiscData();

                return $miscData[StatementMetaInterface::SUBMITTER_ROLE];
            });
        $configBuilder->isCitizenRole
            ->readable(false, static function (StatementMeta $statementMeta): bool {
                $miscData = $statementMeta->getMiscData();

                return  StatementMetaInterface::SUBMITTER_ROLE_CITIZEN === $miscData[StatementMetaInterface::SUBMITTER_ROLE];
            });



        return $configBuilder;
    }

    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return [$this->conditionFactory->false()];
        }

        $procedureId = $currentProcedure->getId();

        return [
            $this->conditionFactory->propertyHasValue($procedureId, Paths::statementMeta()->statement->procedure->id),
            $this->conditionFactory->propertyHasValue(false, Paths::statementMeta()->statement->deleted),
        ];
    }

    public static function getName(): string
    {
        return 'StatementMetaMisc'; //@todo fix me
    }

    public function getEntityClass(): string
    {
        return StatementMeta::class; //this is the context in the getaccess conditions
    }

    public function isAvailable(): bool
    {
        return null !== $this->currentProcedureService->getProcedure();

    }

}
