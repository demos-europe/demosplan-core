<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Event\IsFileAvailableEvent;
use demosplan\DemosPlanCoreBundle\Event\IsFileDirectlyAccessibleEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<File>
 *
 * @property-read End $filename
 * @property-read End $ident
 * @property-read End $deleted
 * @property-read End $hash
 * @property-read End $created
 * @property-read End $mimetype
 */
final class FileResourceType extends DplanResourceType
{
    public function getEntityClass(): string
    {
        return File::class;
    }

    public static function getName(): string
    {
        return 'File';
    }

    public function isAvailable(): bool
    {
        // Currently the File resource needs to be exposed for statement import and assessment table.
        /** @var IsFileAvailableEvent $event * */
        $event = $this->eventDispatcher->dispatch(new IsFileAvailableEvent());

        return $event->isFileAvailable() || $this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'area_admin_globalnews',
            'feature_platform_logo_edit',
            'feature_read_source_statement_via_api',
            'field_sign_language_overview_video_edit',
        );
    }

    /**
     * This method does not check for {@link File::$procedure}, because it depends on where
     * the file is used if access should be restricted. Also note that this property is
     * checked when the actual file bytes are requested.
     */
    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->propertyHasValue(false, ...$this->deleted);
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        /** @var IsFileDirectlyAccessibleEvent $event * */
        $event = $this->eventDispatcher->dispatch(new IsFileDirectlyAccessibleEvent());

        return $event->isFileDirectlyAccessible() || $this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'field_sign_language_overview_video_edit'
        );
    }

    protected function getProperties(): array
    {
        // The 'id' property exists in File, but it is (completely?) null
        // The actual ID is stored in 'ident', hence we need to use an alias here.
        $id = $this->createAttribute($this->id)->readable(true)->aliasedPath($this->ident);
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

        if ($this->currentUser->hasPermission('area_admin_assessmenttable')) {
            $id->filterable()->sortable();
            $hash->readable(true)->filterable()->sortable();
            $filename->readable(true, [self::class, 'getFileName']);
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
