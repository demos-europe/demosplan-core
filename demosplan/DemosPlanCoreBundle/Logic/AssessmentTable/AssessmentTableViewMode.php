<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use JsonSerializable;

final class AssessmentTableViewMode implements JsonSerializable
{
    /** @var string */
    public const DEFAULT_VIEW = 'view_mode_default';
    /** @var string */
    public const TAG_VIEW = 'view_mode_tag';
    /** @var string */
    public const ELEMENTS_VIEW = 'view_mode_elements';

    /** @var string */
    private $viewMode;

    // @improve T16793

    public function __construct(string $viewMode)
    {
        switch ($viewMode) {
            case self::DEFAULT_VIEW:
            case self::ELEMENTS_VIEW:
            case self::TAG_VIEW:
                break;

            default:
                $viewMode = self::DEFAULT_VIEW;
                break;
        }

        $this->viewMode = $viewMode;
    }

    // @improve T16793

    public static function create(string $viewMode): AssessmentTableViewMode
    {
        return new self($viewMode);
    }

    /**
     * @param string|AssessmentTableViewMode $otherViewMode
     */
    public function is($otherViewMode): bool
    {
        if (!is_a($otherViewMode, __CLASS__)) {
            $otherViewMode = self::create($otherViewMode);
        }

        return 0 === strcmp($this->viewMode, $otherViewMode);
    }

    /**
     * @param string|AssessmentTableViewMode $otherViewMode
     */
    public function isNot($otherViewMode): bool
    {
        return !$this->is($otherViewMode);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->viewMode;
    }

    public function jsonSerialize(): array
    {
        return [
            'viewMode' => $this->viewMode,
        ];
    }
}
