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

use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\IsFileAvailableEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\IsFileDirectlyAccessibleEventInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\FileResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\IsFileAvailableEvent;
use demosplan\DemosPlanCoreBundle\Event\IsFileDirectlyAccessibleEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<FileInterface>
 *
 * @property-read End $filename
 * @property-read End $ident
 * @property-read End $deleted
 * @property-read End $hash
 * @property-read End $created
 * @property-read End $mimetype
 * @property-read ProcedureResourceType $procedure
 */
final class FileResourceType extends DplanResourceType implements FileResourceTypeInterface
{
    public function __construct(
        private readonly ProcedureAccessEvaluator $procedureAccessEvaluator,
    ) {
    }

    public function getEntityClass(): string
    {
        return File::class;
    }

    public static function getName(): string
    {
        return 'File';
    }

    public function getIdentifierPropertyPath(): array
    {
        return $this->ident->getAsNames();
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

    /**
     * Accessible are files without procedure (global assets) or files of a procedure
     * the user has access to. Scoping is required here because this resource type
     * exposes the file hash, which grants access to the file bytes.
     */
    protected function getAccessConditions(): array
    {
        $procedureConditions = [$this->conditionFactory->propertyIsNull($this->procedure)];

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
                $this->procedure->id
            );
        }

        return [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->anyConditionApplies(...$procedureConditions),
        ];
    }

    protected function isDirectlyAccessible(): bool
    {
        $event = new IsFileDirectlyAccessibleEvent();
        $this->eventDispatcher->dispatch($event, IsFileDirectlyAccessibleEventInterface::class);

        return $event->isFileDirectlyAccessible() || $this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'field_sign_language_overview_video_edit'
        );
    }

    public function isGetAllowed(): bool
    {
        return $this->isDirectlyAccessible();
    }

    public function isListAllowed(): bool
    {
        return $this->isDirectlyAccessible();
    }

    protected function getProperties(): array
    {
        // The 'id' property exists in File, but it is (completely?) null
        // The actual ID is stored in 'ident', hence we need to use an alias here.
        $id = $this->createIdentifier()->readable()->aliasedPath($this->ident);
        $hash = $this->createAttribute($this->hash);
        $filename = $this->createAttribute($this->filename);
        $created = $this->createAttribute($this->created);
        $mimetype = $this->createAttribute($this->mimetype);
        $properties = [
            $id,
            $hash,
            $filename,
            $created,
            $mimetype,
        ];

        if ($this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'area_admin_original_statement_list',
            'area_admin_statement_list',
            'area_admin_import')
        ) {
            $id->filterable()->sortable();
            $hash->readable(true)->filterable()->sortable();
            $filename->readable(true, self::getFileName(...));
        }

        if ($this->currentUser->hasPermission('field_sign_language_overview_video_edit')) {
            $mimetype->readable();
            $hash->filterable();
        }

        if ($this->currentUser->hasPermission('feature_platform_logo_edit')) {
            $hash->readable();
        }

        return $properties;
    }

    public static function getFileName(File $file): string
    {
        return $file->getFilename();
    }
}
