<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

class GisLayerCategoryTreeTooDeepException extends Exception
{
    /** @var int|null */
    protected $treeDepth;
    /** @var int|null */
    protected $maxTreeDepth;

    public static function create(int $treeDepth, int $maxTreeDepth): GisLayerCategoryTreeTooDeepException
    {
        $e = new self("GisLayerCategories must not be nested deeper than {$maxTreeDepth} levels. Given depth: {$treeDepth}.");
        $e->maxTreeDepth = $maxTreeDepth;
        $e->treeDepth = $treeDepth;

        return $e;
    }

    /**
     * @return int|null
     */
    public function getTreeDepth()
    {
        return $this->treeDepth;
    }

    /**
     * @return int|null
     */
    public function getMaxTreeDepth()
    {
        return $this->maxTreeDepth;
    }
}
