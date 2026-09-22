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
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\Enum\ExportTemplate;

/**
 * Class ViewOrientation
 * <p>
 * Defines valid view orientations.
 */
class ViewOrientation
{
    /** @var string */
    private $name;

    public function __construct(string $name)
    {
        if (ExportTemplate::LANDSCAPE->value !== $name && ExportTemplate::PORTRAIT->value !== $name) {
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
        return self::create(ExportTemplate::PORTRAIT->value);
    }

    public static function createLandscape(): ViewOrientation
    {
        return self::create(ExportTemplate::LANDSCAPE->value);
    }

    public function isLandscape(): bool
    {
        return ExportTemplate::LANDSCAPE->value === $this->getName();
    }

    public function isPortrait(): bool
    {
        return ExportTemplate::PORTRAIT->value === $this->getName();
    }
}
