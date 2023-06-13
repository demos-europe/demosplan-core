<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use LogicException;

class AttachedChildException extends LogicException
{
    /**
     * Name of the GisLayerCategory that couldn't be deleted.
     *
     * @var string|null
     */
    protected $name;

    /**
     * @param string|null $name
     *
     * @return static
     */
    public static function hasChildCategories($name): self
    {
        $e = new self('Cannot delete GisLayerCategory with child Categories');
        $e->name = $name;

        return $e;
    }

    /**
     * @param string|null $name
     *
     * @return static
     */
    public static function hasGisLayers($name): self
    {
        $e = new self('Cannot delete GisLayerCategory with attached GisLayers');
        $e->name = $name;

        return $e;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }
}
