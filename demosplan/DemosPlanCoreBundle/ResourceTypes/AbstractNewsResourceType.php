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

use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template T of EntityInterface
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
}
