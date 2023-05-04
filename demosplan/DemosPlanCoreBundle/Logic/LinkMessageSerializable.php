<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class LinkMessageSerializable extends MessageSerializable
{
    protected $routeName = '';
    protected $routeParameters = [];
    protected $linkText = '';
    protected $parsedUrl = '';

    /**
     * @param string $severity
     * @param string $text
     * @param array  $textParameters
     * @param string $routeName
     * @param array  $routeParameters
     * @param string $linkText
     */
    public function __construct(
        $severity,
        $text,
        $textParameters = [],
        $routeName = '',
        $routeParameters = [],
        $linkText = ''
    ) {
        parent::__construct($severity, $text, $textParameters);
        $this->routeName = $routeName;
        $this->routeParameters = $routeParameters;
        $this->linkText = $linkText;
    }

    /**
     * @param string $severity
     * @param string $text
     * @param array  $textParameters
     * @param string $routeName
     * @param array  $routeParameters
     * @param string $linkText
     */
    public static function createLinkMessage(
        $severity,
        $text,
        $textParameters = [],
        $routeName = '',
        $routeParameters = [],
        $linkText = ''
    ): LinkMessageSerializable {
        return new self($severity, $text, $textParameters, $routeName, $routeParameters, $linkText);
    }

    public function prepareUrl(RouterInterface $router)
    {
        $this->parsedUrl = $router->generate(
            $this->routeName,
            $this->routeParameters,
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    public function getParsedUrl()
    {
        $this->parsedUrl;
    }

    /**
     * @return string
     */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * @param string $routeName
     *
     * @return LinkMessageSerializable
     */
    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;

        return $this;
    }

    /**
     * @return array
     */
    public function getRouteParameters()
    {
        return $this->routeParameters;
    }

    /**
     * @param array $routeParameters
     *
     * @return LinkMessageSerializable
     */
    public function setRouteParameters($routeParameters)
    {
        $this->routeParameters = $routeParameters;

        return $this;
    }

    /**
     * @return string
     */
    public function getLinkText()
    {
        return $this->linkText;
    }

    /**
     * @param string $linkText
     *
     * @return LinkMessageSerializable
     */
    public function setLinkText($linkText)
    {
        $this->linkText = $linkText;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'linkUrl'  => $this->parsedUrl,
                'linkText' => $this->linkText,
            ]
        );
    }
}
