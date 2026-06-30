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
use DemosEurope\DemosplanAddon\Contracts\ResourceType\FileResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceAccess\FileAccessChecker;
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
        private readonly FileAccessChecker $fileAccessChecker,
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
        return $this->fileAccessChecker->isAvailable();
    }

    protected function getAccessConditions(): array
    {
        return $this->fileAccessChecker->getAccessConditions();
    }

    protected function isDirectlyAccessible(): bool
    {
        return $this->fileAccessChecker->isDirectlyAccessible();
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
