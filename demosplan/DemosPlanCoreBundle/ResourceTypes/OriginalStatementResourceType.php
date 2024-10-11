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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\IsOriginalStatementAvailableEventInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\OriginalStatementResourceTypeInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\IsOriginalStatementAvailableEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\OriginalStatementResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<StatementInterface>
 *
 * @property-read ProcedureResourceType $procedure
 * @property-read StatementResourceType $original
 * @property-read End                   $deleted
 * @property-read StatementResourceType $headStatement
 * @property-read StatementResourceType $movedStatement
 */
final class OriginalStatementResourceType extends DplanResourceType implements OriginalStatementResourceTypeInterface
{
    public static function getName(): string
    {
        return 'OriginalStatement';
    }

    public function getEntityClass(): string
    {
        return Statement::class;
    }

    public function isAvailable(): bool
    {
        /** @var IsOriginalStatementAvailableEvent $event * */
        $event = $this->eventDispatcher->dispatch(new IsOriginalStatementAvailableEvent(), IsOriginalStatementAvailableEventInterface::class);

        return $event->isOriginalStatementeAvailable() || $this->hasAccessPermissions();
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyIsNull($this->original->id),
            $this->conditionFactory->propertyIsNull($this->headStatement->id),
            $this->conditionFactory->propertyIsNull($this->movedStatement),
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id),
        ];
    }

    public function isGetAllowed(): bool
    {
        return $this->hasAccessPermissions();
    }

    public function isListAllowed(): bool
    {
        return $this->hasAccessPermissions();
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(OriginalStatementResourceConfigBuilder::class);
        $configBuilder->id->setReadableByPath();
        $configBuilder->externId->setReadableByPath();
        //$configBuilder->meta->setReadableByPath();
        $configBuilder->submitDate->setAliasedPath(Paths::statement()->submit);
        $configBuilder->submitName->setAliasedPath(Paths::statement()->meta->submitName);
        $configBuilder->isSubmittedByCitizen
            ->setReadableByCallable(static fn (Statement $statement): bool => $statement->isSubmittedByCitizen());
        // fullText
        // shortText

        return $configBuilder;
    }

    private function hasAccessPermissions(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_original_statement');
    }
}
