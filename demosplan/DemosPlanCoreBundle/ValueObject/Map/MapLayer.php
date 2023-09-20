<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Map;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Intervention\Image\Image;

/**
 * @method string              getTitle()
 * @method Image               getImage()
 * @method string              getUrl()
 * @method CoordinatesViewport getViewport()
 */
class MapLayer extends ValueObject
{
    /**
     * @var CoordinatesViewport
     */
    protected $viewport;

    /**
     * @var Image
     */
    protected $image;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $url;

    public function __construct(
        CoordinatesViewport $viewport,
        Image $image,
        string $title,
        string $url = ''
    ) {
        $this->viewport = $viewport;
        $this->image = $image;
        $this->title = $title;
        $this->url = $url;
        $this->lock();
    }

    public function getLeft(): float
    {
        return $this->viewport->getLeft();
    }

    public function getBottom(): float
    {
        return $this->viewport->getBottom();
    }

    public function getRight(): float
    {
        return $this->viewport->getRight();
    }

    public function getTop(): float
    {
        return $this->viewport->getTop();
    }

    /**
     * Returns the image's coordinates per pixels.
     */
    public function getCpp(): float
    {
        return $this->getWidthInCoordinates() / $this->image->width();
    }

    public function getWidthInCoordinates(): float
    {
        return $this->getRight() - $this->getLeft();
    }

    public function getHeightInPixels(): int
    {
        return $this->image->getHeight();
    }

    public function getWidthInPixels(): int
    {
        return $this->image->getWidth();
    }

    public function getHeightInCoordinates(): float
    {
        return $this->getTop() - $this->getBottom();
    }

    public function isWms(): bool
    {
        return false !== stripos($this->url, 'SERVICE=WMS');
    }
}
