<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use Psr\Container\ContainerInterface;
use Twig\TwigFilter;

/**
 * Class ObscureExtension.
 *
 * Provides a twig filter to obscure text inside <dp-obscure>-tags
 */
class ObscureExtension extends ExtensionBase
{
    public function __construct(ContainerInterface $container, private readonly EditorService $editorService)
    {
        parent::__construct($container);
    }

    /**
     * Get Twig Filters.
     *
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('dpObscure', $this->obscure(...)),
        ];
    }

    /**
     * Replaces everything inside <dpobscure>-tags with unicode blockchars.
     *
     * @param string $text The input text
     *
     * @return string The obscured text
     */
    public function obscure($text)
    {
        return $this->editorService->obscureString($text);
    }
}
