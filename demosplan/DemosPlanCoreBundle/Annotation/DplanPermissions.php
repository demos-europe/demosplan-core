<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Annotation;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;
use function is_array;
use function is_string;
use RuntimeException;

/**
 * @Annotation
 * @Target("METHOD")
 * @Attributes(
 *  @Attribute("permissions", type="mixed")
 * )
 */
class DplanPermissions
{
    /**
     * The permissions that must be enabled for the annotated route to be available.
     * **All** permissions must be enabled, i.e. they are combined using an AND conjunction.
     *
     * @var mixed
     * @Required()
     */
    public $permissions;

    public function getPermissions(): array
    {
        $permissions = $this->permissions;

        if (is_string($permissions)) {
            return [$permissions];
        }

        if (!is_array($permissions)) {
            throw new RuntimeException('Permissions must be given as string or array');
        }

        return $permissions;
    }
}
