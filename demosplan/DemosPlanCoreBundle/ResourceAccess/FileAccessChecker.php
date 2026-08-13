<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceAccess;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\IsFileAvailableEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\IsFileDirectlyAccessibleEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\IsFileAvailableEvent;
use demosplan\DemosPlanCoreBundle\Event\IsFileDirectlyAccessibleEvent;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Encapsulates the access control of the {@link \demosplan\DemosPlanCoreBundle\ResourceTypes\FileResourceType}
 * so it stays independent of the EDT resource type implementation and can be reused
 * once the resource type is migrated to API Platform 3.0.
 */
class FileAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProcedureAccessEvaluator $procedureAccessEvaluator,
    ) {
    }

    public function isAvailable(): bool
    {
        // Currently the File resource needs to be exposed for statement import and assessment table.
        $event = new IsFileAvailableEvent();
        $this->eventDispatcher->dispatch($event, IsFileAvailableEventInterface::class);

        return $event->isFileAvailable() || $this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'area_admin_globalnews',
            'feature_platform_logo_edit',
            'feature_read_source_statement_via_api',
            'field_sign_language_overview_video_edit',
        );
    }

    public function isDirectlyAccessible(): bool
    {
        $event = new IsFileDirectlyAccessibleEvent();
        $this->eventDispatcher->dispatch($event, IsFileDirectlyAccessibleEventInterface::class);

        return $event->isFileDirectlyAccessible() || $this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'field_sign_language_overview_video_edit'
        );
    }

    /**
     * Accessible are files without procedure (global assets) or files of a procedure
     * the user has access to. Scoping is required here because this resource type
     * exposes the file hash, which grants access to the file bytes.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $procedureConditions = [$this->conditionFactory->propertyIsNull(['procedure'])];

        $currentProcedure = $this->currentProcedureService->getProcedure();
        $user = $this->currentUser->getUser();
        if ($currentProcedure instanceof Procedure && $user instanceof User) {
            // same procedure scope as statements: current procedure plus
            // procedures configured for cross-procedure segment access
            $configuredProcedures = array_filter(
                $currentProcedure->getSettings()->getAllowedSegmentAccessProcedures()->getValues(),
                static fn (ProcedureInterface $procedure): bool => $procedure instanceof Procedure
            );
            $allowedProcedureIds = $this->procedureAccessEvaluator->filterNonOwnedProcedureIds(
                $user,
                ...$configuredProcedures
            );
            $allowedProcedureIds[] = $currentProcedure->getId();
            $procedureConditions[] = $this->conditionFactory->propertyHasAnyOfValues(
                $allowedProcedureIds,
                ['procedure', 'id']
            );
        }

        return [
            $this->conditionFactory->propertyHasValue(false, ['deleted']),
            $this->conditionFactory->anyConditionApplies(...$procedureConditions),
        ];
    }
}
