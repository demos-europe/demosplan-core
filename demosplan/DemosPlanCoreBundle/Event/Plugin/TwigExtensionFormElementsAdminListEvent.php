<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Plugin;

use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class TwigExtensionFormElementsAdminListEvent extends DPlanEvent
{
    /**
     * @var string Markup to add to form
     */
    protected $markup;

    public function addMarkup(string $markup)
    {
        $this->markup .= $markup;
    }

    public function getMarkup(): string
    {
        return $this->markup ?? '';
    }
}
