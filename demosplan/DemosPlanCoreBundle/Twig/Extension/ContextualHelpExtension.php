<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Logic\Help\HelpService;
use Exception;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * Generiere den HTML-Schnipsel für die kontextuelle Hilfe.
 */
class ContextualHelpExtension extends ExtensionBase
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'contextualHelp',
                $this->contextualHelp(...), ['is_safe' => ['html']]
            ),
        ];
    }

    /**
     * Generiere den HTML-Schnipsel für die kontextuelle Hilfe.
     *
     * @param string $helpTextKey
     * @param array  $cssClasses
     *
     * @return string
     */
    public function contextualHelp($helpTextKey, $cssClasses = [])
    {
        $content = '';
        try {
            $contextualHelp = $this->getHelpService()->getHelpByKey($helpTextKey);

            if (null !== $contextualHelp) {
                $helpText = $contextualHelp->getText();
                $cssClasses = implode(' ', $cssClasses);

                $content = $this->container->get('twig')->render(
                    '@DemosPlanCore/Extension/contextual_help.html.twig',
                    compact('helpText', 'cssClasses')
                );
            }
        } catch (Exception) {
        }

        return $content;
    }

    /**
     * @return HelpService
     */
    public function getHelpService()
    {
        // this is not the huge symfony container but a special small one
        // to avoid loading dependencies on every twig call
        // https://symfonycasts.com/screencast/symfony-doctrine/service-subscriber
        return $this->container->get(HelpService::class);
    }

    public static function getSubscribedServices(): array
    {
        return [
            HelpService::class,
            'twig' => Environment::class,
        ];
    }
}
