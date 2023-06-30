<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\TwigFunction;

/**
 * twig files rendered by this extension can be found in DemosPlanCoreBundle/Resources/views/UI.
 *
 * type {String}                used to construct the path to the corresponding twig file, e.g. 'form.input.text'
 *                              will render the file /form/input/text.html.twig
 * options {Object}             see individual twig components for possible options
 */
class UIExtension extends ExtensionBase
{
    public function __construct(private readonly CacheInterface $cache, ContainerInterface $container, private readonly Environment $twig)
    {
        parent::__construct($container);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('uiComponent', $this->renderUiComponent(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param string $type
     * @param array  $options
     *
     * @return string
     */
    public function renderUiComponent($type, $options = [])
    {
        $cacheKey = "ui_{$type}";

        $path = $this->cache->get($cacheKey, function (ItemInterface $item) use ($type) {
            $path = $this->getComponentPath($type);
            $item->expiresAfter(300);

            return $path;
        });

        try {
            return $this->twig->render($path, $options);
        } catch (Error $e) {
            return "<code style=\"color: red; size: 32px; font-weight: 900\">UI Component Error for {$type}: {$e->getMessage()}</code>";
        }
    }

    protected function getComponentPath(string $type): string
    {
        $path = '';

        $pathComponents = explode('.', $type);

        // extract subdirs
        while (true) {
            if (is_dir(
                DemosPlanPath::getRootPath(
                    "templates/bundles/DemosPlanCoreBundle/UI/{$path}/{$pathComponents[0]}"
                )
            )) {
                $path .= array_shift($pathComponents).'/';
                continue;
            }

            break;
        }

        // add remainder as modifiers and add .html.twig
        return '@DemosPlanCore/UI/'.$path.implode('.', $pathComponents).'.html.twig';
    }
}
