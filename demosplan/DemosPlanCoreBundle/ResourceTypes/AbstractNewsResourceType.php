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

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template T of object
 *
 * @template-extends DplanResourceType<T>
 *
 * @property-read End              $ident
 * @property-read End              $title
 * @property-read End              $description
 * @property-read End              $text
 * @property-read End              $picture
 * @property-read End              $pictureTitle
 * @property-read End              $pdf
 * @property-read End              $pdfTitle
 * @property-read End              $enabled
 * @property-read End              $deleted
 * @property-read RoleResourceType $roles
 */
abstract class AbstractNewsResourceType extends DplanResourceType
{
    protected function getInitializableNewsProperties(): array
    {
        return [
            $this->createAttribute($this->title)->initializable(),
            $this->createAttribute($this->description)->initializable(),
            $this->createAttribute($this->text)->initializable(),
            $this->createAttribute($this->enabled)->initializable(),
            $this->createAttribute($this->pictureTitle)->initializable(true),
            $this->createAttribute($this->pdfTitle)->initializable(true),
            $this->createAttribute($this->picture)->initializable(true),
            $this->createAttribute($this->pdf)->initializable(true),
            $this->createToOneRelationship($this->roles)->initializable(),
        ];
    }
}
