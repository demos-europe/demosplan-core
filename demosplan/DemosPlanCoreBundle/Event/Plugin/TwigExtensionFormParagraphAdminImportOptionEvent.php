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

class TwigExtensionFormParagraphAdminImportOptionEvent extends DPlanEvent
{
    /**
     * @var string Markup to add to form
     */
    protected $markup;

    /**
     * @var string
     */
    protected $formPath;

    public function __construct($variables)
    {
        $this->formPath = is_array($variables) && array_key_exists('path', $variables) ? $variables['path'] : '';
    }

    public function addMarkup($markup)
    {
        $this->markup .= $markup;
    }

    /**
     * @return string
     */
    public function getMarkup()
    {
        return $this->markup;
    }

    /**
     * @return string
     */
    public function getFormPath()
    {
        return $this->formPath;
    }
}
