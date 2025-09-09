<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

/**
 * Class ViewOrientation
 * <p>
 * Defines valid view orientations.
 */
class ViewOrientation
{
    /** @var string */
    final public const PORTRAIT_NAME = 'portrait';
    /** @var string */
    final public const LANDSCAPE_NAME = 'landscape';

    /** @var string */
    private $name;

    public function __construct(string $name)
    {
        if (self::LANDSCAPE_NAME !== $name && self::PORTRAIT_NAME !== $name) {
            throw new InvalidArgumentException('value not allowed as name: "'.$name.'"');
        }
        $this->name = $name;
    }

    /**
     * @return string The name of the view orientation
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    protected static function create($name): ViewOrientation
    {
        return new self($name);
    }

    public static function createPortrait(): ViewOrientation
    {
        return self::create(self::PORTRAIT_NAME);
    }

    public static function createLandscape(): ViewOrientation
    {
        return self::create(self::LANDSCAPE_NAME);
    }

    public function isLandscape(): bool
    {
        return self::LANDSCAPE_NAME === $this->getName();
    }

    public function isPortrait(): bool
    {
        return self::PORTRAIT_NAME === $this->getName();
    }
}
